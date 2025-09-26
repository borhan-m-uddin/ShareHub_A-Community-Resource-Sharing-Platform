<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$requests = [];
$sql = "SELECT r.request_id, r.request_type, r.item_id, r.service_id, r.message, r.status, r.request_date, " .
    "CASE WHEN r.request_type = 'item' THEN i.title WHEN r.request_type = 'service' THEN s.title END as resource_title, " .
    "CASE WHEN r.request_type = 'item' THEN u_item.username WHEN r.request_type = 'service' THEN u_service.username END as giver_username " .
    "FROM requests r " .
    "LEFT JOIN items i ON r.request_type = 'item' AND r.item_id = i.item_id " .
    "LEFT JOIN services s ON r.request_type = 'service' AND r.service_id = s.service_id " .
    "LEFT JOIN users u_item ON r.request_type = 'item' AND i.giver_id = u_item.user_id " .
    "LEFT JOIN users u_service ON r.request_type = 'service' AND s.giver_id = u_service.user_id " .
    "WHERE r.requester_id = ? ORDER BY r.request_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $_SESSION['user_id']);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $requests[] = $row;
        }
        $res->free();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Requests</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <div class="page-top-actions">
            <a href="<?php echo site_href('pages/seeker_feed.php'); ?>" class="btn btn-default">← Back to Feed</a>
            <a href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>" class="btn btn-default">Browse Items</a>
            <a href="<?php echo site_href('pages/seeker_feed.php?tab=services'); ?>" class="btn btn-default">Browse Services</a>
        </div>
        <h2>📋 My Requests</h2>
        <p>View the status of your requests for items and services.</p>
        <?php if ($requests): ?>
            <div class="table-wrap">
                <table class="request-table">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Giver</th>
                            <th>Status</th>
                            <th>Request Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): $status = strtolower($r['status'] ?? 'pending'); ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['resource_title'] ?: 'Unknown'); ?></strong></td>
                                <td><span class="badge badge-<?php echo $r['request_type'] === 'item' ? 'primary' : 'info'; ?>"><?php echo htmlspecialchars(ucfirst($r['request_type'] ?: '')); ?></span></td>
                                <td><?php echo htmlspecialchars($r['message'] ?: 'No message'); ?></td>
                                <td><?php echo htmlspecialchars($r['giver_username'] ?: 'Unknown'); ?></td>
                                <td class="status-<?php echo $status; ?>">
                                    <?php $icons = ['pending' => '⏳', 'approved' => '✅', 'rejected' => '❌', 'completed' => '🎯'];
                                    echo ($icons[$status] ?? 'ℹ️') . ' ' . htmlspecialchars(ucfirst($status)); ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($r['request_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <h4>📭 No Requests Yet</h4>
                <p>You haven't made any requests yet.</p>
                <p>
                    <a href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>" class="btn btn-default">Browse Items</a>
                    <a href="<?php echo site_href('pages/seeker_feed.php?tab=services'); ?>" class="btn btn-default">Browse Services</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php render_footer(); ?>
</body>

</html>