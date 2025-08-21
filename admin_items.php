<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: login.php");
    exit;
}

require_once "config.php";

$all_items = [];

// Fetch all items from the system
$sql = "SELECT i.item_id, i.title, i.description, i.category, i.condition_status, i.availability_status, 
               i.posting_date, i.pickup_location, u.username, u.email 
        FROM items i 
        JOIN users u ON i.giver_id = u.user_id 
        ORDER BY i.posting_date DESC";

if($stmt = $conn->prepare($sql)){
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $all_items[] = $row;
        }
        $result->free();
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    $stmt->close();
}

// Handle item status update or deletion
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST["delete_item_id"])){
        $item_id = $_POST["delete_item_id"];
        
        // Delete related requests first
        $delete_requests_sql = "DELETE FROM requests WHERE item_id = ?";
        if($stmt = $conn->prepare($delete_requests_sql)){
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete the item
        $delete_sql = "DELETE FROM items WHERE item_id = ?";
        if($stmt = $conn->prepare($delete_sql)){
            $stmt->bind_param("i", $item_id);
            if($stmt->execute()){
                header("location: admin_items.php");
                exit;
            }
            $stmt->close();
        }
    } elseif(isset($_POST["item_id"]) && isset($_POST["new_status"])){
        $item_id = $_POST["item_id"];
        $new_status = $_POST["new_status"];
        
        $update_sql = "UPDATE items SET availability_status = ? WHERE item_id = ?";
        if($stmt = $conn->prepare($update_sql)){
            $stmt->bind_param("si", $new_status, $item_id);
            if($stmt->execute()){
                header("location: admin_items.php");
                exit;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage All Items - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .items-table th, .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .status-available { color: #28a745; font-weight: bold; }
        .status-unavailable { color: #dc3545; font-weight: bold; }
        .status-pending { color: #ff8c00; font-weight: bold; }
        .action-buttons form { display: inline-block; margin-right: 5px; }
        .action-buttons .btn { padding: 4px 8px; font-size: 0.8em; }
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
        <h2>📦 Admin - Manage All Items</h2>
        <p>Administrative overview of all items in the system.</p>
        <p>
            <a href="admin_panel.php" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </p>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <?php
            $stats = ['available' => 0, 'unavailable' => 0, 'pending' => 0, 'total' => count($all_items)];
            $categories = [];
            foreach($all_items as $item) {
                if(isset($stats[$item['availability_status']])) {
                    $stats[$item['availability_status']]++;
                }
                $category = $item['category'] ?: 'Uncategorized';
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
            ?>
            <div class="stat-card">
                <h4>Total Items</h4>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Available Items</h4>
                <div class="number" style="color: #28a745;"><?php echo $stats['available']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Pending Items</h4>
                <div class="number" style="color: #ff8c00;"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Unavailable Items</h4>
                <div class="number" style="color: #dc3545;"><?php echo $stats['unavailable']; ?></div>
            </div>
        </div>

        <?php if (!empty($all_items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Giver</th>
                        <th>Posted</th>
                        <th>Location</th>
                        <th>Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_items as $item): ?>
                        <tr>
                            <td><?php echo $item["item_id"]; ?></td>
                            <td><strong><?php echo htmlspecialchars($item["title"]); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($item["description"], 0, 80)); ?><?php echo strlen($item["description"]) > 80 ? '...' : ''; ?></td>
                            <td><?php echo htmlspecialchars($item["category"] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($item["condition_status"])); ?></td>
                            <td class="status-<?php echo $item["availability_status"]; ?>"><?php echo htmlspecialchars(ucfirst($item["availability_status"])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($item["username"]); ?><br>
                                <small><?php echo htmlspecialchars($item["email"]); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($item["posting_date"])); ?></td>
                            <td><?php echo htmlspecialchars($item["pickup_location"] ?: 'Not specified'); ?></td>
                            <td class="action-buttons">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="margin-bottom: 3px;">
                                    <input type="hidden" name="item_id" value="<?php echo $item["item_id"]; ?>">
                                    <select name="new_status" onchange="this.form.submit()" class="form-control" style="font-size: 0.8em;">
                                        <option value="">Change Status</option>
                                        <option value="available" <?php echo ($item["availability_status"] == "available") ? "selected" : ""; ?>>Available</option>
                                        <option value="pending" <?php echo ($item["availability_status"] == "pending") ? "selected" : ""; ?>>Pending</option>
                                        <option value="unavailable" <?php echo ($item["availability_status"] == "unavailable") ? "selected" : ""; ?>>Unavailable</option>
                                    </select>
                                </form>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" 
                                      onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                    <input type="hidden" name="delete_item_id" value="<?php echo $item["item_id"]; ?>">
                                    <input type="submit" class="btn btn-danger" value="🗑️ Delete">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!empty($categories)): ?>
                <div class="mt-4">
                    <h4>Items by Category</h4>
                    <div class="stats-cards">
                        <?php foreach($categories as $category => $count): ?>
                            <div class="stat-card">
                                <h4><?php echo htmlspecialchars($category); ?></h4>
                                <div class="number"><?php echo $count; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <strong>No items found</strong><br>
                There are no items in the system yet.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
