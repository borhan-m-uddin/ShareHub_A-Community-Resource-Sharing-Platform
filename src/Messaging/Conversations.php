<?php
namespace App\Messaging;

class Conversations
{
    public static function ensureSchema(): void
    {
        if (!\function_exists('db_connected') || !db_connected()) return;
        global $conn;
        static $done = false; if ($done) return; $done = true;
        @ $conn->query("CREATE TABLE IF NOT EXISTS conversations (
                conversation_id INT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        @ $conn->query("CREATE TABLE IF NOT EXISTS conversation_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                user_id INT NOT NULL,
                last_read_at DATETIME NULL,
                joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_conv_user (conversation_id, user_id),
                KEY idx_user (user_id),
                CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $colsRes = @ $conn->query('SHOW COLUMNS FROM messages');
        $have = [];
        if ($colsRes) { while ($r = $colsRes->fetch_assoc()) { $have[strtolower($r['Field'])] = true; } $colsRes->free(); }
        $alterParts = [];
        if (!isset($have['conversation_id'])) { $alterParts[] = 'ADD COLUMN conversation_id INT NULL'; }
        if (!isset($have['read_at'])) { $alterParts[] = 'ADD COLUMN read_at DATETIME NULL'; }
        if (!isset($have['sender_deleted_at'])) { $alterParts[] = 'ADD COLUMN sender_deleted_at DATETIME NULL'; }
        if (!isset($have['recipient_deleted_at'])) { $alterParts[] = 'ADD COLUMN recipient_deleted_at DATETIME NULL'; }
        if ($alterParts) { @ $conn->query('ALTER TABLE messages ' . implode(', ', $alterParts)); }
        $idxRes = @ $conn->query("SHOW INDEX FROM messages WHERE Key_name='idx_conv'");
        if (!$idxRes || $idxRes->num_rows === 0) { @ $conn->query('ALTER TABLE messages ADD INDEX idx_conv (conversation_id)'); }
        if ($idxRes) { $idxRes->free(); }
    }

    public static function start(array $userIds): ?int
    {
        self::ensureSchema();
        if (!\function_exists('db_connected') || !db_connected()) return null;
        global $conn;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_filter($userIds, fn($v) => $v > 0);
        if (count($userIds) < 2) return null;
        sort($userIds);
        if (count($userIds) === 2) {
            [$a, $b] = $userIds;
            $sql = "SELECT cp1.conversation_id FROM conversation_participants cp1
                    JOIN conversation_participants cp2 ON cp1.conversation_id=cp2.conversation_id
                    WHERE cp1.user_id=? AND cp2.user_id=? LIMIT 1";
            if ($st = $conn->prepare($sql)) {
                $st->bind_param('ii', $a, $b);
                if ($st->execute()) { $r = $st->get_result()->fetch_assoc(); if ($r) { $cid = (int)$r['conversation_id']; $st->close(); return $cid; } }
                $st->close();
            }
        }
        if (!$conn->query('INSERT INTO conversations () VALUES ()')) { return null; }
        $cid = (int)$conn->insert_id; if ($cid <= 0) return null;
        foreach ($userIds as $uid) { @ $conn->query('INSERT IGNORE INTO conversation_participants (conversation_id,user_id) VALUES (' . (int)$cid . ',' . (int)$uid . ')'); }
        return $cid;
    }

    public static function lazyBackfillForUser(int $userId, int $limitPairs = 100): void
    {
        self::ensureSchema();
        if (!\function_exists('db_connected') || !db_connected()) return;
        global $conn; $userId = (int)$userId; if ($userId <= 0) return;
        $sql = "SELECT LEAST(sender_id,receiver_id) a, GREATEST(sender_id,receiver_id) b
                FROM messages
                WHERE conversation_id IS NULL AND (sender_id=$userId OR receiver_id=$userId) AND sender_id<>receiver_id
                GROUP BY a,b
                LIMIT " . (int)$limitPairs;
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $a = (int)$row['a']; $b = (int)$row['b'];
                if ($a <= 0 || $b <= 0 || $a === $b) continue;
                $cid = self::start([$a, $b]);
                if (!$cid) continue;
                @ $conn->query('UPDATE messages SET conversation_id=' . (int)$cid . ' WHERE conversation_id IS NULL AND ((sender_id=' . (int)$a . ' AND receiver_id=' . (int)$b . ') OR (sender_id=' . (int)$b . ' AND receiver_id=' . (int)$a . '))');
            }
            $res->free();
        }
    }

    public static function send(int $conversationId, int $senderId, string $body, ?string $subject = null, ?int $requestId = null): ?int
    {
        self::ensureSchema();
        if (!\function_exists('db_connected') || !db_connected()) return null;
        global $conn;
        $conversationId = (int)$conversationId; $senderId = (int)$senderId;
        $body = trim($body); $subject = trim((string)$subject);
        if ($conversationId <= 0 || $senderId <= 0 || $body === '') return null;
        if ($st = $conn->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1')) {
            $st->bind_param('ii', $conversationId, $senderId);
            $st->execute(); $ok = $st->get_result()->fetch_row(); $st->close();
            if (!$ok) return null;
        }
        $receiverPlaceholder = 0; $mid = null; $GLOBALS['conversation_send_error'] = null;
        if ($requestId !== null && $requestId > 0) {
            $sql = 'INSERT INTO messages (sender_id, receiver_id, request_id, subject, message_content, conversation_id, sent_date, is_read) VALUES (?,?,?,?,?,?,NOW(),0)';
            if ($st = $conn->prepare($sql)) { $st->bind_param('iiissi', $senderId, $receiverPlaceholder, $requestId, $subject, $body, $conversationId); if ($st->execute()) { $mid = (int)$st->insert_id; } else { $GLOBALS['conversation_send_error'] = $st->error ?: $conn->error; } $st->close(); }
        } else {
            $sql = 'INSERT INTO messages (sender_id, receiver_id, subject, message_content, conversation_id, sent_date, is_read) VALUES (?,?,?,?,?,NOW(),0)';
            if ($st = $conn->prepare($sql)) { $st->bind_param('iissi', $senderId, $receiverPlaceholder, $subject, $body, $conversationId); if ($st->execute()) { $mid = (int)$st->insert_id; } else { $GLOBALS['conversation_send_error'] = $st->error ?: $conn->error; } $st->close(); }
        }
        if ($mid) {
            if ($stUp = $conn->prepare('UPDATE conversations SET updated_at=NOW() WHERE conversation_id=? LIMIT 1')) { $stUp->bind_param('i',$conversationId); $stUp->execute(); $stUp->close(); }
            $preview = mb_substr($body, 0, 120);
            $previewEsc = \function_exists('e') ? e($preview) : htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
            if (\function_exists('notify_user')) {
                $resP = @ $conn->query('SELECT user_id FROM conversation_participants WHERE conversation_id=' . (int)$conversationId . ' AND user_id<>' . (int)$senderId);
                if ($resP) { while ($rp = $resP->fetch_assoc()) { notify_user((int)$rp['user_id'], 'message_new', 'New message', $previewEsc, 'conversation', $conversationId, false); } $resP->free(); }
            }
            return $mid;
        }
        return null;
    }

    public static function listForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        self::ensureSchema(); if (!\function_exists('db_connected') || !db_connected()) return [];
        global $conn; $out = [];
        self::lazyBackfillForUser($userId);
        $sql = "SELECT c.conversation_id,
                       c.updated_at,
                       MAX(m.sent_date) last_msg_at,
                       SUBSTRING_INDEX(MAX(CONCAT(m.sent_date,'\t',m.message_content)), '\t', -1) last_msg_body,
                       COUNT(CASE WHEN (m.is_read=0 OR m.read_at IS NULL) AND m.sender_id<>? THEN 1 END) unread_count,
                       COALESCE(u_other.username, '(user)') AS other_username
                FROM conversations c
                JOIN conversation_participants cp_self ON cp_self.conversation_id=c.conversation_id AND cp_self.user_id=?
                LEFT JOIN conversation_participants cp_other ON cp_other.conversation_id=c.conversation_id AND cp_other.user_id<>?
                LEFT JOIN users u_other ON u_other.user_id=cp_other.user_id
                LEFT JOIN messages m ON m.conversation_id=c.conversation_id
                GROUP BY c.conversation_id, other_username
                ORDER BY c.updated_at DESC
                LIMIT ?,?";
        if ($st = $conn->prepare($sql)) { $st->bind_param('iiiii', $userId, $userId, $userId, $offset, $limit); if ($st->execute()) { $res = $st->get_result(); while ($r = $res->fetch_assoc()) { $out[] = $r; } $res->free(); } $st->close(); }
        return $out;
    }

    public static function fetch(int $userId, int $conversationId, int $limit = 200, int $afterMessageId = 0): array
    {
        self::ensureSchema(); if (!\function_exists('db_connected') || !db_connected()) return ['ok' => false, 'messages' => []];
        global $conn; $conversationId = (int)$conversationId; $userId = (int)$userId; $limit = max(1, min(400, $limit)); $afterMessageId = (int)$afterMessageId;
        if ($st = $conn->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1')) { $st->bind_param('ii', $conversationId, $userId); $st->execute(); $m = $st->get_result()->fetch_row(); $st->close(); if (!$m) return ['ok' => false, 'messages' => []]; }
        $cond = $afterMessageId > 0 ? 'AND m.message_id > ' . (int)$afterMessageId : '';
        $sql = "SELECT m.message_id, m.sender_id, m.subject, m.message_content, m.sent_date, m.is_read, m.read_at,
                       u.username AS sender_username, u.first_name, u.last_name
                FROM messages m
                LEFT JOIN users u ON u.user_id = m.sender_id
                WHERE m.conversation_id=? $cond
                ORDER BY m.message_id ASC
                LIMIT ?";
        $out = [];
        if ($st = $conn->prepare($sql)) { $st->bind_param('ii', $conversationId, $limit); if ($st->execute()) { $res = $st->get_result(); while ($r = $res->fetch_assoc()) { $out[] = $r; } $res->free(); } $st->close(); }
        return ['ok' => true, 'messages' => $out];
    }

    public static function markRead(int $userId, int $conversationId): int
    {
        self::ensureSchema(); if (!\function_exists('db_connected') || !db_connected()) return 0;
        global $conn; $userId = (int)$userId; $conversationId = (int)$conversationId; if ($userId <= 0 || $conversationId <= 0) return 0;
        if ($st = $conn->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1')) { $st->bind_param('ii', $conversationId, $userId); $st->execute(); $ok = $st->get_result()->fetch_row(); $st->close(); if (!$ok) return 0; }
    if ($st1 = $conn->prepare('UPDATE messages SET is_read=1, read_at=NOW() WHERE conversation_id=? AND sender_id<>? AND (is_read=0 OR read_at IS NULL)')) { $st1->bind_param('ii',$conversationId,$userId); $st1->execute(); $st1->close(); }
    if ($st2 = $conn->prepare('UPDATE conversation_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=? LIMIT 1')) { $st2->bind_param('ii',$conversationId,$userId); $st2->execute(); $st2->close(); }
        return (int)$conn->affected_rows;
    }

    public static function otherParticipant(int $conversationId, int $selfUserId): ?int
    {
        if (!\function_exists('db_connected') || !db_connected()) return null;
        global $conn; $conversationId = (int)$conversationId; $selfUserId = (int)$selfUserId; if ($conversationId <= 0 || $selfUserId <= 0) return null;
        if ($st = $conn->prepare('SELECT user_id FROM conversation_participants WHERE conversation_id=? AND user_id<>? LIMIT 1')) { $st->bind_param('ii', $conversationId, $selfUserId); if ($st->execute()) { $res = $st->get_result()->fetch_assoc(); $st->close(); if ($res && isset($res['user_id'])) return (int)$res['user_id']; } else { $st->close(); } }
        return null;
    }

    public static function canMessage(int $userId, int $otherUserId): bool
    {
        if (!\function_exists('db_connected') || !db_connected()) return false;
        global $conn; $userId = (int)$userId; $otherUserId = (int)$otherUserId; if ($userId <= 0 || $otherUserId <= 0 || $userId === $otherUserId) return false;
        // Always allow messaging admins
        if ($stR = $conn->prepare('SELECT role FROM users WHERE user_id=? LIMIT 1')) {
            $stR->bind_param('i', $otherUserId);
            if ($stR->execute()) { $roleRow = $stR->get_result()->fetch_assoc(); if ($roleRow && ($roleRow['role'] ?? '') === 'admin') { $stR->close(); return true; } }
            $stR->close();
        }
        $sql = "SELECT 1 FROM requests WHERE ((requester_id=? AND giver_id=?) OR (requester_id=? AND giver_id=?)) AND status IN ('pending','approved','completed') LIMIT 1";
        if ($st = $conn->prepare($sql)) { $st->bind_param('iiii', $userId, $otherUserId, $otherUserId, $userId); if ($st->execute()) { $found = $st->get_result()->fetch_row(); $st->close(); return (bool)$found; } $st->close(); }
        return false;
    }
}
