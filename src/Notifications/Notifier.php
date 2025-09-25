<?php
namespace App\Notifications;

class Notifier
{
    public static function notify(int $userId, string $type, string $subject, string $body, ?string $relatedType = null, ?int $relatedId = null, bool $alsoEmail = false): bool
    {
        if ($userId <= 0) return false;
        if (!\function_exists('db_connected') || !db_connected()) return false;
        global $conn;
        @ $conn->query("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(40) NOT NULL,
            subject VARCHAR(160) NOT NULL,
            body TEXT NULL,
            related_type VARCHAR(40) NULL,
            related_id INT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_related (related_type, related_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $sql = 'INSERT INTO notifications (user_id,type,subject,body,related_type,related_id) VALUES (?,?,?,?,?,?)';
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('issssi', $userId, $type, $subject, $body, $relatedType, $relatedId);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok && $alsoEmail) {
                if ($uStmt = $conn->prepare('SELECT email, email_verified FROM users WHERE user_id=? LIMIT 1')) {
                    $uStmt->bind_param('i', $userId);
                    if ($uStmt->execute()) {
                        $res = $uStmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            if ((int)$row['email_verified'] === 1) {
                                if (\function_exists('send_email')) {
                                    @send_email($row['email'], $subject, nl2br($body));
                                } elseif (\class_exists('App\\Mail\\Mailer')) {
                                    \App\Mail\Mailer::send($row['email'], $subject, nl2br($body));
                                }
                            }
                            if ($res) { $res->free(); }
                        }
                    }
                    $uStmt->close();
                }
            }
            return $ok;
        }
        return false;
    }

    public static function fetchUnread(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) return [];
        if (\function_exists('db_reconnect_if_needed')) { db_reconnect_if_needed(); }
        if (!\function_exists('db_connected') || !db_connected()) return [];
        global $conn;
        $out = [];
        try {
            if ($stmt = $conn->prepare('SELECT id,type,subject,body,related_type,related_id,created_at FROM notifications WHERE user_id=? AND is_read=0 ORDER BY id DESC LIMIT ?')) {
                $stmt->bind_param('ii', $userId, $limit);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) { $out[] = $r; }
                    if ($res) { $res->free(); }
                }
                $stmt->close();
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    public static function markRead(int $userId, array $ids = []): int
    {
        if ($userId <= 0 || !\function_exists('db_connected') || !db_connected()) return 0;
        global $conn;
        $count = 0;
        if ($ids) {
            $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
            if (!$ids) return 0;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids) + 1);
            $params = array_merge([$userId], $ids);
            $sql = 'UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND id IN (' . $placeholders . ') AND is_read=0';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $count = $stmt->affected_rows;
                $stmt->close();
            }
        } else {
            if ($stmt = $conn->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0')) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $count = $stmt->affected_rows;
                $stmt->close();
            }
        }
        return $count;
    }
}
