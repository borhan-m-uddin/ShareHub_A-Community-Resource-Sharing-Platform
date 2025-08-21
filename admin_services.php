<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: login.php");
    exit;
}

require_once "config.php";

$all_services = [];

// Fetch all services from the system
$sql = "SELECT s.service_id, s.title, s.description, s.category, s.availability, 
               s.posting_date, s.location, u.username, u.email 
        FROM services s 
        JOIN users u ON s.giver_id = u.user_id 
        ORDER BY s.posting_date DESC";

if($stmt = $conn->prepare($sql)){
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $all_services[] = $row;
        }
        $result->free();
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    $stmt->close();
}

// Handle service status update or deletion
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST["delete_service_id"])){
        $service_id = $_POST["delete_service_id"];
        
        // Delete related requests first
        $delete_requests_sql = "DELETE FROM requests WHERE service_id = ?";
        if($stmt = $conn->prepare($delete_requests_sql)){
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete the service
        $delete_sql = "DELETE FROM services WHERE service_id = ?";
        if($stmt = $conn->prepare($delete_sql)){
            $stmt->bind_param("i", $service_id);
            if($stmt->execute()){
                header("location: admin_services.php");
                exit;
            }
            $stmt->close();
        }
    } elseif(isset($_POST["service_id"]) && isset($_POST["new_availability"])){
        $service_id = $_POST["service_id"];
        $new_availability = $_POST["new_availability"];
        
        $update_sql = "UPDATE services SET availability = ? WHERE service_id = ?";
        if($stmt = $conn->prepare($update_sql)){
            $stmt->bind_param("si", $new_availability, $service_id);
            if($stmt->execute()){
                header("location: admin_services.php");
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
    <title>Manage All Services - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .services-table th, .services-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .services-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .status-available { color: #28a745; font-weight: bold; }
        .status-unavailable { color: #dc3545; font-weight: bold; }
        .status-busy { color: #ff8c00; font-weight: bold; }
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
        <h2>🛠️ Admin - Manage All Services</h2>
        <p>Administrative overview of all services in the system.</p>
        <p>
            <a href="admin_panel.php" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </p>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <?php
            $stats = ['available' => 0, 'unavailable' => 0, 'busy' => 0, 'total' => count($all_services)];
            $categories = [];
            foreach($all_services as $service) {
                if(isset($stats[$service['availability']])) {
                    $stats[$service['availability']]++;
                }
                $category = $service['category'] ?: 'Uncategorized';
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
            ?>
            <div class="stat-card">
                <h4>Total Services</h4>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Available Services</h4>
                <div class="number" style="color: #28a745;"><?php echo $stats['available']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Busy Services</h4>
                <div class="number" style="color: #ff8c00;"><?php echo $stats['busy']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Unavailable Services</h4>
                <div class="number" style="color: #dc3545;"><?php echo $stats['unavailable']; ?></div>
            </div>
        </div>

        <?php if (!empty($all_services)): ?>
            <table class="services-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Availability</th>
                        <th>Provider</th>
                        <th>Posted</th>
                        <th>Location</th>
                        <th>Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_services as $service): ?>
                        <tr>
                            <td><?php echo $service["service_id"]; ?></td>
                            <td><strong><?php echo htmlspecialchars($service["title"]); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($service["description"], 0, 80)); ?><?php echo strlen($service["description"]) > 80 ? '...' : ''; ?></td>
                            <td><?php echo htmlspecialchars($service["category"] ?: 'N/A'); ?></td>
                            <td class="status-<?php echo $service["availability"]; ?>"><?php echo htmlspecialchars(ucfirst($service["availability"])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($service["username"]); ?><br>
                                <small><?php echo htmlspecialchars($service["email"]); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($service["posting_date"])); ?></td>
                            <td><?php echo htmlspecialchars($service["location"] ?: 'Not specified'); ?></td>
                            <td class="action-buttons">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="margin-bottom: 3px;">
                                    <input type="hidden" name="service_id" value="<?php echo $service["service_id"]; ?>">
                                    <select name="new_availability" onchange="this.form.submit()" class="form-control" style="font-size: 0.8em;">
                                        <option value="">Change Status</option>
                                        <option value="available" <?php echo ($service["availability"] == "available") ? "selected" : ""; ?>>Available</option>
                                        <option value="busy" <?php echo ($service["availability"] == "busy") ? "selected" : ""; ?>>Busy</option>
                                        <option value="unavailable" <?php echo ($service["availability"] == "unavailable") ? "selected" : ""; ?>>Unavailable</option>
                                    </select>
                                </form>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" 
                                      onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.');">
                                    <input type="hidden" name="delete_service_id" value="<?php echo $service["service_id"]; ?>">
                                    <input type="submit" class="btn btn-danger" value="🗑️ Delete">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!empty($categories)): ?>
                <div class="mt-4">
                    <h4>Services by Category</h4>
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
                <strong>No services found</strong><br>
                There are no services in the system yet.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
