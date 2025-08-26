<?php
include_once 'bootstrap.php';
// Check if the user is logged in, if not then redirect to login/home
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Lightweight KPIs for dashboard (no admin actions here)
$kpis = [
    'total_items' => 0,
    'total_services' => 0,
    'pending_requests' => 0,
];

// Only run counts if DB connection exists
if (isset($conn) && $conn instanceof mysqli) {
    // Items count
    if ($res = $conn->query("SELECT COUNT(*) AS c FROM items")) { $kpis['total_items'] = (int)($res->fetch_assoc()['c'] ?? 0); $res->free(); }
    // Services count
    if ($res = $conn->query("SELECT COUNT(*) AS c FROM services")) { $kpis['total_services'] = (int)($res->fetch_assoc()['c'] ?? 0); $res->free(); }
    // Pending requests count
    if ($res = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE status = 'pending'")) { $kpis['pending_requests'] = (int)($res->fetch_assoc()['c'] ?? 0); $res->free(); }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>

    <div class="wrapper">
        <div class="card">
            <div class="card-body">
                <h2 style="margin-bottom:6px;">Welcome, <?php echo htmlspecialchars($_SESSION["username"] ?? ''); ?> 👋</h2>
                <p style="color:#6b7280;margin-bottom:0;">Role: <b><?php echo htmlspecialchars($_SESSION["role"] ?? ''); ?></b></p>
            </div>
        </div>

        <div style="margin-top:16px;" class="grid grid-auto">
            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                <!-- Read-only KPIs for admins; links go to Admin Panel pages for actions -->
                <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Items</div><div style="font-size:1.3rem; font-weight:800; color:var(--primary);"><?php echo $kpis['total_items']; ?></div></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Services</div><div style="font-size:1.3rem; font-weight:800; color:var(--primary);"><?php echo $kpis['total_services']; ?></div></div></div>
                <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Pending Requests</div><div class="status-pending" style="font-size:1.3rem;">&nbsp;<?php echo $kpis['pending_requests']; ?></div></div></div>
            <?php endif; ?>
            <a class="card" href="<?php echo site_href('profile.php'); ?>" style="text-decoration:none;">
                <div class="card-body"><b>👤 Manage Profile</b><p style="margin-top:6px;color:#6b7280;">Update your info and settings</p></div>
            </a>
            <a class="card" href="<?php echo site_href('messages.php'); ?>" style="text-decoration:none;">
                <div class="card-body"><b>💬 Messages</b><p style="margin-top:6px;color:#6b7280;">Conversations and notifications</p></div>
            </a>
            <a class="card" href="<?php echo site_href('reviews.php'); ?>" style="text-decoration:none;">
                <div class="card-body"><b>⭐ Reviews & Ratings</b><p style="margin-top:6px;color:#6b7280;">Your feedback and trust</p></div>
            </a>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "giver"): ?>
                <a class="card" href="<?php echo site_href('manage_items.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📦 Manage Items</b><p style="margin-top:6px;color:#6b7280;">Add, edit, and track your items</p></div>
                </a>
                <a class="card" href="<?php echo site_href('manage_services.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ Manage Services</b><p style="margin-top:6px;color:#6b7280;">Offer and update your services</p></div>
                </a>
                <a class="card" href="<?php echo site_href('manage_requests.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📋 Incoming Requests</b><p style="margin-top:6px;color:#6b7280;">Approve or decline requests</p></div>
                </a>
                <a class="card" href="<?php echo site_href('my_requests.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📝 My Requests</b><p style="margin-top:6px;color:#6b7280;">Requests you have made</p></div>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "seeker"): ?>
                <a class="card" href="<?php echo site_href('view_items.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>🛍️ Browse Items</b><p style="margin-top:6px;color:#6b7280;">Find items shared by neighbors</p></div>
                </a>
                <a class="card" href="<?php echo site_href('view_services.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ Browse Services</b><p style="margin-top:6px;color:#6b7280;">Discover help and expertise</p></div>
                </a>
                <a class="card" href="<?php echo site_href('my_requests.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📝 My Requests</b><p style="margin-top:6px;color:#6b7280;">Track your requests</p></div>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                <!-- No admin management cards here to avoid duplication; use Admin Panel as single entry point -->
                <a class="card" href="<?php echo site_href('admin/panel.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>�️ Admin Panel</b><p style="margin-top:6px;color:#6b7280;">Go to Admin Panel</p></div>
                </a>
            <?php endif; ?>
        </div>

        <div style="margin-top:18px; display:flex; gap:10px;">
            <a href="<?php echo site_href('logout.php'); ?>" class="btn btn-danger">🚪 Sign Out</a>
        </div>
    </div>

    </main>
</body>
</html>

