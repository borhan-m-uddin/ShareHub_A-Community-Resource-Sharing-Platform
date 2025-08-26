<?php require_once __DIR__ . '/../bootstrap.php';
require_admin();

// Filters & pagination
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1) * $perPage;

$clauses=[];$params=[];$types='';
if ($q !== '') {
    if (ctype_digit($q)) { $clauses[]='m.message_id=?'; $params[]=(int)$q; $types.='i'; }
    $clauses[]='(m.subject LIKE ? OR m.message_content LIKE ? OR su.username LIKE ? OR ru.username LIKE ?)';
    $like='%'.$q.'%'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s';
}
if ($from !== '') { $clauses[]='DATE(m.sent_date) >= ?'; $params[]=$from; $types.='s'; }
if ($to !== '') { $clauses[]='DATE(m.sent_date) <= ?'; $params[]=$to; $types.='s'; }
$where = $clauses ? ('WHERE '.implode(' AND ',$clauses)) : '';

$messages=[]; $total=0;
$sql = "SELECT m.message_id, m.subject, m.message_content, m.sent_date, m.is_read, m.request_id,
               su.username AS sender, ru.username AS receiver
        FROM messages m
        JOIN users su ON m.sender_id = su.user_id
        JOIN users ru ON m.receiver_id = ru.user_id
        $where
        ORDER BY m.sent_date DESC
        LIMIT $perPage OFFSET $offset";
if($st=$conn->prepare($sql)){
    if($types!==''){ $st->bind_param($types,...$params);} $st->execute(); $r=$st->get_result();
    while($row=$r->fetch_assoc()){ $messages[]=$row; }
    $r->free(); $st->close();
}
$sqlc = "SELECT COUNT(*) c FROM messages m JOIN users su ON m.sender_id=su.user_id JOIN users ru ON m.receiver_id=ru.user_id $where";
if($st=$conn->prepare($sqlc)){
    if($types!==''){ $st->bind_param($types,...$params);} $st->execute(); $r=$st->get_result(); $row=$r->fetch_assoc(); $total=(int)($row['c']??0); $r->free(); $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Messages</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>💬 Admin - Messages</h2>
        <p>
            <a class="btn btn-primary" href="<?php echo site_href('admin/panel.php'); ?>">← Back</a>
            <a class="btn btn-secondary" href="<?php echo site_href('dashboard.php'); ?>">Dashboard</a>
        </p>

        <form method="get" class="filter-bar" style="margin-bottom:12px;">
            <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search subject/content/sender/receiver or ID" />
            <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
            <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
            <button class="btn btn-default" type="submit">Filter</button>
        </form>

        <div class="muted" style="margin-bottom:8px;">Showing <?php echo count($messages); ?> of <?php echo (int)$total; ?><?php if($total>$perPage): ?> | Page <?php echo $page; ?><?php endif; ?></div>

        <?php if(!empty($messages)): ?>
            <table class="table">
                <thead><tr>
                    <th>ID</th><th>Subject</th><th>Sender</th><th>Receiver</th><th>Sent</th><th>Request</th>
                </tr></thead>
                <tbody>
                <?php foreach($messages as $m): ?>
                    <tr>
                        <td><?php echo (int)$m['message_id']; ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($m['subject'] ?: '(no subject)', 0, 60, '...')); ?></td>
                        <td><?php echo htmlspecialchars($m['sender']); ?></td>
                        <td><?php echo htmlspecialchars($m['receiver']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($m['sent_date'])); ?></td>
                        <td><?php echo $m['request_id'] ? ('#'.(int)$m['request_id']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total > $perPage): ?>
            <div style="margin-top:10px;display:flex;gap:8px;">
                <?php if ($page>1): ?><a class="btn btn-default" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>">Prev</a><?php endif; ?>
                <?php if ($offset + count($messages) < $total): ?><a class="btn btn-default" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>">Next</a><?php endif; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><h3>No messages found</h3></div>
        <?php endif; ?>
    </div>
    <?php render_footer(); ?>