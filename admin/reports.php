<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();
// Compute datasets
// ---- Filters: from/to date, role, category ----
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$is_valid_date = function($s){ return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); };
if ($from && !$is_valid_date($from)) { $from = ''; }
if ($to && !$is_valid_date($to)) { $to = ''; }
if (!in_array($role, ['', 'admin','giver','seeker'], true)) { $role = ''; }

// Gather distinct categories for the filter dropdown
$all_categories = [];
if ($res = $conn->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category <> '' UNION SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category <> '' ORDER BY category")) {
    while ($r = $res->fetch_row()) { $all_categories[] = $r[0]; }
    $res->free();
}

// Helper to construct date conditions for a date column
$date_cond = function($col) use ($from, $to) {
    $parts = [];
    if ($from) { $parts[] = "DATE($col) >= '" . $from . "'"; }
    if ($to) { $parts[] = "DATE($col) <= '" . $to . "'"; }
    return $parts ? (' AND ' . implode(' AND ', $parts)) : '';
};

// Build filter query string for links (export, etc.)
$filter_qs = http_build_query(array_filter([
    'from' => $from ?: null,
    'to' => $to ?: null,
    'role' => $role ?: null,
    'category' => $category ?: null,
]));

// ---- CSV Export ----
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isset($_GET['type'])) {
    $type = $_GET['type'];
    // Avoid leaking warnings/notices into CSV output
    @ini_set('display_errors', '0');
    @error_reporting(E_ERROR | E_PARSE);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_'. $type .'.csv"');
    $out = fopen('php://output', 'w');
    $limit = 1000;

    switch ($type) {
        case 'users':
            $cond = 'WHERE 1=1';
            if ($role) { $cond .= " AND role='".$conn->real_escape_string($role)."'"; }
            $cond .= $date_cond('registration_date');
            $sql = "SELECT user_id, username, role, status, registration_date FROM users $cond ORDER BY registration_date DESC LIMIT $limit";
            fputcsv($out, ['user_id','username','role','status','registration_date'], ',', '"', '\\', "\r\n");
            break;
        case 'items':
            $cond = 'WHERE 1=1';
            if ($category) { $cond .= " AND category='".$conn->real_escape_string($category)."'"; }
            $cond .= $date_cond('posting_date');
            $sql = "SELECT item_id, title, category, availability_status, posting_date FROM items $cond ORDER BY posting_date DESC LIMIT $limit";
            fputcsv($out, ['item_id','title','category','availability_status','posting_date'], ',', '"', '\\', "\r\n");
            break;
        case 'services':
            $cond = 'WHERE 1=1';
            if ($category) { $cond .= " AND category='".$conn->real_escape_string($category)."'"; }
            $cond .= $date_cond('posting_date');
            $sql = "SELECT service_id, title, category, availability, posting_date FROM services $cond ORDER BY posting_date DESC LIMIT $limit";
            fputcsv($out, ['service_id','title','category','availability','posting_date'], ',', '"', '\\', "\r\n");
            break;
        case 'requests':
            $cond = 'WHERE 1=1';
            $cond .= $date_cond('request_date');
            $sql = "SELECT request_id, request_type, status, request_date FROM requests $cond ORDER BY request_date DESC LIMIT $limit";
            fputcsv($out, ['request_id','request_type','status','request_date'], ',', '"', '\\', "\r\n");
            break;
        case 'reviews':
            $cond = 'WHERE 1=1';
            $cond .= $date_cond('review_date');
            $sql = "SELECT review_id, rating, review_date FROM reviews $cond ORDER BY review_date DESC LIMIT $limit";
            fputcsv($out, ['review_id','rating','review_date'], ',', '"', '\\', "\r\n");
            break;
        case 'messages':
            $cond = 'WHERE 1=1';
            $cond .= $date_cond('sent_date');
            $sql = "SELECT message_id, sender_id, receiver_id, is_read, sent_date FROM messages $cond ORDER BY sent_date DESC LIMIT $limit";
            fputcsv($out, ['message_id','sender_id','receiver_id','is_read','sent_date'], ',', '"', '\\', "\r\n");
            break;
        default:
            $sql = '';
    }

    if ($sql) {
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                switch ($type) {
                    case 'users':
                        fputcsv($out, [
                            $row['user_id'], $row['username'], $row['role'], $row['status'], $row['registration_date']
                        ], ',', '"', '\\', "\r\n");
                        break;
                    case 'items':
                        fputcsv($out, [
                            $row['item_id'], $row['title'], $row['category'], $row['availability_status'], $row['posting_date']
                        ], ',', '"', '\\', "\r\n");
                        break;
                    case 'services':
                        fputcsv($out, [
                            $row['service_id'], $row['title'], $row['category'], $row['availability'], $row['posting_date']
                        ], ',', '"', '\\', "\r\n");
                        break;
                    case 'requests':
                        fputcsv($out, [
                            $row['request_id'], $row['request_type'], $row['status'], $row['request_date']
                        ], ',', '"', '\\', "\r\n");
                        break;
                    case 'reviews':
                        fputcsv($out, [
                            $row['review_id'], $row['rating'], $row['review_date']
                        ], ',', '"', '\\', "\r\n");
                        break;
                    case 'messages':
                        fputcsv($out, [
                            $row['message_id'], $row['sender_id'], $row['receiver_id'], $row['is_read'], $row['sent_date']
                        ], ',', '"', '\\', "\r\n");
                        break;
                }
            }
            $res->free();
        }
    }
    fclose($out);
    exit;
}


// Users statistics
$users = ['total'=>0,'admin'=>0,'giver'=>0,'seeker'=>0,'active'=>0,'new_today'=>0,'new_week'=>0,'new_month'=>0];
$cond = 'WHERE 1=1';
if ($role) { $cond .= " AND role='".$conn->real_escape_string($role)."'"; }
$cond .= $date_cond('registration_date');
$sql = "SELECT COUNT(*) total,
               SUM(role='admin') admin,
               SUM(role='giver') giver,
               SUM(role='seeker') seeker,
               SUM(status=1) active,
               SUM(DATE(registration_date)=CURDATE()) new_today,
               SUM(DATE(registration_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) new_week,
               SUM(DATE(registration_date)>=DATE_SUB(CURDATE(), INTERVAL 30 DAY)) new_month
        FROM users $cond";
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    foreach ($users as $k => $v) { if (isset($row[$k])) $users[$k] = (int)$row[$k]; }
    $res->free();
}

// Items statistics
$items = ['total'=>0,'available'=>0,'pending'=>0,'unavailable'=>0,'today'=>0,'week'=>0];
$cond = 'WHERE 1=1';
if ($category) { $cond .= " AND category='".$conn->real_escape_string($category)."'"; }
$cond .= $date_cond('posting_date');
$sql = "SELECT COUNT(*) total,
               SUM(availability_status='available') available,
               SUM(availability_status='pending') pending,
               SUM(availability_status='unavailable') unavailable,
               SUM(DATE(posting_date)=CURDATE()) today,
               SUM(DATE(posting_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) week
        FROM items $cond";
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    foreach ($items as $k => $v) { if (isset($row[$k])) $items[$k] = (int)$row[$k]; }
    $res->free();
}

// Services statistics
$services = ['total'=>0,'available'=>0,'busy'=>0,'unavailable'=>0,'today'=>0,'week'=>0];
$cond = 'WHERE 1=1';
if ($category) { $cond .= " AND category='".$conn->real_escape_string($category)."'"; }
$cond .= $date_cond('posting_date');
$sql = "SELECT COUNT(*) total,
               SUM(availability='available') available,
               SUM(availability='busy') busy,
               SUM(availability='unavailable') unavailable,
               SUM(DATE(posting_date)=CURDATE()) today,
               SUM(DATE(posting_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) week
        FROM services $cond";
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    // busy may be null if column doesn't exist; keep default 0
    foreach ($services as $k => $v) { if (isset($row[$k])) $services[$k] = (int)$row[$k]; }
    $res->free();
}

// Requests statistics
$requests = ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'completed'=>0,'today'=>0,'week'=>0];
$cond = 'WHERE 1=1';
$cond .= $date_cond('request_date');
$sql = "SELECT COUNT(*) total,
               SUM(status='pending') pending,
               SUM(status='approved') approved,
               SUM(status='rejected') rejected,
               SUM(status='completed') completed,
               SUM(DATE(request_date)=CURDATE()) today,
               SUM(DATE(request_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) week
        FROM requests $cond";
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    foreach ($requests as $k => $v) { if (isset($row[$k])) $requests[$k] = (int)$row[$k]; }
    $res->free();
}

// Reviews statistics
$reviews = ['total'=>0,'avg'=>0,'week'=>0,'one'=>0,'two'=>0,'three'=>0,'four'=>0,'five'=>0];
$cond = 'WHERE 1=1';
$cond .= $date_cond('review_date');
$sql = "SELECT COUNT(*) total,
               COALESCE(AVG(rating),0) avg,
               SUM(DATE(review_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) week,
               SUM(rating=1) one,
               SUM(rating=2) two,
               SUM(rating=3) three,
               SUM(rating=4) four,
               SUM(rating=5) five
        FROM reviews $cond";
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    if ($row) {
        $reviews['total'] = (int)$row['total'];
        $reviews['avg'] = (float)$row['avg'];
        $reviews['week'] = (int)$row['week'];
        $reviews['one'] = (int)$row['one'];
        $reviews['two'] = (int)$row['two'];
        $reviews['three'] = (int)$row['three'];
        $reviews['four'] = (int)$row['four'];
        $reviews['five'] = (int)$row['five'];
    }
    $res->free();
}

// Messages statistics
$messages = ['total'=>0,'unread'=>0,'today'=>0,'week'=>0];
$cond = 'WHERE 1=1';
$cond .= $date_cond('sent_date');
$sql = "SELECT COUNT(*) total,
               SUM(is_read=0) unread,
               SUM(DATE(sent_date)=CURDATE()) today,
               SUM(DATE(sent_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) week
        FROM messages $cond";
if ($res = $conn->query($sql)) {
    $row = $res->fetch_assoc();
    foreach ($messages as $k => $v) { if (isset($row[$k])) $messages[$k] = (int)$row[$k]; }
    $res->free();
}

// Popular categories
$item_categories = [];
if ($res = $conn->query("SELECT category, COUNT(*) cnt FROM items WHERE category IS NOT NULL AND category <> '' GROUP BY category ORDER BY cnt DESC LIMIT 10")) {
    while ($row = $res->fetch_assoc()) { $item_categories[] = $row; }
    $res->free();
}
$service_categories = [];
if ($res = $conn->query("SELECT category, COUNT(*) cnt FROM services WHERE category IS NOT NULL AND category <> '' GROUP BY category ORDER BY cnt DESC LIMIT 10")) {
    while ($row = $res->fetch_assoc()) { $service_categories[] = $row; }
    $res->free();
}

// Most active users
$active_users = [];
$sql = "SELECT u.username, u.role,
    (SELECT COUNT(*) FROM items WHERE giver_id=u.user_id) items_shared,
    (SELECT COUNT(*) FROM services WHERE giver_id=u.user_id) services_shared,
    (SELECT COUNT(*) FROM requests WHERE requester_id=u.user_id) requests_made,
    (SELECT COUNT(*) FROM reviews WHERE reviewer_id=u.user_id) reviews_given
    FROM users u WHERE u.role <> 'admin'";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $row['score'] = (int)$row['items_shared'] + (int)$row['services_shared'] + (int)$row['requests_made'] + (int)$row['reviews_given'];
        $active_users[] = $row;
    }
    usort($active_users, function($a, $b) { return $b['score'] <=> $a['score']; });
    $active_users = array_slice($active_users, 0, 8);
    $res->free();
}

// Recent activity (last 7 days)
$recent = [];
$u_cond = "WHERE DATE(registration_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
if ($role) { $u_cond .= " AND role='".$conn->real_escape_string($role)."'"; }
$i_cond = "WHERE DATE(posting_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
if ($category) { $i_cond .= " AND category='".$conn->real_escape_string($category)."'"; }
$s_cond = $i_cond;
$r_cond = "WHERE DATE(request_date)>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$sql = "SELECT t,a,d FROM (SELECT 'user' AS t, username AS a, registration_date AS d FROM users $u_cond ORDER BY registration_date DESC LIMIT 10) u ";
$sql .= "UNION ALL SELECT t,a,d FROM (SELECT 'item' AS t, title AS a, posting_date AS d FROM items $i_cond ORDER BY posting_date DESC LIMIT 10) i ";
$sql .= "UNION ALL SELECT t,a,d FROM (SELECT 'service' AS t, title AS a, posting_date AS d FROM services $s_cond ORDER BY posting_date DESC LIMIT 10) s ";
$sql .= "UNION ALL SELECT t,a,d FROM (SELECT 'request' AS t, CAST(request_id AS CHAR) AS a, request_date AS d FROM requests $r_cond ORDER BY request_date DESC LIMIT 10) r ";
$sql .= "ORDER BY d DESC LIMIT 20";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) { $recent[] = $row; }
    $res->free();
}
?>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        .stat-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);} .stat-row:last-child{border-bottom:0}
        .rating-bar{height:12px;background:var(--muted-bg,#e9ecef);border-radius:8px;overflow:hidden}
        .rating-fill{height:100%;background:#ffc107}
    </style>
    </head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <h2>📊 Admin - Reports & Analytics</h2>
    <p>
        <a class="btn btn-primary" href="<?php echo site_href('admin/panel.php'); ?>">← Back to Admin Panel</a>
        <a class="btn btn-secondary" href="<?php echo site_href('dashboard.php'); ?>">Dashboard</a>
    </p>

    <!-- Filters -->
    <div class="card" style="margin-bottom:12px;">
        <div class="card-body">
        <form method="get" class="grid" style="grid-template-columns: repeat(5, minmax(0, 1fr)); gap:10px; align-items:end;">
            <div class="form-group">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label for="role">User Role</label>
                <select id="role" name="role" class="form-control">
                    <option value="">All</option>
                    <option value="admin" <?php echo $role==='admin'?'selected':''; ?>>Admin</option>
                    <option value="giver" <?php echo $role==='giver'?'selected':''; ?>>Giver</option>
                    <option value="seeker" <?php echo $role==='seeker'?'selected':''; ?>>Seeker</option>
                </select>
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">All</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category===$cat?'selected':''; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?php echo site_href('admin/reports.php'); ?>" class="btn btn-default">Reset</a>
            </div>
        </form>
        </div>
    </div>

    <!-- Export Buttons -->
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
        <?php $base = site_href('admin/reports.php'); $qs = $filter_qs ? ($filter_qs . '&') : ''; ?>
        <a class="btn btn-default" href="<?php echo $base.'?'.$qs; ?>export=csv&type=users">Export Users CSV</a>
        <a class="btn btn-default" href="<?php echo $base.'?'.$qs; ?>export=csv&type=items">Export Items CSV</a>
        <a class="btn btn-default" href="<?php echo $base.'?'.$qs; ?>export=csv&type=services">Export Services CSV</a>
        <a class="btn btn-default" href="<?php echo $base.'?'.$qs; ?>export=csv&type=requests">Export Requests CSV</a>
        <a class="btn btn-default" href="<?php echo $base.'?'.$qs; ?>export=csv&type=reviews">Export Reviews CSV</a>
        <a class="btn btn-default" href="<?php echo $base.'?'.$qs; ?>export=csv&type=messages">Export Messages CSV</a>
    </div>

    <!-- KPI cards -->
    <div class="grid grid-auto" style="margin-top:12px;">
    <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Users</div><div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo (int)$users['total']; ?></div></div></div>
    <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Items</div><div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo (int)$items['total']; ?></div></div></div>
    <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Services</div><div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo (int)$services['total']; ?></div></div></div>
    <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Requests</div><div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo (int)$requests['total']; ?></div></div></div>
    <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;">Total Reviews</div><div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo (int)$reviews['total']; ?></div></div></div>
    </div>

    <!-- Detailed Reports -->
    <div class="reports-grid grid grid-3" style="margin-top:16px; gap:16px;">
        <div class="report-card card"><div class="card-body"><h3>👥 Users</h3>
            <div class="stat-row"><span>Total</span><span><?php echo (int)$users['total']; ?></span></div>
            <div class="stat-row"><span>Active</span><span><?php echo (int)$users['active']; ?></span></div>
            <div class="stat-row"><span>Admins</span><span><?php echo (int)$users['admin']; ?></span></div>
            <div class="stat-row"><span>Givers</span><span><?php echo (int)$users['giver']; ?></span></div>
            <div class="stat-row"><span>Seekers</span><span><?php echo (int)$users['seeker']; ?></span></div>
            <div class="stat-row"><span>New Today</span><span><?php echo (int)$users['new_today']; ?></span></div>
            <div class="stat-row"><span>New This Week</span><span><?php echo (int)$users['new_week']; ?></span></div>
            <div class="stat-row"><span>New This Month</span><span><?php echo (int)$users['new_month']; ?></span></div>
        </div></div>

        <div class="report-card card"><div class="card-body"><h3>📦 Items</h3>
            <div class="stat-row"><span>Total</span><span><?php echo (int)$items['total']; ?></span></div>
            <div class="stat-row"><span>Available</span><span><?php echo (int)$items['available']; ?></span></div>
            <div class="stat-row"><span>Pending</span><span><?php echo (int)$items['pending']; ?></span></div>
            <div class="stat-row"><span>Unavailable</span><span><?php echo (int)$items['unavailable']; ?></span></div>
            <div class="stat-row"><span>Posted Today</span><span><?php echo (int)$items['today']; ?></span></div>
            <div class="stat-row"><span>Posted This Week</span><span><?php echo (int)$items['week']; ?></span></div>
        </div></div>

        <div class="report-card card"><div class="card-body"><h3>⚙️ Services</h3>
            <div class="stat-row"><span>Total</span><span><?php echo (int)$services['total']; ?></span></div>
            <div class="stat-row"><span>Available</span><span><?php echo (int)$services['available']; ?></span></div>
            <div class="stat-row"><span>Busy</span><span><?php echo (int)$services['busy']; ?></span></div>
            <div class="stat-row"><span>Unavailable</span><span><?php echo (int)$services['unavailable']; ?></span></div>
            <div class="stat-row"><span>Posted Today</span><span><?php echo (int)$services['today']; ?></span></div>
            <div class="stat-row"><span>Posted This Week</span><span><?php echo (int)$services['week']; ?></span></div>
        </div></div>

        <div class="report-card card"><div class="card-body"><h3>📋 Requests</h3>
            <div class="stat-row"><span>Total</span><span><?php echo (int)$requests['total']; ?></span></div>
            <div class="stat-row"><span>Pending</span><span><?php echo (int)$requests['pending']; ?></span></div>
            <div class="stat-row"><span>Approved</span><span><?php echo (int)$requests['approved']; ?></span></div>
            <div class="stat-row"><span>Rejected</span><span><?php echo (int)$requests['rejected']; ?></span></div>
            <div class="stat-row"><span>Completed</span><span><?php echo (int)$requests['completed']; ?></span></div>
            <div class="stat-row"><span>Today</span><span><?php echo (int)$requests['today']; ?></span></div>
            <div class="stat-row"><span>This Week</span><span><?php echo (int)$requests['week']; ?></span></div>
        </div></div>

        <div class="report-card card"><div class="card-body"><h3>⭐ Reviews & Ratings</h3>
            <div class="stat-row"><span>Total Reviews</span><span><?php echo (int)$reviews['total']; ?></span></div>
            <div class="stat-row"><span>Average Rating</span><span><?php echo number_format((float)$reviews['avg'], 2); ?>/5</span></div>
            <div class="stat-row"><span>This Week</span><span><?php echo (int)$reviews['week']; ?></span></div>
            <?php if ((int)$reviews['total']>0): ?>
                <?php for($i=5;$i>=1;$i--): $key = [1=>'one',2=>'two',3=>'three',4=>'four',5=>'five'][$i]; $cnt = (int)$reviews[$key]; $pct = $reviews['total']>0 ? ($cnt/$reviews['total']*100) : 0; ?>
                <div class="stat-row" style="align-items:center; gap:8px;">
                    <span style="width:40px; text-align:right; display:inline-block;">&nbsp;<?php echo $i; ?>★</span>
                    <div class="rating-bar" style="flex:1;">
                        <div class="rating-fill" style="width: <?php echo round($pct,2); ?>%;"></div>
                    </div>
                    <span style="width:40px; text-align:left; display:inline-block;">&nbsp;<?php echo $cnt; ?></span>
                </div>
                <?php endfor; ?>
            <?php endif; ?>
        </div></div>

        <div class="report-card card"><div class="card-body"><h3>💬 Messages</h3>
            <div class="stat-row"><span>Total</span><span><?php echo (int)$messages['total']; ?></span></div>
            <div class="stat-row"><span>Unread</span><span><?php echo (int)$messages['unread']; ?></span></div>
            <div class="stat-row"><span>Today</span><span><?php echo (int)$messages['today']; ?></span></div>
            <div class="stat-row"><span>This Week</span><span><?php echo (int)$messages['week']; ?></span></div>
        </div></div>
    </div>

    <!-- Categories and Active Users -->
    <div class="grid grid-2" style="margin-top:16px; gap:16px;">
        <div class="card"><div class="card-body"><h3>📦 Popular Item Categories</h3>
            <?php if(!empty($item_categories)): foreach($item_categories as $cat): ?>
                <div class="stat-row"><span><?php echo htmlspecialchars($cat['category']); ?></span><span><?php echo (int)$cat['cnt']; ?></span></div>
            <?php endforeach; else: ?>
                <div class="empty-state"><p>No category data.</p></div>
            <?php endif; ?>
        </div></div>
        <div class="card"><div class="card-body"><h3>⚙️ Popular Service Categories</h3>
            <?php if(!empty($service_categories)): foreach($service_categories as $cat): ?>
                <div class="stat-row"><span><?php echo htmlspecialchars($cat['category']); ?></span><span><?php echo (int)$cat['cnt']; ?></span></div>
            <?php endforeach; else: ?>
                <div class="empty-state"><p>No category data.</p></div>
            <?php endif; ?>
        </div></div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div class="card-header" style="border-bottom:0; padding:0 0 10px 0; font-weight:800;">🏆 Most Active Users</div>
            <?php if(!empty($active_users)): foreach($active_users as $au): ?>
                <div class="stat-row">
                    <span><?php echo htmlspecialchars($au['username']); ?> <small class="muted">(<?php echo htmlspecialchars($au['role']); ?>)</small></span>
                    <span><?php echo (int)$au['score']; ?> activities</span>
                </div>
            <?php endforeach; else: ?>
                <div class="empty-state"><p>No activity yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-body">
            <div class="card-header" style="border-bottom:0; padding:0 0 10px 0; font-weight:800;">🕒 Recent Activity (Last 7 Days)</div>
            <?php if(!empty($recent)): foreach($recent as $ev): ?>
                <div class="list-item" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border);">
                    <div><strong><?php echo htmlspecialchars(ucfirst($ev['t'])); ?></strong> <small class="muted">: <?php echo htmlspecialchars($ev['a']); ?></small></div>
                    <div class="muted"><?php echo date('M j, Y', strtotime($ev['d'])); ?></div>
                </div>
            <?php endforeach; else: ?>
                <div class="empty-state"><p>No recent activity.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php render_footer(); ?>