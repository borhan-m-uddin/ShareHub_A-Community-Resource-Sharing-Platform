<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: login.php");
    exit;
}

require_once "config.php";

$all_requests = [];

// Fetch all requests from the system
$sql = "SELECT r.request_id, r.request_type, r.item_id, r.service_id, r.message, r.status, r.request_date, r.response_date, ";
$sql .= "u_requester.username as requester_username, u_requester.email as requester_email, ";
$sql .= "u_giver.username as giver_username, u_giver.email as giver_email, ";
$sql .= "CASE ";
$sql .= "    WHEN r.request_type = 'item' THEN i.title ";
$sql .= "    WHEN r.request_type = 'service' THEN s.title ";
$sql .= "END as resource_title ";
$sql .= "FROM requests r ";
$sql .= "JOIN users u_requester ON r.requester_id = u_requester.user_id ";
$sql .= "LEFT JOIN users u_giver ON r.giver_id = u_giver.user_id ";
$sql .= "LEFT JOIN items i ON r.request_type = 'item' AND r.item_id = i.item_id ";
$sql .= "LEFT JOIN services s ON r.request_type = 'service' AND r.service_id = s.service_id ";
$sql .= "ORDER BY r.request_date DESC";

if($stmt = $conn->prepare($sql)){
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $all_requests[] = $row;
        }
        $result->free();
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    $stmt->close();
}

// Handle request status update (admin can override)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["request_id"]) && isset($_POST["action"])){
    $request_id = $_POST["request_id"];
    $action = $_POST["action"];

    $new_status = '';
    switch($action) {
        case 'approve':
            $new_status = 'approved';
            break;
        case 'reject':
            $new_status = 'rejected';
            break;
        case 'complete':
            $new_status = 'completed';
            break;
        case 'reset':
            $new_status = 'pending';
            break;
    }

    if ($new_status) {
        $response_date = ($new_status != 'pending') ? date('Y-m-d H:i:s') : null;
        
        $sql_update = "UPDATE requests SET status = ?, response_date = ? WHERE request_id = ?";
        if($stmt_update = $conn->prepare($sql_update)){
            $stmt_update->bind_param("ssi", $new_status, $response_date, $request_id);
            if($stmt_update->execute()){
                header("location: admin_requests.php");
                exit;
            } else{
                echo "Error updating request status.";
            }
            $stmt_update->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage All Requests - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .request-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .request-table th, .request-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        .request-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .status-pending { color: #ff8c00; font-weight: bold; }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .status-completed { color: #007bff; font-weight: bold; }
        .action-buttons form { display: inline-block; margin-right: 3px; }
        .action-buttons .btn { padding: 3px 8px; font-size: 0.75em; }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        .stat-card h4 { margin: 0 0 5px 0; color: #333; }
        .stat-card .number { font-size: 1.5em; font-weight: bold; color: #007cba; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>🛠️ Admin - Manage All Requests</h2>
        <p>Administrative overview of all system requests.</p>
        <p>
            <a href="admin_panel.php" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </p>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <?php
            $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
            foreach($all_requests as $req) {
                if(isset($stats[$req['status']])) {
                    $stats[$req['status']]++;
                }
            }
            ?>
            <div class="stat-card">
                <h4>Pending Requests</h4>
                <div class="number" style="color: #ff8c00;"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Approved Requests</h4>
                <div class="number" style="color: #28a745;"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Rejected Requests</h4>
                <div class="number" style="color: #dc3545;"><?php echo $stats['rejected']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Completed Requests</h4>
                <div class="number" style="color: #007bff;"><?php echo $stats['completed']; ?></div>
            </div>
        </div>

        <?php if (!empty($all_requests)): ?>
            <table class="request-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Resource</th>
                        <th>Type</th>
                        <th>Requester</th>
                        <th>Giver</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Request Date</th>
                        <th>Response Date</th>
                        <th>Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_requests as $request): ?>
                        <tr>
                            <td><?php echo $request["request_id"]; ?></td>
                            <td><?php echo htmlspecialchars($request["resource_title"] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($request["request_type"])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($request["requester_username"]); ?><br>
                                <small><?php echo htmlspecialchars($request["requester_email"]); ?></small>
                            </td>
                            <td>
                                <?php if ($request["giver_username"]): ?>
                                    <?php echo htmlspecialchars($request["giver_username"]); ?><br>
                                    <small><?php echo htmlspecialchars($request["giver_email"]); ?></small>
                                <?php else: ?>
                                    <em>Not assigned</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(substr($request["message"] ?: 'No message', 0, 50)); ?><?php echo strlen($request["message"]) > 50 ? '...' : ''; ?></td>
                            <td class="status-<?php echo strtolower($request["status"]); ?>"><?php echo htmlspecialchars(ucfirst($request["status"])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($request["request_date"])); ?></td>
                            <td><?php echo $request["response_date"] ? date('M j, Y', strtotime($request["response_date"])) : '-'; ?></td>
                            <td class="action-buttons">
                                <?php if ($request["status"] == "pending"): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="submit" class="btn btn-success" value="✓ Approve">
                                    </form>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="submit" class="btn btn-danger" value="✗ Reject">
                                    </form>
                                <?php elseif ($request["status"] == "approved"): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <input type="submit" class="btn btn-info" value="✓ Complete">
                                    </form>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="reset">
                                        <input type="submit" class="btn btn-warning" value="↺ Reset">
                                    </form>
                                <?php else: ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                        <input type="hidden" name="request_id" value="<?php echo $request["request_id"]; ?>">
                                        <input type="hidden" name="action" value="reset">
                                        <input type="submit" class="btn btn-warning" value="↺ Reset">
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <strong>No requests found</strong><br>
                There are no requests in the system yet.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
