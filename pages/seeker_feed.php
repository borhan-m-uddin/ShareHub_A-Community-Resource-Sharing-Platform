<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'seeker') {
    header('Location: ' . site_href('pages/dashboard.php'));
    exit;
}

$isPost = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
// Generate a one-time token for request submissions on initial GET
if (!$isPost) {
    try { $_SESSION['form_token_request'] = bin2hex(random_bytes(16)); }
    catch (Throwable $e) { $_SESSION['form_token_request'] = bin2hex(random_bytes(8)); }
}

$notice = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // One-time form token to prevent accidental duplicate submission on reload/double-click
        $sent_form_token = (string)($_POST['form_token'] ?? '');
        $stored_form_token = (string)($_SESSION['form_token_request'] ?? '');
        unset($_SESSION['form_token_request']);
        if ($sent_form_token === '' || $stored_form_token === '' || !hash_equals($stored_form_token, $sent_form_token)) {
            $error = 'Duplicate or invalid submission detected. Please refresh and try again.';
        }

        $uid = (int)($_SESSION['user_id'] ?? 0);
        if ($_POST['action'] === 'request_item') {
            $item_id = (int)($_POST['item_id'] ?? 0);
            if ($item_id > 0 && function_exists('db_connected') && db_connected()) {
                global $conn;
                // Short-window duplicate guard (same payload within 15s)
                $msg = trim((string)($_POST['message'] ?? ''));
                $payload_hash = hash('sha256', 'request_item|' . $uid . '|' . $item_id . '|' . $msg);
                $now = time();
                $last_hash = $_SESSION['last_request_hash'] ?? '';
                $last_time = (int)($_SESSION['last_request_time'] ?? 0);
                $withinWindow = ($now - $last_time) <= 15;
                if ($payload_hash !== '' && $payload_hash === $last_hash && $withinWindow) {
                    $notice = 'Your request was already submitted.';
                } else {
                if ($st = $conn->prepare('SELECT giver_id, title FROM items WHERE item_id=? AND availability_status=\'available\' LIMIT 1')) {
                    $st->bind_param('i', $item_id);
                    if ($st->execute() && ($res = $st->get_result()) && ($row = $res->fetch_assoc())) {
                        $giver_id = (int)$row['giver_id'];
                        $item_title = (string)($row['title'] ?? 'item');
                        if ($giver_id === $uid) {
                            $error = 'You cannot request your own item.';
                        }
                        if (!$error && ($chk = $conn->prepare('SELECT 1 FROM requests WHERE requester_id=? AND item_id=? AND status=\'pending\' LIMIT 1'))) {
                            $chk->bind_param('ii', $uid, $item_id);
                            $dup = $chk->execute() && $chk->get_result()->num_rows > 0;
                            $chk->close();
                            if (!$dup) {
                                if ($ins = $conn->prepare('INSERT INTO requests(requester_id, giver_id, item_id, request_type, message) VALUES (?,?,?,?,?)')) {
                                    $type = 'item';
                                    $ins->bind_param('iiiss', $uid, $giver_id, $item_id, $type, $msg);
                                    if ($ins->execute()) {
                                        $notice = 'Item request sent.';
                                        $reqId = (int)$ins->insert_id;
                                        // Notify giver about new item request
                                        if (function_exists('notify_user') && $giver_id > 0) {
                                            $subject = 'New request for your item';
                                            $body = 'A seeker requested your item: ' . $item_title;
                                            @notify_user($giver_id, 'request.new', $subject, $body, 'request', $reqId, false);
                                        }
                                        $_SESSION['last_request_hash'] = $payload_hash;
                                        $_SESSION['last_request_time'] = $now;
                                    } else {
                                        $error = 'Failed to send item request.';
                                    }
                                    $ins->close();
                                }
                            } else {
                                $error = 'You already have a pending request for this item.';
                            }
                        }
                    } else {
                        $error = 'Item not available.';
                    }
                    if ($res) {
                        $res->free();
                    }
                    $st->close();
                }
                }
            }
        } elseif ($_POST['action'] === 'request_service') {
            $service_id = (int)($_POST['service_id'] ?? 0);
            if ($service_id > 0 && function_exists('db_connected') && db_connected()) {
                global $conn;
                $msg = trim((string)($_POST['message'] ?? ''));
                $payload_hash = hash('sha256', 'request_service|' . $uid . '|' . $service_id . '|' . $msg);
                $now = time();
                $last_hash = $_SESSION['last_request_hash'] ?? '';
                $last_time = (int)($_SESSION['last_request_time'] ?? 0);
                $withinWindow = ($now - $last_time) <= 15;
                if ($payload_hash !== '' && $payload_hash === $last_hash && $withinWindow) {
                    $notice = 'Your request was already submitted.';
                } else {
                if ($st = $conn->prepare('SELECT giver_id, title FROM services WHERE service_id=? AND availability=\'available\' LIMIT 1')) {
                    $st->bind_param('i', $service_id);
                    if ($st->execute() && ($res = $st->get_result()) && ($row = $res->fetch_assoc())) {
                        $giver_id = (int)$row['giver_id'];
                        $service_title = (string)($row['title'] ?? 'service');
                        if ($giver_id === $uid) {
                            $error = 'You cannot request your own service.';
                        }
                        if (!$error && ($chk = $conn->prepare('SELECT 1 FROM requests WHERE requester_id=? AND service_id=? AND status=\'pending\' LIMIT 1'))) {
                            $chk->bind_param('ii', $uid, $service_id);
                            $dup = $chk->execute() && $chk->get_result()->num_rows > 0;
                            $chk->close();
                            if (!$dup) {
                                if ($ins = $conn->prepare('INSERT INTO requests(requester_id, giver_id, service_id, request_type, message) VALUES (?,?,?,?,?)')) {
                                    $type = 'service';
                                    $ins->bind_param('iiiss', $uid, $giver_id, $service_id, $type, $msg);
                                    if ($ins->execute()) {
                                        $notice = 'Service request sent.';
                                        $reqId = (int)$ins->insert_id;
                                        // Notify giver about new service request
                                        if (function_exists('notify_user') && $giver_id > 0) {
                                            $subject = 'New request for your service';
                                            $body = 'A seeker requested your service: ' . $service_title;
                                            @notify_user($giver_id, 'request.new', $subject, $body, 'request', $reqId, false);
                                        }
                                        $_SESSION['last_request_hash'] = $payload_hash;
                                        $_SESSION['last_request_time'] = $now;
                                    } else {
                                        $error = 'Failed to send service request.';
                                    }
                                    $ins->close();
                                }
                            } else {
                                $error = 'You already have a pending request for this service.';
                            }
                        }
                    } else {
                        $error = 'Service not available.';
                    }
                    if ($res) {
                        $res->free();
                    }
                    $st->close();
                }
                }
            }
        }
    }
}

$tab = ($_GET['tab'] ?? 'items') === 'services' ? 'services' : 'items';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tab'])) {
    $tab = $_POST['tab'] === 'services' ? 'services' : 'items';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$search = trim((string)($_GET['search'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$condition = trim((string)($_GET['condition'] ?? ''));

$items = [];
$totalItems = 0;
$services = [];
$totalServices = 0;
if ($tab === 'items' && function_exists('db_connected') && db_connected()) {
    global $conn;
    $where = ["i.availability_status='available'"];
    $types = '';
    $params = [];
    if ($search !== '') {
        $where[] = '(i.title LIKE ? OR i.description LIKE ?)';
        $types .= 'ss';
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }
    if ($category !== '') {
        $where[] = 'i.category = ?';
        $types .= 's';
        $params[] = $category;
    }
    if ($condition !== '') {
        $where[] = 'i.condition_status = ?';
        $types .= 's';
        $params[] = $condition;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT SQL_CALC_FOUND_ROWS i.item_id,i.title,i.description,i.category,i.condition_status,i.posting_date,i.image_url,i.pickup_location,u.username AS giver_name FROM items i JOIN users u ON i.giver_id=u.user_id $whereSql ORDER BY i.posting_date DESC LIMIT $perPage OFFSET $offset";
    if ($st = $conn->prepare($sql)) {
        if ($types !== '') {
            $st->bind_param($types, ...$params);
        }
        if ($st->execute() && ($res = $st->get_result())) {
            while ($r = $res->fetch_assoc()) {
                $items[] = $r;
            }
            $res->free();
        }
        $st->close();
        if ($rc = $conn->query('SELECT FOUND_ROWS() AS c')) {
            $totalItems = (int)($rc->fetch_assoc()['c'] ?? 0);
            $rc->free();
        }
    }
}
if ($tab === 'services' && function_exists('db_connected') && db_connected()) {
    global $conn;
    $where = ["s.availability='available'"];
    $types = '';
    $params = [];
    if ($search !== '') {
        $where[] = '(s.title LIKE ? OR s.description LIKE ?)';
        $types .= 'ss';
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }
    if ($category !== '') {
        $where[] = 's.category = ?';
        $types .= 's';
        $params[] = $category;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT SQL_CALC_FOUND_ROWS s.service_id,s.title,s.description,s.category,s.posting_date,s.availability,s.location,u.username AS giver_name FROM services s JOIN users u ON s.giver_id=u.user_id $whereSql ORDER BY s.posting_date DESC LIMIT $perPage OFFSET $offset";
    if ($st = $conn->prepare($sql)) {
        if ($types !== '') {
            $st->bind_param($types, ...$params);
        }
        if ($st->execute() && ($res = $st->get_result())) {
            while ($r = $res->fetch_assoc()) {
                $services[] = $r;
            }
            $res->free();
        }
        $st->close();
        if ($rc = $conn->query('SELECT FOUND_ROWS() AS c')) {
            $totalServices = (int)($rc->fetch_assoc()['c'] ?? 0);
            $rc->free();
        }
    }
}

$itemCategories = [];
$serviceCategories = [];
if ($tab === 'items' && function_exists('db_connected') && db_connected()) {
    global $conn;
    if ($st = $conn->prepare("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category <> '' ORDER BY category")) {
        if ($st->execute() && ($res = $st->get_result())) {
            while ($r = $res->fetch_assoc()) {
                $itemCategories[] = $r['category'];
            }
            $res->free();
        }
        $st->close();
    }
}
if ($tab === 'services' && function_exists('db_connected') && db_connected()) {
    global $conn;
    if ($st = $conn->prepare("SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category <> '' ORDER BY category")) {
        if ($st->execute() && ($res = $st->get_result())) {
            while ($r = $res->fetch_assoc()) {
                $serviceCategories[] = $r['category'];
            }
            $res->free();
        }
        $st->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discover – Available Items & Services</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <?php include ROOT_DIR . '/partials/head_meta.php'; ?>
    <style>
        .feed-section h3 {
            margin: 0 0 8px 0
        }

        .item-card {
            display: flex;
            flex-direction: column
        }

        .item-image-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }

        html[data-theme='dark'] .item-image-wrap {
            background: #0f172a
        }
    </style>
</head>

<body class="has-seeker-sidebar">
    <?php render_header(); ?>
    <div class="wrapper" style="margin-top:24px;">
        <div style="margin-bottom:10px;">
            <h2 style="margin:0 0 6px 0;">Discover</h2>
            <div class="muted">Browse all available items and services</div>
            <div class="btn-group" role="tablist" style="margin-top:8px;display:flex;gap:6px;">
                <?php $base = site_href('pages/seeker_feed.php'); ?>
                <a class="btn <?php echo $tab === 'items' ? 'btn-primary' : 'btn-default'; ?>" href="<?php echo $base . '?tab=items'; ?>">Items</a>
                <a class="btn <?php echo $tab === 'services' ? 'btn-primary' : 'btn-default'; ?>" href="<?php echo $base . '?tab=services'; ?>">Services</a>
            </div>
        </div>
        <?php if ($notice): ?><div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($tab === 'items'): ?>
            <section class="feed-section" style="margin-bottom:18px;">
                <h3>🛍️ Items</h3>
                <div class="card" style="margin-bottom:10px;">
                    <div class="card-body">
                        <form method="get" class="grid" style="grid-template-columns: 2fr 1fr 1fr auto; gap:10px; align-items:end;">
                            <input type="hidden" name="tab" value="items" />
                            <div class="form-group"><label for="search_i">Search</label><input id="search_i" type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title or description" /></div>
                            <div class="form-group"><label for="cat_i">Category</label><select id="cat_i" name="category" class="form-control">
                                    <option value="">All</option><?php foreach ($itemCategories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $category === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="form-group"><label for="cond_i">Condition</label><select id="cond_i" name="condition" class="form-control">
                                    <option value="">All</option><?php foreach (['new', 'like_new', 'good', 'fair', 'poor'] as $opt): ?><option value="<?php echo $opt; ?>" <?php echo $condition === $opt ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $opt)); ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="form-group"><button class="btn btn-primary" type="submit">🔎 Filter</button></div>
                        </form>
                    </div>
                </div>
                <?php if ($items): ?>
                    <div class="grid grid-auto">
                        <?php foreach ($items as $it): ?>
                            <div class="card item-card">
                                <?php
                                // Prepare normalized image URL once for both the <img> and the hover overlay CSS variable
                                $hasImage = !empty($it['image_url']);
                                $src = '';
                                if ($hasImage) {
                                    $src = (string)$it['image_url'];
                                    if (strpos($src, 'http://') === 0 || strpos($src, 'https://') === 0) {
                                        // absolute URL, leave as-is
                                    } else {
                                        if (strpos($src, '/') === 0) {
                                            // already site-absolute
                                            $src = asset_url($src);
                                        } else {
                                            // relative path; if already in uploads/* or assets/*, do not re-prefix uploads/items
                                            if (preg_match('#^(uploads/|assets/|public/)#', $src) === 1) {
                                                $src = asset_url($src);
                                            } else {
                                                $src = asset_url('uploads/items/' . ltrim($src, '/'));
                                            }
                                        }
                                    }
                                }
                                ?>
                                <div class="item-image-wrap"<?php if ($hasImage) { echo ' style="--full-src: url(\'' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '\');"'; } ?>>
                                    <?php if ($hasImage): ?>
                                        <img class="item-image" src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($it['title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <div style="font-weight:800; margin-bottom:4px;"><?php echo htmlspecialchars($it['title']); ?></div>
                                    <div class="muted" style="font-size:0.9rem;">Category: <?php echo htmlspecialchars($it['category'] ?: 'N/A'); ?> • by <?php echo htmlspecialchars($it['giver_name']); ?></div>
                                    <p class="muted" style="margin:8px 0 10px 0;"><?php echo htmlspecialchars($it['description'] ?: ''); ?></p>
                                    <div class="grid" style="gap:6px; font-size:0.9rem;">
                                        <div>Condition: <span class="badge badge-<?php echo $it['condition_status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $it['condition_status'])); ?></span></div>
                                        <div>Pickup: <?php echo htmlspecialchars($it['pickup_location'] ?: ''); ?></div>
                                        <div>Posted: <?php echo date('M j, Y', strtotime($it['posting_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="card-footer card-body" style="display:flex;justify-content:flex-end;">
                                    <form method="post" style="display:flex;gap:6px;align-items:center;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="form_token" value="<?php echo e($_SESSION['form_token_request'] ?? ''); ?>" />
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
                        $extra = ['tab' => 'items'];
                        if ($search !== '') $extra['search'] = $search;
                        if ($category !== '') $extra['category'] = $category;
                        if ($condition !== '') $extra['condition'] = $condition;
                        render_pagination($page, $perPage, count($items), $totalItems, $extra);
                    } ?>
                <?php else: ?><div class="empty-state">
                        <h4>No items available</h4>
                    </div><?php endif; ?>
            </section>
        <?php elseif ($tab === 'services'): ?>
            <section class="feed-section" style="margin-bottom:18px;">
                <h3>⚙️ Services</h3>
                <div class="card" style="margin-bottom:10px;">
                    <div class="card-body">
                        <form method="get" class="grid" style="grid-template-columns: 2fr 1fr auto; gap:10px; align-items:end;">
                            <input type="hidden" name="tab" value="services" />
                            <div class="form-group"><label for="search_s">Search</label><input id="search_s" type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Title or description" /></div>
                            <div class="form-group"><label for="cat_s">Category</label><select id="cat_s" name="category" class="form-control">
                                    <option value="">All</option><?php foreach ($serviceCategories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $category === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="form-group"><button class="btn btn-primary" type="submit">🔎 Filter</button></div>
                        </form>
                    </div>
                </div>
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
                                        <input type="hidden" name="form_token" value="<?php echo e($_SESSION['form_token_request'] ?? ''); ?>" />
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
                        $extra = ['tab' => 'services'];
                        if ($search !== '') $extra['search'] = $search;
                        if ($category !== '') $extra['category'] = $category;
                        render_pagination($page, $perPage, count($services), $totalServices, $extra);
                    } ?>
                <?php else: ?><div class="empty-state">
                        <h4>No services available</h4>
                    </div><?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php render_footer(); ?>
        <script>
        (function(){
            // Disable submit button on request forms to prevent double-clicks
            document.querySelectorAll('form input[name="action"][value="request_item"], form input[name="action"][value="request_service"]').forEach(function(hidden){
                var form = hidden.closest('form');
                if(!form) return;
                form.addEventListener('submit', function(){
                    var btn = form.querySelector('button[type="submit"]');
                    if(btn){ btn.disabled = true; btn.textContent = 'Sending…'; }
                });
            });
        })();
        </script>
</body>

</html>