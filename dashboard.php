<?php
include_once 'bootstrap.php';
// Check if the user is logged in, if not then redirect to login/home
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Redirect seekers to the unified feed, dashboard is for givers/admin
if (($_SESSION['role'] ?? '') === 'seeker') {
    header('Location: ' . site_href('seeker_feed.php'));
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
        if ($st = $conn->prepare("SELECT COUNT(*) AS c FROM items")) { $st->execute(); $res = $st->get_result(); $kpis['total_items'] = (int)($res->fetch_assoc()['c'] ?? 0); $res->free(); $st->close(); }
    // Services count
        if ($st = $conn->prepare("SELECT COUNT(*) AS c FROM services")) { $st->execute(); $res = $st->get_result(); $kpis['total_services'] = (int)($res->fetch_assoc()['c'] ?? 0); $res->free(); $st->close(); }
    // Pending requests count
        if ($st = $conn->prepare("SELECT COUNT(*) AS c FROM requests WHERE status = 'pending'")) { $st->execute(); $res = $st->get_result(); $kpis['pending_requests'] = (int)($res->fetch_assoc()['c'] ?? 0); $res->free(); $st->close(); }
}

// Role-aware data buckets
$role = $_SESSION['role'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);

$giver_items = [];
$giver_incoming = [];

$seeker_items = [];
$seeker_services = [];
$seeker_my_requests = [];

$admin_pending = [];

if (isset($conn) && $conn instanceof mysqli) {
    if ($role === 'giver') {
        // Recent items posted by this giver
        if ($st = $conn->prepare("SELECT item_id, title, availability_status, posting_date FROM items WHERE giver_id = ? ORDER BY posting_date DESC LIMIT 5")) {
            $st->bind_param('i', $uid);
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $giver_items[]=$r; } $res->free(); }
            $st->close();
        }
        // Incoming pending requests to this giver (items or services)
        $sql = "SELECT r.request_id, r.request_type, r.item_id, r.service_id, r.message, r.status, r.request_date, "
             . "CASE WHEN r.request_type='item' THEN i.title WHEN r.request_type='service' THEN s.title END as resource_title, "
             . "u.username AS seeker_username "
             . "FROM requests r "
             . "JOIN users u ON r.requester_id = u.user_id "
             . "LEFT JOIN items i ON r.request_type='item' AND r.item_id = i.item_id "
             . "LEFT JOIN services s ON r.request_type='service' AND r.service_id = s.service_id "
             . "WHERE r.giver_id = ? AND r.status='pending' "
             . "ORDER BY r.request_date DESC LIMIT 5";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('i', $uid);
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $giver_incoming[]=$r; } $res->free(); }
            $st->close();
        }
    } elseif ($role === 'seeker') {
        // Recommended/latest available items & services
        if ($st = $conn->prepare("SELECT item_id, title, category, posting_date FROM items WHERE availability_status='available' ORDER BY posting_date DESC LIMIT 6")) {
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $seeker_items[]=$r; } $res->free(); }
            $st->close();
        }
        if ($st = $conn->prepare("SELECT service_id, title, category, posting_date FROM services WHERE availability='available' ORDER BY posting_date DESC LIMIT 6")) {
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $seeker_services[]=$r; } $res->free(); }
            $st->close();
        }
        // My recent requests
        $sql = "SELECT r.request_id, r.request_type, r.status, r.request_date, "
             . "CASE WHEN r.request_type='item' THEN i.title WHEN r.request_type='service' THEN s.title END as resource_title "
             . "FROM requests r "
             . "LEFT JOIN items i ON r.request_type='item' AND r.item_id=i.item_id "
             . "LEFT JOIN services s ON r.request_type='service' AND r.service_id=s.service_id "
             . "WHERE r.requester_id=? ORDER BY r.request_date DESC LIMIT 5";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('i', $uid);
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $seeker_my_requests[]=$r; } $res->free(); }
            $st->close();
        }
    } elseif ($role === 'admin') {
        // Platform-wide pending requests snapshot
        $sql = "SELECT r.request_id, r.request_type, r.status, r.request_date, "
             . "u1.username as requester, u2.username as giver "
             . "FROM requests r "
             . "JOIN users u1 ON r.requester_id = u1.user_id "
             . "LEFT JOIN users u2 ON r.giver_id = u2.user_id "
             . "WHERE r.status='pending' ORDER BY r.request_date DESC LIMIT 5";
        if ($st = $conn->prepare($sql)) {
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $admin_pending[]=$r; } $res->free(); }
            $st->close();
        }
    }
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
                <p class="muted" style="margin-bottom:0;">Role: <b><?php echo htmlspecialchars($_SESSION["role"] ?? ''); ?></b></p>
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
                <div class="card-body"><b>👤 Manage Profile</b><p class="muted" style="margin-top:6px;">Update your info and settings</p></div>
            </a>
            <a class="card" href="<?php echo site_href('conversations.php'); ?>" style="text-decoration:none;">
                <div class="card-body"><b>💬 Messages</b><p class="muted" style="margin-top:6px;">Conversations and notifications</p></div>
            </a>
            <a class="card" href="<?php echo site_href('reviews.php'); ?>" style="text-decoration:none;">
                <div class="card-body"><b>⭐ Reviews & Ratings</b><p class="muted" style="margin-top:6px;">Your feedback and trust</p></div>
            </a>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "giver"): ?>
                <a class="card" href="<?php echo site_href('add_item.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>➕ Add Item</b><p class="muted" style="margin-top:6px;">Share something new</p></div>
                </a>
                <a class="card" href="<?php echo site_href('add_service.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>➕ Offer Service</b><p class="muted" style="margin-top:6px;">List a skill or help</p></div>
                </a>
                <a class="card" href="<?php echo site_href('manage_items.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📦 Manage Items</b><p class="muted" style="margin-top:6px;">Add, edit, and track your items</p></div>
                </a>
                <a class="card" href="<?php echo site_href('manage_services.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ Manage Services</b><p class="muted" style="margin-top:6px;">Offer and update your services</p></div>
                </a>
                <a class="card" href="<?php echo site_href('manage_requests.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📋 Incoming Requests</b><p class="muted" style="margin-top:6px;">Approve or decline requests</p></div>
                </a>
                <a class="card" href="<?php echo site_href('my_requests.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📝 My Requests</b><p class="muted" style="margin-top:6px;">Requests you have made</p></div>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "seeker"): ?>
                <a class="card" href="<?php echo site_href('seeker_feed.php?tab=items'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>🛍️ Browse Items</b><p class="muted" style="margin-top:6px;">Find items shared by neighbors</p></div>
                </a>
                <a class="card" href="<?php echo site_href('seeker_feed.php?tab=services'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>⚙️ Browse Services</b><p class="muted" style="margin-top:6px;">Discover help and expertise</p></div>
                </a>
                <a class="card" href="<?php echo site_href('my_requests.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>📝 My Requests</b><p class="muted" style="margin-top:6px;">Track your requests</p></div>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin"): ?>
                <!-- No admin management cards here to avoid duplication; use Admin Panel as single entry point -->
                <a class="card" href="<?php echo site_href('admin/panel.php'); ?>" style="text-decoration:none;">
                    <div class="card-body"><b>�️ Admin Panel</b><p style="margin-top:6px;color:#6b7280;">Go to Admin Panel</p></div>
                </a>
            <?php endif; ?>
        </div>

        <!-- Role-specific snapshots -->
        <?php if ($role === 'giver'): ?>
            <div class="grid grid-2" style="margin-top:18px; gap:16px;">
                <div class="card"><div class="card-body">
                    <div class="card-header" style="border-bottom:0; padding:0 0 8px 0; font-weight:800;">📦 Your Recent Items</div>
                    <?php if ($giver_items): foreach($giver_items as $it): ?>
                        <div class="list-item" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border);">
                            <div><strong><?php echo htmlspecialchars($it['title']); ?></strong><br><small class="muted">Status: <?php echo htmlspecialchars(ucfirst($it['availability_status'])); ?></small></div>
                            <div class="muted"><?php echo date('M j, Y', strtotime($it['posting_date'])); ?></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="muted">No items yet. <a href="<?php echo site_href('add_item.php'); ?>">Add your first item →</a></div>
                    <?php endif; ?>
                </div></div>
                <div class="card"><div class="card-body">
                    <div class="card-header" style="border-bottom:0; padding:0 0 8px 0; font-weight:800;">📨 Incoming Requests</div>
                    <?php if ($giver_incoming): foreach($giver_incoming as $rq): ?>
                        <div class="list-item" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border);">
                            <div><strong><?php echo htmlspecialchars($rq['resource_title'] ?: 'Unknown'); ?></strong> <span class="badge badge-<?php echo $rq['request_type']==='item'?'primary':'info'; ?>"><?php echo ucfirst($rq['request_type']); ?></span><br><small class="muted">from <?php echo htmlspecialchars($rq['seeker_username']); ?></small></div>
                            <div class="muted"><?php echo date('M j, Y', strtotime($rq['request_date'])); ?></div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="muted">No pending requests. When someone requests your item or service, it shows up here.</div>
                    <?php endif; ?>
                    <div style="margin-top:10px;"><a href="<?php echo site_href('manage_requests.php'); ?>" class="btn btn-default">Manage Requests →</a></div>
                </div></div>
            </div>
        <?php elseif ($role === 'seeker'): ?>
            <div class="card" style="margin-top:18px;"><div class="card-body">
                <div class="card-header" style="border-bottom:0; padding:0 0 8px 0; font-weight:800;">🛍️ Latest Items</div>
                <?php if ($seeker_items): ?>
                    <div class="grid grid-auto">
                        <?php foreach($seeker_items as $it): ?>
                            <div class="card"><div class="card-body">
                                <div><strong><?php echo htmlspecialchars($it['title']); ?></strong></div>
                                <div class="muted"><?php echo htmlspecialchars($it['category']); ?> • <?php echo date('M j', strtotime($it['posting_date'])); ?></div>
                            </div></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="muted">No items available right now.</div>
                <?php endif; ?>
                <div style="margin-top:10px; display:flex; gap:8px;"><a href="<?php echo site_href('seeker_feed.php?tab=items'); ?>" class="btn btn-default">Browse Items →</a></div>
            </div></div>
            <div class="card" style="margin-top:16px;"><div class="card-body">
                <div class="card-header" style="border-bottom:0; padding:0 0 8px 0; font-weight:800;">⚙️ Latest Services</div>
                <?php if ($seeker_services): ?>
                    <div class="grid grid-auto">
                        <?php foreach($seeker_services as $sv): ?>
                            <div class="card"><div class="card-body">
                                <div><strong><?php echo htmlspecialchars($sv['title']); ?></strong></div>
                                <div class="muted"><?php echo htmlspecialchars($sv['category']); ?> • <?php echo date('M j', strtotime($sv['posting_date'])); ?></div>
                            </div></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="muted">No services available right now.</div>
                <?php endif; ?>
                <div style="margin-top:10px; display:flex; gap:8px;"><a href="<?php echo site_href('seeker_feed.php?tab=services'); ?>" class="btn btn-default">Browse Services →</a></div>
            </div></div>
            <div class="card" style="margin-top:16px;"><div class="card-body">
                <div class="card-header" style="border-bottom:0; padding:0 0 8px 0; font-weight:800;">📝 Your Recent Requests</div>
                <?php if ($seeker_my_requests): foreach($seeker_my_requests as $rq): ?>
                    <div class="list-item" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border);">
                        <div><strong><?php echo htmlspecialchars($rq['resource_title'] ?: 'Unknown'); ?></strong> <span class="badge badge-<?php echo $rq['request_type']==='item'?'primary':'info'; ?>"><?php echo ucfirst($rq['request_type']); ?></span><br><small class="muted">Status: <?php echo htmlspecialchars(ucfirst($rq['status'])); ?></small></div>
                        <div class="muted"><?php echo date('M j, Y', strtotime($rq['request_date'])); ?></div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="muted">You haven't made any requests yet.</div>
                <?php endif; ?>
                <div style="margin-top:10px;"><a href="<?php echo site_href('my_requests.php'); ?>" class="btn btn-default">View All Requests →</a></div>
            </div></div>
        <?php elseif ($role === 'admin'): ?>
            <div class="card" style="margin-top:18px;"><div class="card-body">
                <div class="card-header" style="border-bottom:0; padding:0 0 8px 0; font-weight:800;">⏳ Pending Requests (recent)</div>
                <?php if ($admin_pending): foreach($admin_pending as $rq): ?>
                    <div class="list-item" style="display:flex; justify-content:space-between; border-bottom:1px solid var(--border);">
                        <div><strong>#<?php echo (int)$rq['request_id']; ?></strong> <span class="badge badge-<?php echo $rq['request_type']==='item'?'primary':'info'; ?>"><?php echo ucfirst($rq['request_type']); ?></span><br><small class="muted">From: <?php echo htmlspecialchars($rq['requester']); ?><?php if(!empty($rq['giver'])): ?> → <?php echo htmlspecialchars($rq['giver']); ?><?php endif; ?></small></div>
                        <div class="muted"><?php echo date('M j, Y', strtotime($rq['request_date'])); ?></div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="muted">No pending requests 🎉</div>
                <?php endif; ?>
                <div style="margin-top:10px;"><a href="<?php echo site_href('admin/requests.php'); ?>" class="btn btn-default">Manage Requests →</a></div>
            </div></div>
        <?php endif; ?>

        <div style="margin-top:18px; display:flex; gap:10px;">
            <a href="<?php echo site_href('logout.php'); ?>" class="btn btn-danger">🚪 Sign Out</a>
        </div>
    </div>

    </main>
</body>
</html>

