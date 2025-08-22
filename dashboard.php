<?php
include_once 'bootstrap.php';
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}
include_once 'brand.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .dashboard-links a {
            display: block;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .dashboard-links a:hover {
            background-color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION["username"] ?? ''); ?>!</h2>
        <p>Your role: <b><?php echo htmlspecialchars($_SESSION["role"] ?? ''); ?></b></p>

        <div class="dashboard-links">
            <a href="profile.php">👤 Manage Profile</a>
            <a href="messages.php">💬 Messages</a>
            <a href="reviews.php">⭐ Reviews & Ratings</a>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "giver"): ?>
                <a href="manage_items.php">📦 Manage Items</a>
                <a href="manage_services.php">⚙️ Manage Services</a>
                <a href="manage_requests.php">📋 Manage Incoming Requests</a>
                <a href="my_requests.php">📝 My Requests</a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "seeker"): ?>
                <a href="view_items.php">🛍️ Browse Available Items</a>
                <a href="view_services.php">⚙️ Browse Available Services</a>
                <a href="my_requests.php">📝 My Requests</a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                <a href="admin_panel.php">🛠️ Admin Panel</a>
                <a href="admin_users.php">👥 Manage Users</a>
                <a href="admin_items.php">📦 Manage All Items</a>
                <a href="admin_services.php">⚙️ Manage All Services</a>
                <a href="admin_requests.php">📋 Manage All Requests</a>
            <?php endif; ?>

            <a href="logout.php" class="btn btn-danger">🚪 Sign Out of Your Account</a>
        </div>
    </div>
</body>
</html>

