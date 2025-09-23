<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'seeker') { header('Location: ' . site_href('dashboard.php')); exit; }

// Messages
$notice = '';
$error = '';

// Create item/service request via inline POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($_POST['action'] === 'request_item') {
            $item_id = (int)($_POST['item_id'] ?? 0);
            if ($item_id > 0) {
                // Ensure item available and get giver
                if ($st = $conn->prepare("SELECT giver_id FROM items WHERE item_id=? AND availability_status='available' LIMIT 1")) {
                    $st->bind_param('i', $item_id);
                    if ($st->execute() && ($res = $st->get_result()) && ($row = $res->fetch_assoc())) {
                        $giver_id = (int)$row['giver_id'];
                        if ($giver_id === $uid) { $error = 'You cannot request your own item.'; }
                        // prevent duplicate pending request
                        if (!$error && ($chk = $conn->prepare("SELECT 1 FROM requests WHERE requester_id=? AND item_id=? AND status='pending' LIMIT 1"))) {
                            $chk->bind_param('ii', $uid, $item_id);
                            $dup = $chk->execute() && $chk->get_result()->num_rows > 0;
                            $chk->close();
                            if (!$dup) {
                                if ($ins = $conn->prepare("INSERT INTO requests(requester_id, giver_id, item_id, request_type, message) VALUES (?,?,?,?,?)")) {
                                    $msg = trim((string)($_POST['message'] ?? ''));
                                    $type = 'item';
                                    $ins->bind_param('iiiss', $uid, $giver_id, $item_id, $type, $msg);
                                    if ($ins->execute()) { $notice = 'Item request sent.'; } else { $error = 'Failed to send item request.'; }
                                    $ins->close();
                                }
                            } else { $error = 'You already have a pending request for this item.'; }
                        }
                    } else { $error = 'Item not available.'; }
                    if ($res) { $res->free(); }
                    $st->close();
                }
            }
        } elseif ($_POST['action'] === 'request_service') {
            $service_id = (int)($_POST['service_id'] ?? 0);
            if ($service_id > 0) {
                if ($st = $conn->prepare("SELECT giver_id FROM services WHERE service_id=? AND availability='available' LIMIT 1")) {
                    $st->bind_param('i', $service_id);
                    if ($st->execute() && ($res = $st->get_result()) && ($row = $res->fetch_assoc())) {
                        $giver_id = (int)$row['giver_id'];
                        if ($giver_id === $uid) { $error = 'You cannot request your own service.'; }
                        if (!$error && ($chk = $conn->prepare("SELECT 1 FROM requests WHERE requester_id=? AND service_id=? AND status='pending' LIMIT 1"))) {
                            $chk->bind_param('ii', $uid, $service_id);
                            $dup = $chk->execute() && $chk->get_result()->num_rows > 0;
                            $chk->close();
                            if (!$dup) {
                                if ($ins = $conn->prepare("INSERT INTO requests(requester_id, giver_id, service_id, request_type, message) VALUES (?,?,?,?,?)")) {
                                    $msg = trim((string)($_POST['message'] ?? ''));
                                    $type = 'service';
                                    $ins->bind_param('iiiss', $uid, $giver_id, $service_id, $type, $msg);
                                    if ($ins->execute()) { $notice = 'Service request sent.'; } else { $error = 'Failed to send service request.'; }
                                    $ins->close();
                                }
                            } else { $error = 'You already have a pending request for this service.'; }
                        }
                    } else { $error = 'Service not available.'; }
                    if ($res) { $res->free(); }
                    $st->close();
                }
            }
        }
    }
}

// Tab selection (items | services)
$tab = ($_GET['tab'] ?? 'items') === 'services' ? 'services' : 'items';
// Persist tab on form posts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tab'])) { $tab = $_POST['tab'] === 'services' ? 'services' : 'items'; }

// Pagination inputs
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12; // show more per view since only one tab renders at a time
$offset = ($page - 1) * $perPage;

// Filters
$search = trim((string)($_GET['search'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$condition = trim((string)($_GET['condition'] ?? ''));

// Load available items (paged) if tab=items
$items = [];
$totalItems = 0;
if ($tab === 'items') {
    $where = ["i.availability_status='available'"];
    $types = '';
    $params = [];
    if ($search !== '') { $where[] = "(i.title LIKE ? OR i.description LIKE ?)"; $types .= 'ss'; $like = "%$search%"; $params[] = $like; $params[] = $like; }
    if ($category !== '') { $where[] = "i.category = ?"; $types .= 's'; $params[] = $category; }
    if ($condition !== '') { $where[] = "i.condition_status = ?"; $types .= 's'; $params[] = $condition; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT SQL_CALC_FOUND_ROWS i.item_id, i.title, i.description, i.category, i.condition_status, i.posting_date, i.image_url, i.pickup_location, u.username AS giver_name
            FROM items i JOIN users u ON i.giver_id=u.user_id
            $whereSql
            ORDER BY i.posting_date DESC
            LIMIT $perPage OFFSET $offset";
    if ($st = $conn->prepare($sql)) {
        if ($types !== '') { $st->bind_param($types, ...$params); }
        if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()){ $items[]=$r; } $res->free(); }
        $st->close();
        if ($rc = $conn->query("SELECT FOUND_ROWS() AS c")) { $totalItems = (int)($rc->fetch_assoc()['c'] ?? 0); $rc->free(); }
    }
}

// Load available services (paged) if tab=services
$services = [];
$totalServices = 0;
if ($tab === 'services') {
    $where = ["s.availability='available'"];
    $types = '';
    $params = [];
    if ($search !== '') { $where[] = "(s.title LIKE ? OR s.description LIKE ?)"; $types .= 'ss'; $like = "%$search%"; $params[] = $like; $params[] = $like; }
    if ($category !== '') { $where[] = "s.category = ?"; $types .= 's'; $params[] = $category; }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT SQL_CALC_FOUND_ROWS s.service_id, s.title, s.description, s.category, s.posting_date, s.availability, s.location, u.username AS giver_name
            FROM services s JOIN users u ON s.giver_id=u.user_id
            $whereSql
            ORDER BY s.posting_date DESC
            LIMIT $perPage OFFSET $offset";
    if ($st = $conn->prepare($sql)) {
        if ($types !== '') { $st->bind_param($types, ...$params); }
        if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()){ $services[]=$r; } $res->free(); }
        $st->close();
        if ($rc = $conn->query("SELECT FOUND_ROWS() AS c")) { $totalServices = (int)($rc->fetch_assoc()['c'] ?? 0); $rc->free(); }
    }
}

// Fetch categories per tab for filters
$itemCategories = [];
$serviceCategories = [];
if ($tab === 'items') {
    if ($st = $conn->prepare("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category <> '' ORDER BY category")) {
        if ($st->execute() && ($res=$st->get_result())) { while($r=$res->fetch_assoc()){ $itemCategories[] = $r['category']; } $res->free(); }
        $st->close();
    }
} else {
    if ($st = $conn->prepare("SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category <> '' ORDER BY category")) {
        if ($st->execute() && ($res=$st->get_result())) { while($r=$res->fetch_assoc()){ $serviceCategories[] = $r['category']; } $res->free(); }
        $st->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discover – Available Items & Services</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        .feed-section h3 { margin:0 0 8px 0; }
        .item-card { display:flex; flex-direction:column; }
        .item-image-wrap { position:relative; width:100%; aspect-ratio: 16/9; background:#f8fafc; border-bottom:1px solid var(--border); }
        html[data-theme='dark'] .item-image-wrap { background:#0f172a; }
    </style>
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
            <div style="margin-bottom:10px;">
                <h2 style="margin:0 0 6px 0;">Discover</h2>
                <div class="muted">Browse all available items and services</div>
                <div class="btn-group" role="tablist" style="margin-top:8px;display:flex;gap:6px;">
                    <?php $base = site_href('seeker_feed.php'); ?>
                    <a class="btn <?php echo $tab==='items'?'btn-primary':'btn-default'; ?>" href="<?php echo $base . '?tab=items'; ?>">Items</a>
                    <a class="btn <?php echo $tab==='services'?'btn-primary':'btn-default'; ?>" href="<?php echo $base . '?tab=services'; ?>">Services</a>
                </div>
            </div>

            <?php if ($notice): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($tab === 'items'): ?>
            <section class="feed-section" style="margin-bottom:18px;">
                <h3>🛍️ Items</h3>
                <div class="card" style="margin-bottom:10px;"><div class="card-body">
                    <form method="get" class="grid" style="grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end;">
                        <input type="hidden" name="tab" value="items" />
                        <div class="form-group">
                            <label for="search_i">Search</label>
                            <input id="search_i" type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title or description" />
                        </div>
                        <div class="form-group">
                            <label for="cat_i">Category</label>
                            <select id="cat_i" name="category" class="form-control">
                                <option value="">All</option>
                                <?php foreach ($itemCategories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $category===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cond_i">Condition</label>
                            <select id="cond_i" name="condition" class="form-control">
                                <option value="">All</option>
                                <?php foreach (['new','like_new','good','fair','poor'] as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo $condition===$opt?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button class="btn btn-primary" type="submit">🔎 Filter</button>
                        </div>
                    </form>
                </div></div>
                <?php if ($items): ?>
                    <div class="grid grid-auto">
                        <?php foreach ($items as $it): ?>
                            <div class="card item-card">
                                <div class="item-image-wrap">
                                    <?php if (!empty($it['image_url'])): ?>
                                        <img class="item-image" src="<?php echo htmlspecialchars($it['image_url']); ?>" alt="<?php echo htmlspecialchars($it['title']); ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div style="font-weight:800; margin-bottom:4px;"><?php echo htmlspecialchars($it['title']); ?></div>
                                    <div class="muted" style="font-size:0.9rem;">Category: <?php echo htmlspecialchars($it['category'] ?: 'N/A'); ?> • by <?php echo htmlspecialchars($it['giver_name']); ?></div>
                                    <p class="muted" style="margin:8px 0 10px 0;"><?php echo htmlspecialchars($it['description'] ?: ''); ?></p>
                                    <div class="grid" style="gap:6px; font-size:0.9rem;">
                                        <div>Condition: <span class="badge badge-<?php echo $it['condition_status']; ?>"><?php echo ucfirst(str_replace('_',' ',$it['condition_status'])); ?></span></div>
                                        <div>Pickup: <?php echo htmlspecialchars($it['pickup_location'] ?: ''); ?></div>
                                        <div>Posted: <?php echo date('M j, Y', strtotime($it['posting_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="card-footer card-body" style="display:flex;justify-content:flex-end;">
                                    <form method="post" style="display:flex;gap:6px;align-items:center;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="request_item" />
                                        <input type="hidden" name="item_id" value="<?php echo (int)$it['item_id']; ?>" />
                                        <input type="hidden" name="tab" value="items" />
                                        <input type="text" name="message" class="form-control" placeholder="Message (optional)" style="max-width:260px;" />
                                        <button class="btn btn-success" type="submit">📨 Request</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (function_exists('render_pagination')) { 
                        $extra = ['tab'=>'items'];
                        if($search!=='') $extra['search']=$search; if($category!=='') $extra['category']=$category; if($condition!=='') $extra['condition']=$condition;
                        render_pagination($page, $perPage, count($items), $totalItems, $extra); 
                    } ?>
                <?php else: ?>
                    <div class="empty-state"><h4>No items available</h4></div>
                <?php endif; ?>
            </section>
            <?php elseif ($tab === 'services'): ?>
            <section class="feed-section" style="margin-bottom:18px;">
                <h3>⚙️ Services</h3>
                <div class="card" style="margin-bottom:10px;"><div class="card-body">
                    <form method="get" class="grid" style="grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end;">
                        <input type="hidden" name="tab" value="services" />
                        <div class="form-group">
                            <label for="search_s">Search</label>
                            <input id="search_s" type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title or description" />
                        </div>
                        <div class="form-group">
                            <label for="cat_s">Category</label>
                            <select id="cat_s" name="category" class="form-control">
                                <option value="">All</option>
                                <?php foreach ($serviceCategories as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $category===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button class="btn btn-primary" type="submit">🔎 Filter</button>
                        </div>
                    </form>
                </div></div>
                <?php if ($services): ?>
                    <div class="grid grid-auto">
                        <?php foreach ($services as $sv): ?>
                            <div class="card item-card">
                                <div class="card-body">
                                    <div style="font-weight:800; margin-bottom:4px;"><?php echo htmlspecialchars($sv['title']); ?></div>
                                    <div class="muted" style="font-size:0.9rem;">Category: <?php echo htmlspecialchars($sv['category'] ?: 'N/A'); ?> • by <?php echo htmlspecialchars($sv['giver_name']); ?></div>
                                    <p class="muted" style="margin:8px 0 10px 0;"><?php echo htmlspecialchars($sv['description'] ?: ''); ?></p>
                                    <div class="grid" style="gap:6px; font-size:0.9rem;">
                                        <div>Availability: <span class="badge badge-<?php echo $sv['availability']; ?>"><?php echo ucfirst($sv['availability']); ?></span></div>
                                        <div>Location: <?php echo htmlspecialchars($sv['location'] ?: ''); ?></div>
                                        <div>Posted: <?php echo date('M j, Y', strtotime($sv['posting_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="card-footer card-body" style="display:flex;justify-content:flex-end;">
                                    <form method="post" style="display:flex;gap:6px;align-items:center;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="request_service" />
                                        <input type="hidden" name="service_id" value="<?php echo (int)$sv['service_id']; ?>" />
                                        <input type="hidden" name="tab" value="services" />
                                        <input type="text" name="message" class="form-control" placeholder="Message (optional)" style="max-width:260px;" />
                                        <button class="btn btn-success" type="submit">📨 Request</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (function_exists('render_pagination')) { 
                        $extra = ['tab'=>'services'];
                        if($search!=='') $extra['search']=$search; if($category!=='') $extra['category']=$category;
                        render_pagination($page, $perPage, count($services), $totalServices, $extra); 
                    } ?>
                <?php else: ?>
                    <div class="empty-state"><h4>No services available</h4></div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
</div>
    <?php render_footer(); ?>
