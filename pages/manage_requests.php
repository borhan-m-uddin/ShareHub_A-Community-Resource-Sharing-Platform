<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (($_SESSION['role'] ?? '') !== 'giver' && ($_SESSION['role'] ?? '') !== 'admin')) {
    header('Location: ' . site_href('pages/dashboard.php'));
    exit;
}

// Build incoming requests list (joining titles)
$incoming_requests = [];
if (function_exists('db_connected') && db_connected()) {
    global $conn; $uid = (int)$_SESSION['user_id'];
    $sql = "SELECT r.request_id,r.request_type,r.item_id,r.service_id,r.message,r.status,r.request_date," .
        "u.username AS seeker_username,u.email AS seeker_email," .
        "CASE WHEN r.request_type='item' THEN i.title WHEN r.request_type='service' THEN s.title END AS resource_title " .
        "FROM requests r " .
        "JOIN users u ON r.requester_id=u.user_id " .
        "LEFT JOIN items i ON r.request_type='item' AND r.item_id=i.item_id " .
        "LEFT JOIN services s ON r.request_type='service' AND r.service_id=s.service_id " .
        "WHERE (r.request_type='item' AND i.giver_id=?) OR (r.request_type='service' AND s.giver_id=?) " .
        "ORDER BY r.request_date DESC";
    if ($st = $conn->prepare($sql)) { $st->bind_param('ii',$uid,$uid); if($st->execute()){ $res=$st->get_result(); while($r=$res->fetch_assoc()){ $incoming_requests[]=$r; } if($res){$res->free();} } $st->close(); }
}

// Handle request status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) { header('Location: ' . site_href('pages/manage_requests.php')); exit; }
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action = ($_POST['action'] ?? '') === 'approve' ? 'approve' : 'decline';
    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    $uid = (int)$_SESSION['user_id']; $allowed = false; if (function_exists('db_connected') && db_connected()) { global $conn; $chkSql = "SELECT 1 FROM requests r LEFT JOIN items i ON r.request_type='item' AND r.item_id=i.item_id LEFT JOIN services s ON r.request_type='service' AND r.service_id=s.service_id WHERE r.request_id=? AND ((r.request_type='item' AND i.giver_id=?) OR (r.request_type='service' AND s.giver_id=?)) LIMIT 1"; if($st=$conn->prepare($chkSql)){ $st->bind_param('iii',$request_id,$uid,$uid); if($st->execute()){ $row=$st->get_result()->fetch_row(); $allowed=(bool)$row; } $st->close(); } }
    if ($allowed) {
        if (request_update_status($request_id, $new_status)) {
            if ($new_status === 'approved' && function_exists('db_connected') && db_connected()) {
                global $conn; if ($st2=$conn->prepare("UPDATE items i JOIN requests r ON r.item_id=i.item_id AND r.request_type='item' SET i.availability_status='pending' WHERE r.request_id=?")) { $st2->bind_param('i',$request_id); $st2->execute(); $st2->close(); }
            }
        }
    header('Location: ' . site_href('pages/manage_requests.php')); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <div class="page-top-actions">
    <a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">← Back to Dashboard</a>
    </div>
    <h2>📨 Manage Incoming Requests</h2>
    <p>Review and respond to requests for your items and services.</p>
    <?php if (!empty($incoming_requests)): ?>
        <div class="table-wrap">
        <table class="request-table">
            <thead>
                <tr>
                    <th>Resource</th><th>Type</th><th>Message</th><th>Seeker</th><th>Seeker Email</th><th>Status</th><th>Request Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($incoming_requests as $request): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($request['resource_title'] ?: 'Unknown'); ?></strong></td>
                    <td><span class="badge badge-<?php echo $request['request_type']==='item'?'primary':'info'; ?>"><?php echo htmlspecialchars(ucfirst($request['request_type'] ?: '')); ?></span></td>
                    <td><?php echo htmlspecialchars($request['message'] ?: 'No message'); ?></td>
                    <td><?php echo htmlspecialchars($request['seeker_username'] ?: 'Unknown'); ?></td>
                    <td><a href="mailto:<?php echo htmlspecialchars($request['seeker_email'] ?: ''); ?>"><?php echo htmlspecialchars($request['seeker_email'] ?: 'No email'); ?></a></td>
                    <td class="status-<?php echo strtolower($request['status'] ?: 'pending'); ?>">
                        <?php $status_icons=['pending'=>'⏳','approved'=>'✅','rejected'=>'❌','completed'=>'🎯']; $status=strtolower($request['status'] ?: 'pending'); echo ($status_icons[$status]??'ℹ️') . ' ' . htmlspecialchars(ucfirst($status)); ?>
                    </td>
                    <td><?php echo date('M j, Y g:i A', strtotime($request['request_date'])); ?></td>
                    <td class="action-buttons">
                        <?php if (($request['status'] ?: 'pending') == 'pending'): ?>
                            <form action="" method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="submit" class="btn btn-primary" value="✅ Approve">
                            </form>
                            <form action="" method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <input type="submit" class="btn btn-danger" value="❌ Decline">
                            </form>
                        <?php else: ?><em>No actions available</em><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <div class="alert-info">
            <h4>📭 No Incoming Requests</h4>
            <p>You don't have any requests for your items or services at the moment.</p>
            <p>Make sure your items and services are active and properly listed!</p>
        </div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
</body>
</html>