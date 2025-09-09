<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

// Get platform statistics
$stats = [
    'total_users' => 0,
    'users_by_role' => [],
    'total_items' => 0,
    'items_by_status' => [],
    'total_services' => 0,
    'total_requests' => 0,
    'requests_by_status' => [],
    'total_reviews' => 0,
    'average_rating' => 0.0,
];

if ($st = $conn->prepare("SELECT COUNT(*) as total FROM users")) {
    $st->execute(); $res = $st->get_result(); $stats['total_users'] = (int)($res->fetch_assoc()['total'] ?? 0); $res->free(); $st->close();
}

if ($st = $conn->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role")) {
    $st->execute(); if ($res = $st->get_result()) { while($row = $res->fetch_assoc()) { $stats['users_by_role'][$row['role']] = (int)$row['count']; } $res->free(); } $st->close();
}

if ($st = $conn->prepare("SELECT COUNT(*) as total FROM items")) {
    $st->execute(); $res = $st->get_result(); $stats['total_items'] = (int)($res->fetch_assoc()['total'] ?? 0); $res->free(); $st->close();
}

if ($st = $conn->prepare("SELECT availability_status, COUNT(*) as count FROM items GROUP BY availability_status")) {
    $st->execute(); if ($res = $st->get_result()) { while($row = $res->fetch_assoc()) { $stats['items_by_status'][$row['availability_status']] = (int)$row['count']; } $res->free(); } $st->close();
}

if ($st = $conn->prepare("SELECT COUNT(*) as total FROM services")) {
    $st->execute(); $res = $st->get_result(); $stats['total_services'] = (int)($res->fetch_assoc()['total'] ?? 0); $res->free(); $st->close();
}

if ($st = $conn->prepare("SELECT COUNT(*) as total FROM requests")) {
    $st->execute(); $res = $st->get_result(); $stats['total_requests'] = (int)($res->fetch_assoc()['total'] ?? 0); $res->free(); $st->close();
}

if ($st = $conn->prepare("SELECT status, COUNT(*) as count FROM requests GROUP BY status")) {
    $st->execute(); if ($res = $st->get_result()) { while($row = $res->fetch_assoc()) { $stats['requests_by_status'][$row['status']] = (int)$row['count']; } $res->free(); } $st->close();
}

if ($st = $conn->prepare("SELECT COUNT(*) as total FROM reviews")) {
    $st->execute(); $res = $st->get_result(); $stats['total_reviews'] = (int)($res->fetch_assoc()['total'] ?? 0); $res->free(); $st->close();
}

if ($st = $conn->prepare("SELECT AVG(rating) as avg_rating FROM reviews")) {
    $st->execute(); $res = $st->get_result(); $avg = (float)($res->fetch_assoc()['avg_rating'] ?? 0); $stats['average_rating'] = round($avg, 2); $res->free(); $st->close();
}

$recent_users = []; $recent_items = []; $recent_requests = [];
if ($st = $conn->prepare("SELECT user_id, username, email, role, registration_date FROM users ORDER BY registration_date DESC LIMIT 5")) {
    if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $recent_users[] = $r; } $res->free(); }
    $st->close();
}
if ($st = $conn->prepare("SELECT i.item_id, i.title, u.username as giver, i.posting_date FROM items i JOIN users u ON i.giver_id = u.user_id ORDER BY i.posting_date DESC LIMIT 5")) {
    if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $recent_items[] = $r; } $res->free(); }
    $st->close();
}
if ($st = $conn->prepare("SELECT r.request_id, r.status, u1.username as requester, u2.username as giver, r.request_date FROM requests r JOIN users u1 ON r.requester_id = u1.user_id LEFT JOIN users u2 ON r.giver_id = u2.user_id ORDER BY r.request_date DESC LIMIT 5")) {
    if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $recent_requests[] = $r; } $res->free(); }
    $st->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Community Resource Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    </head>
<body>
    <?php render_header(); ?>

    <div class="wrapper">
        <div class="card">
            <div class="card-body" style="text-align:center;">
                <h2>🛠️ Admin Control Panel</h2>
                <p class="muted">Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?> | Community Resource Platform Management</p>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <div class="grid grid-auto">
                    <a href="<?php echo site_href('admin/users.php'); ?>" class="btn btn-default">👥 Manage Users</a>
                    <a href="<?php echo site_href('admin/items.php'); ?>" class="btn btn-default">📦 Manage Items</a>
                    <a href="<?php echo site_href('admin/services.php'); ?>" class="btn btn-default">⚙️ Manage Services</a>
                    <a href="<?php echo site_href('admin/requests.php'); ?>" class="btn btn-default">📋 Manage Requests</a>
                    <a href="<?php echo site_href('admin/reviews.php'); ?>" class="btn btn-default">⭐ Manage Reviews</a>
                    <a href="<?php echo site_href('admin/messages.php'); ?>" class="btn btn-default">💬 Monitor Messages</a>
                    <a href="<?php echo site_href('admin/reports.php'); ?>" class="btn btn-default">📊 Reports & Analytics</a>
                    <a href="<?php echo site_href('admin/settings.php'); ?>" class="btn btn-default">⚙️ Platform Settings</a>
                </div>
            </div>
        </div>

        <!-- Stats and recent lists copied from original, unchanged -->
        <div class="grid grid-3" style="margin-top:16px;">
            <div class="card"><div class="card-body" style="text-align:center;"><div style="font-size:2rem; font-weight:800; color:var(--primary);"><?php echo $stats['total_users']; ?></div><div class="muted">Total Users</div><div style="margin-top:10px;"><?php if(isset($stats['users_by_role'])): foreach($stats['users_by_role'] as $role=>$count): ?><span class="badge badge-<?php echo $role; ?>"><?php echo ucfirst($role); ?>: <?php echo $count; ?></span> <?php endforeach; endif; ?></div></div></div>
            <div class="card"><div class="card-body" style="text-align:center;"><div style="font-size:2rem; font-weight:800; color:var(--primary);"><?php echo $stats['total_items']; ?></div><div class="muted">Total Items</div><div style="margin-top:10px;"><?php if(isset($stats['items_by_status'])): foreach($stats['items_by_status'] as $status=>$count): ?><span class="badge badge-<?php echo $status; ?>"><?php echo ucfirst($status); ?>: <?php echo $count; ?></span> <?php endforeach; endif; ?></div></div></div>
            <div class="card"><div class="card-body" style="text-align:center;"><div style="font-size:2rem; font-weight:800; color:var(--primary);"><?php echo $stats['total_services']; ?></div><div class="muted">Total Services</div></div></div>
            <div class="card"><div class="card-body" style="text-align:center;"><div style="font-size:2rem; font-weight:800; color:var(--primary);"><?php echo $stats['total_requests']; ?></div><div class="muted">Total Requests</div><div style="margin-top:10px;"><?php if(isset($stats['requests_by_status'])): foreach($stats['requests_by_status'] as $status=>$count): ?><span class="badge badge-<?php echo $status; ?>"><?php echo ucfirst($status); ?>: <?php echo $count; ?></span> <?php endforeach; endif; ?></div></div></div>
            <div class="card"><div class="card-body" style="text-align:center;"><div style="font-size:2rem; font-weight:800; color:var(--primary);"><?php echo $stats['total_reviews']; ?></div><div class="muted">Total Reviews</div><div style="margin-top:10px;"><span class="badge badge-available">Avg Rating: <?php echo $stats['average_rating']; ?>⭐</span></div></div></div>
        </div>

        <div class="card" style="margin-top:16px;"><div class="card-body"><div class="card-header" style="border-bottom:0; padding:0 0 10px 0; font-weight:800;">👥 Recent User Registrations</div>
            <?php foreach($recent_users as $user): ?>
            <div class="list-item" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border);">
                <div><strong><?php echo htmlspecialchars($user['username']); ?></strong> <span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span><br><small class="muted"><?php echo htmlspecialchars($user['email']); ?></small></div>
                <div class="muted"><?php echo date('M j, Y', strtotime($user['registration_date'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>

        <div class="card" style="margin-top:16px;"><div class="card-body"><div class="card-header" style="border-bottom:0; padding:0 0 10px 0; font-weight:800;">📦 Recent Items Posted</div>
            <?php foreach($recent_items as $item): ?>
            <div class="list-item" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border);">
                <div><strong><?php echo htmlspecialchars($item['title']); ?></strong><br><small class="muted">by <?php echo htmlspecialchars($item['giver']); ?></small></div>
                <div class="muted"><?php echo date('M j, Y', strtotime($item['posting_date'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>

        <div class="card" style="margin-top:16px;"><div class="card-body"><div class="card-header" style="border-bottom:0; padding:0 0 10px 0; font-weight:800;">📋 Recent Requests</div>
            <?php foreach($recent_requests as $request): ?>
            <div class="list-item" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border);">
                <div><strong>Request #<?php echo $request['request_id']; ?></strong> <span class="badge badge-<?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span><br><small class="muted">From: <?php echo htmlspecialchars($request['requester']); ?> <?php if($request['giver']): ?>to <?php echo htmlspecialchars($request['giver']); ?><?php endif; ?></small></div>
                <div class="muted"><?php echo date('M j, Y', strtotime($request['request_date'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div></div>

        <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary" style="margin-top:16px;">← Back to Dashboard</a>
    </div>
    <?php render_footer(); ?>
