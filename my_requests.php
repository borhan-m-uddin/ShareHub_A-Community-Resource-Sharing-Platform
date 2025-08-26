<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
$requests = [];

$sql = "SELECT r.request_id, r.request_type, r.item_id, r.service_id, r.message, r.status, r.request_date, ";
$sql .= "CASE WHEN r.request_type = 'item' THEN i.title WHEN r.request_type = 'service' THEN s.title END as resource_title, ";
$sql .= "CASE WHEN r.request_type = 'item' THEN u_item.username WHEN r.request_type = 'service' THEN u_service.username END as giver_username ";
$sql .= "FROM requests r ";
$sql .= "LEFT JOIN items i ON r.request_type = 'item' AND r.item_id = i.item_id ";
$sql .= "LEFT JOIN services s ON r.request_type = 'service' AND r.service_id = s.service_id ";
$sql .= "LEFT JOIN users u_item ON r.request_type = 'item' AND i.giver_id = u_item.user_id ";
$sql .= "LEFT JOIN users u_service ON r.request_type = 'service' AND s.giver_id = u_service.user_id ";
$sql .= "WHERE r.requester_id = ? ORDER BY r.request_date DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $requests[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Requests</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>📋 My Requests</h2>
        <p>View the status of your requests for items and services.</p>
        <p>
            <a href="dashboard.php" class="btn btn-default">← Back to Dashboard</a>
            <a href="view_items.php" class="btn btn-default">Browse Items</a>
            <a href="view_services.php" class="btn btn-default">Browse Services</a>
        </p>

        <?php if (!empty($requests)): ?>
            <table class="request-table">
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
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request["resource_title"] ?: 'Unknown'); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $request["request_type"] == 'item' ? 'primary' : 'info'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($request["request_type"] ?: '')); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($request["message"] ?: 'No message'); ?></td>
                            <td><?php echo htmlspecialchars($request["giver_username"] ?: 'Unknown'); ?></td>
                            <td class="status-<?php echo strtolower($request["status"] ?: 'pending'); ?>">
                                <?php 
                                $status_icons = ['pending' => '⏳', 'approved' => '✅', 'declined' => '❌', 'completed' => '🎯'];
                                $status = $request["status"] ?: 'pending';
                                echo $status_icons[$status] . ' ' . htmlspecialchars(ucfirst($status)); 
                                ?>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($request["request_date"])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
        <div class="alert alert-info">
                <h4>📭 No Requests Yet</h4>
                <p>You haven't made any requests yet.</p>
                <p>
            <a href="<?php echo site_href('view_items.php'); ?>" class="btn btn-default">Browse Items</a>
            <a href="<?php echo site_href('view_services.php'); ?>" class="btn btn-default">Browse Services</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

