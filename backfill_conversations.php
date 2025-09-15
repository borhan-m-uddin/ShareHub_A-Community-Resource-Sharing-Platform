<?php
/**
 * backfill_conversations.php
 * One-time / idempotent admin tool to migrate legacy pairwise messages into
 * the new threaded conversation model (conversations + participants + messages.conversation_id).
 *
 * Safety / Idempotency:
 *  - Uses conversation_start([$a,$b]) which reuses an existing conversation if it already exists.
 *  - Only updates messages that still have conversation_id IS NULL.
 *  - Can be re-run; subsequent runs will skip already migrated messages.
 *
 * What it does:
 *  1. Ensures schema (creates conversations + participants + new columns in messages if missing).
 *  2. Finds distinct sender/receiver user pairs in legacy messages without a conversation_id.
 *  3. Creates (or reuses) a conversation for each pair and assigns conversation_id to their messages.
 *  4. Sets read_at timestamps for already read legacy messages (is_read=1 & read_at NULL).
 *  5. Updates each participant's last_read_at to the max read_at of messages from the OTHER user
 *     they have already read (best-effort heuristic so unread badge counts make sense).
 *  6. Outputs a simple HTML report.
 *
 * NOTE: Keep this file temporarily. After successful migration you can delete
 *       or restrict further (e.g. rename) to avoid accidental hits.
 */

require_once __DIR__ . '/bootstrap.php';
require_admin();

// Basic CSRF for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    http_response_code(400);
    echo 'Bad CSRF token';
    exit;
}

$conversationsCreated = 0;
$pairsProcessed       = 0;
$messagesUpdated      = 0;
$messagesPreMigrated  = 0;
$readTimestampsSet    = 0;
$participantReadsSet  = 0;
$error                = '';
$detailRows           = [];
$startedAt            = microtime(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!db_connected()) {
        $error = 'Database not connected.';
    } else {
        global $conn;
        conversations_ensure_schema();

        // 1. Select distinct canonical pairs among unmigrated messages
        $sqlPairs = "SELECT LEAST(sender_id,receiver_id) AS a, GREATEST(sender_id,receiver_id) AS b, COUNT(*) AS cnt, MIN(message_id) AS first_mid
                      FROM messages
                      WHERE conversation_id IS NULL
                      GROUP BY a,b
                      ORDER BY first_mid";
        $resPairs = $conn->query($sqlPairs);
        if ($resPairs) {
            while ($row = $resPairs->fetch_assoc()) {
                $a = (int)$row['a'];
                $b = (int)$row['b'];
                if ($a <= 0 || $b <= 0 || $a === $b) { continue; }
                $pairsProcessed++;
                $cid = conversation_start([$a,$b]);
                if ($cid) {
                    // Count pre-existing messages already attached to a conversation (should be zero in update query)
                    $beforeCountRes = $conn->query('SELECT COUNT(*) c FROM messages WHERE conversation_id='.(int)$cid);
                    $beforeCount = ($beforeCountRes && ($bc = $beforeCountRes->fetch_assoc())) ? (int)$bc['c'] : 0;
                    if ($beforeCountRes) { $beforeCountRes->free(); }

                    $updateSql = 'UPDATE messages SET conversation_id='.(int)$cid.'\n'
                        . ' WHERE conversation_id IS NULL AND ((sender_id='.(int)$a.' AND receiver_id='.(int)$b.') OR (sender_id='.(int)$b.' AND receiver_id='.(int)$a.'))';
                    $conn->query($updateSql);
                    $affected = $conn->affected_rows; // only the ones we just set
                    $messagesUpdated += max(0,$affected);

                    // Determine if conversation was newly created by seeing if participants existed before
                    // Simpler: track if we actually attached any new messages. If new messages added & beforeCount=0 treat as new conversation.
                    if ($beforeCount === 0 && $affected > 0) { $conversationsCreated++; }

                    $detailRows[] = [
                        'pair' => $a.' ↔ '.$b,
                        'conversation_id' => $cid,
                        'messages_migrated' => $affected,
                        'total_pair_messages' => (int)$row['cnt']
                    ];
                }
            }
            $resPairs->free();
        }

        // 2. Set read_at for legacy read messages lacking it
        $conn->query("UPDATE messages SET read_at=sent_date WHERE read_at IS NULL AND is_read=1 AND conversation_id IS NOT NULL");
        $readTimestampsSet = $conn->affected_rows;

        // 3. Approximate last_read_at for participants.
        // For each participant in each conversation, set last_read_at to the max read_at of messages in that conversation not sent by them which are marked read.
        $sqlParticipants = "SELECT cp.conversation_id, cp.user_id
                             FROM conversation_participants cp";
        $resP = $conn->query($sqlParticipants);
        if ($resP) {
            while ($p = $resP->fetch_assoc()) {
                $cid = (int)$p['conversation_id'];
                $uid = (int)$p['user_id'];
                $q = $conn->query('SELECT MAX(read_at) mr FROM messages WHERE conversation_id='.(int)$cid.' AND sender_id<>'.(int)$uid.' AND read_at IS NOT NULL');
                if ($q && ($mr = $q->fetch_assoc()) && !empty($mr['mr'])) {
                    $mrVal = $conn->real_escape_string($mr['mr']);
                    $conn->query("UPDATE conversation_participants SET last_read_at='$mrVal' WHERE conversation_id=$cid AND user_id=$uid AND (last_read_at IS NULL OR last_read_at < '$mrVal')");
                    if ($conn->affected_rows > 0) { $participantReadsSet++; }
                }
                if ($q) { $q->free(); }
            }
            $resP->free();
        }

        // Count how many messages were already migrated
        $resCount = $conn->query('SELECT COUNT(*) c FROM messages WHERE conversation_id IS NOT NULL');
        if ($resCount && ($cr = $resCount->fetch_assoc())) { $messagesPreMigrated = (int)$cr['c']; $resCount->free(); }
    }
}

$elapsed = microtime(true) - $startedAt;

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Backfill Conversations</title>
<style>
body{font-family:system-ui,Arial,sans-serif;margin:20px;line-height:1.4;background:#f7f7f9;color:#222}
pre{background:#fff;border:1px solid #ddd;padding:10px;overflow:auto}
.table{border-collapse:collapse;width:100%;margin-top:16px;background:#fff}
.table th,.table td{border:1px solid #ccc;padding:6px 8px;font-size:14px;text-align:left}
.badge{display:inline-block;background:#0366d6;color:#fff;padding:2px 6px;border-radius:12px;font-size:12px}
.summary{background:#fff;border:1px solid #ddd;padding:12px;margin-top:16px}
button{cursor:pointer}
</style>
</head>
<body>
<h1>Legacy Messages → Conversations Backfill</h1>
<p>This tool migrates old pairwise messages into the new threaded conversation model. It's safe to re-run; already migrated messages are skipped.</p>
<?php if ($error): ?>
    <div style="color:#b30000;font-weight:bold;">Error: <?= e($error) ?></div>
<?php endif; ?>
<form method="post" style="margin:20px 0;">
    <?= csrf_field() ?>
    <button type="submit" style="background:#28a745;color:#fff;border:0;padding:10px 18px;border-radius:4px;font-size:15px;">Run Backfill</button>
</form>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
<div class="summary">
    <h2 style="margin-top:0;">Results</h2>
    <ul>
        <li>Pairs processed: <strong><?= (int)$pairsProcessed ?></strong></li>
        <li>New conversations created (first-time): <strong><?= (int)$conversationsCreated ?></strong></li>
        <li>Messages migrated this run: <strong><?= (int)$messagesUpdated ?></strong></li>
        <li>Total messages now with conversation_id: <strong><?= (int)$messagesPreMigrated ?></strong></li>
        <li>Legacy read messages with read_at set this run: <strong><?= (int)$readTimestampsSet ?></strong></li>
        <li>Participant last_read_at heuristically set/updated: <strong><?= (int)$participantReadsSet ?></strong></li>
        <li>Elapsed time: <?= number_format($elapsed, 3) ?>s</li>
    </ul>
</div>
<?php if ($detailRows): ?>
<table class="table">
    <thead><tr><th>#</th><th>User Pair</th><th>Conversation ID</th><th>Migrated (this run)</th><th>Total Pair Messages (legacy count)</th></tr></thead>
    <tbody>
    <?php $i=1; foreach ($detailRows as $r): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= e($r['pair']) ?></td>
            <td><?= (int)$r['conversation_id'] ?></td>
            <td><?= (int)$r['messages_migrated'] ?></td>
            <td><?= (int)$r['total_pair_messages'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php endif; ?>
<hr>
<p style="font-size:13px;color:#555;">After confirming successful migration and updating the UI to use conversations, you may remove this file.</p>
</body>
</html>
