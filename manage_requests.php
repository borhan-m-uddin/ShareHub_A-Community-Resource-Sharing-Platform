<?php
include_once 'bootstrap.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["role"] ?? '') !== "giver") {
    header("location: login.php");
    exit;
}

$incoming_requests = [];

$sql = "SELECT r.request_id, r.request_type, r.item_id, r.service_id, r.message, r.status, r.request_date, ";
$sql .= "u_seeker.username as seeker_username, u_seeker.email as seeker_email, ";
$sql .= "CASE ";
$sql .= "    WHEN r.request_type = 'item' THEN i.title ";
$sql .= "    WHEN r.request_type = 'service' THEN s.title ";
$sql .= "END as resource_title ";
$sql .= "FROM requests r ";
$sql .= "JOIN users u_seeker ON r.requester_id = u_seeker.user_id ";
$sql .= "LEFT JOIN items i ON r.request_type = 'item' AND r.item_id = i.item_id ";
$sql .= "LEFT JOIN services s ON r.request_type = 'service' AND r.service_id = s.service_id ";
$sql .= "WHERE (i.giver_id = ? AND r.request_type = 'item') OR (s.giver_id = ? AND r.request_type = 'service') ";
$sql .= "ORDER BY r.request_date DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("ii", $_SESSION["user_id"], $_SESSION["user_id"]);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $incoming_requests[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

// Handle request status update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["request_id"], $_POST["action"])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'] === 'approve' ? 'approve' : 'decline';
    $new_status = $action === 'approve' ? 'approved' : 'rejected'; // enum uses 'rejected'

    // Update only if the request belongs to this giver (ownership check via JOIN)
    $sql_update = "UPDATE requests r
                   LEFT JOIN items i ON r.request_type='item' AND r.item_id=i.item_id
                   LEFT JOIN services s ON r.request_type='service' AND r.service_id=s.service_id
                   SET r.status = ?
                   WHERE r.request_id = ?
                     AND ((r.request_type='item' AND i.giver_id = ?) OR (r.request_type='service' AND s.giver_id = ?))";
    if ($stmt_update = $conn->prepare($sql_update)) {
        $uid = (int)$_SESSION['user_id'];
        $stmt_update->bind_param("siii", $new_status, $request_id, $uid, $uid);
        if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
            if ($new_status === 'approved') {
                // Set item availability to pending if it's an item request
                $sql_update_item = "UPDATE items i
                                    JOIN requests r ON r.item_id = i.item_id AND r.request_type='item'
                                    SET i.availability_status='pending'
                                    WHERE r.request_id = ?";
                if ($stmt_update_item = $conn->prepare($sql_update_item)) {
                    $stmt_update_item->bind_param("i", $request_id);
                    $stmt_update_item->execute();
                    $stmt_update_item->close();
                }
            }
            header("location: manage_requests.php");
            exit;
        }
        $stmt_update->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Requests</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include_once 'header.php'; ?>
    <div class="wrapper">
        <h2>📨 Manage Incoming Requests</h2>
        <p>Review and respond to requests for your items and services.</p>
        <p><a href="dashboard.php" class="btn btn-default">← Back to Dashboard</a></p>

        <?php if (!empty($incoming_requests)): ?>
            <table class="request-table">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Seeker</th>
                        <th>Seeker Email</th>
                        <th>Status</th>
                        <th>Request Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incoming_requests as $request): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request["resource_title"] ?: 'Unknown'); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $request["request_type"] == 'item' ? 'primary' : 'info'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($request["request_type"] ?: '')); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($request["message"] ?: 'No message'); ?></td>
                            <td><?php echo htmlspecialchars($request["seeker_username"] ?: 'Unknown'); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($request["seeker_email"] ?: ''); ?>"><?php echo htmlspecialchars($request["seeker_email"] ?: 'No email'); ?></a></td>
                            <td class="status-<?php echo strtolower($request["status"] ?: 'pending'); ?>">
                                <?php 
                                $status_icons = ['pending' => '⏳', 'approved' => '✅', 'declined' => '❌', 'completed' => '🎯'];
                                $status = $request["status"] ?: 'pending';
                                echo $status_icons[$status] . ' ' . htmlspecialchars(ucfirst($status)); 
                                ?>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($request["request_date"])); ?></td>
                            <td class="action-buttons">
                                <?php if (($request["status"] ?: 'pending') == "pending"): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="submit" class="btn btn-primary" value="✅ Approve">
                                    </form>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <input type="submit" class="btn btn-danger" value="❌ Decline">
                                    </form>
                                <?php else: ?>
                                    <em>No actions available</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert-info">
                <h4>📭 No Incoming Requests</h4>
                <p>You don't have any requests for your items or services at the moment.</p>
                <p>Make sure your items and services are active and properly listed!</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

