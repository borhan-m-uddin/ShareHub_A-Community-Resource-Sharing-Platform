<?php
include_once 'bootstrap.php';
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include_once 'header.php'; ?>

    <div class="wrapper">
        <div class="card">
            <div class="card-body">
                <h2 style="margin-bottom:6px;">Welcome, <?php echo htmlspecialchars($_SESSION["username"] ?? ''); ?> 👋</h2>
                <p style="color:#6b7280;margin-bottom:0;">Role: <b><?php echo htmlspecialchars($_SESSION["role"] ?? ''); ?></b></p>
            </div>
        </div>

        <div style="margin-top:16px;" class="grid grid-auto">
            <a class="card" href="profile.php" style="text-decoration:none;">
                <div class="card-body"><b>👤 Manage Profile</b><p style="margin-top:6px;color:#6b7280;">Update your info and settings</p></div>
            </a>
            <a class="card" href="messages.php" style="text-decoration:none;">
                <div class="card-body"><b>💬 Messages</b><p style="margin-top:6px;color:#6b7280;">Conversations and notifications</p></div>
            </a>
            <a class="card" href="reviews.php" style="text-decoration:none;">
                <div class="card-body"><b>⭐ Reviews & Ratings</b><p style="margin-top:6px;color:#6b7280;">Your feedback and trust</p></div>
            </a>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "giver"): ?>
                <a class="card" href="manage_items.php" style="text-decoration:none;">
                    <div class="card-body"><b>📦 Manage Items</b><p style="margin-top:6px;color:#6b7280;">Add, edit, and track your items</p></div>
                </a>
                <a class="card" href="manage_services.php" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ Manage Services</b><p style="margin-top:6px;color:#6b7280;">Offer and update your services</p></div>
                </a>
                <a class="card" href="manage_requests.php" style="text-decoration:none;">
                    <div class="card-body"><b>📋 Incoming Requests</b><p style="margin-top:6px;color:#6b7280;">Approve or decline requests</p></div>
                </a>
                <a class="card" href="my_requests.php" style="text-decoration:none;">
                    <div class="card-body"><b>📝 My Requests</b><p style="margin-top:6px;color:#6b7280;">Requests you have made</p></div>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "seeker"): ?>
                <a class="card" href="view_items.php" style="text-decoration:none;">
                    <div class="card-body"><b>🛍️ Browse Items</b><p style="margin-top:6px;color:#6b7280;">Find items shared by neighbors</p></div>
                </a>
                <a class="card" href="view_services.php" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ Browse Services</b><p style="margin-top:6px;color:#6b7280;">Discover help and expertise</p></div>
                </a>
                <a class="card" href="my_requests.php" style="text-decoration:none;">
                    <div class="card-body"><b>📝 My Requests</b><p style="margin-top:6px;color:#6b7280;">Track your requests</p></div>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                <a class="card" href="admin_panel.php" style="text-decoration:none;">
                    <div class="card-body"><b>🛠️ Admin Panel</b><p style="margin-top:6px;color:#6b7280;">Platform settings and tools</p></div>
                </a>
                <a class="card" href="admin_users.php" style="text-decoration:none;">
                    <div class="card-body"><b>👥 Manage Users</b><p style="margin-top:6px;color:#6b7280;">Review accounts and roles</p></div>
                </a>
                <a class="card" href="admin_items.php" style="text-decoration:none;">
                    <div class="card-body"><b>📦 All Items</b><p style="margin-top:6px;color:#6b7280;">Moderate item listings</p></div>
                </a>
                <a class="card" href="admin_services.php" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ All Services</b><p style="margin-top:6px;color:#6b7280;">Moderate services</p></div>
                </a>
                <a class="card" href="admin_requests.php" style="text-decoration:none;">
                    <div class="card-body"><b>📋 All Requests</b><p style="margin-top:6px;color:#6b7280;">Review platform requests</p></div>
                </a>
            <?php endif; ?>
        </div>

        <div style="margin-top:18px; display:flex; gap:10px;">
            <a href="logout.php" class="btn btn-danger">🚪 Sign Out</a>
        </div>
    </div>

    </main>
</body>
</html>

