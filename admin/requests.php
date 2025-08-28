<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

// Handle actions (approve/reject/complete/delete)
$allowedActions = ['approve','reject','complete','delete'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], $allowedActions, true)) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Invalid CSRF token.');
        header('Location: ' . site_href('admin/requests.php'));
        exit;
    }
    $rid = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = $_POST['action'];
    if ($rid > 0) {
        if ($action === 'delete') {
            if ($stmt = $conn->prepare("DELETE FROM requests WHERE request_id = ?")) {
                $stmt->bind_param('i', $rid);
                $stmt->execute();
                $stmt->close();
                flash_set('success', 'Request deleted.');
            }
        } else {
            $newStatus = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'completed');
            if ($stmt = $conn->prepare("UPDATE requests SET status = ? WHERE request_id = ?")) {
                $stmt->bind_param('si', $newStatus, $rid);
                $stmt->execute();
                $stmt->close();
            }
            // If approving an item request, set item availability to pending
            if ($newStatus === 'approved') {
                if ($stmt2 = $conn->prepare("UPDATE items i JOIN requests r ON r.item_id = i.item_id AND r.request_type='item' SET i.availability_status='pending' WHERE r.request_id = ?")) {
                    $stmt2->bind_param('i', $rid);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            flash_set('success', 'Request status updated to ' . ucfirst($newStatus) . '.');
        }
    }
    header('Location: ' . site_href('admin/requests.php'));
    exit;
}

// Filters
$status = isset($_GET['status']) && in_array($_GET['status'], ['pending','approved','rejected','completed'], true) ? $_GET['status'] : '';
$type = isset($_GET['type']) && in_array($_GET['type'], ['item','service'], true) ? $_GET['type'] : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$clauses = [];
$params = [];
$types = '';
if ($status !== '') { $clauses[] = 'r.status = ?'; $params[] = $status; $types .= 's'; }
if ($type !== '') { $clauses[] = 'r.request_type = ?'; $params[] = $type; $types .= 's'; }
if ($from !== '') { $clauses[] = 'DATE(r.request_date) >= ?'; $params[] = $from; $types .= 's'; }
if ($to !== '') { $clauses[] = 'DATE(r.request_date) <= ?'; $params[] = $to; $types .= 's'; }
if ($q !== '') {
    if (ctype_digit($q)) {
        $clauses[] = 'r.request_id = ?';
        $params[] = (int)$q; $types .= 'i';
    } else {
        $clauses[] = '(u1.username LIKE ? OR u2.username LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like; $types .= 's';
        $params[] = $like; $types .= 's';
    }
}

$where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

$requests = [];
$sql = "SELECT r.request_id, r.status, r.request_date, r.request_type,
               u1.username as requester, u2.username as giver
        FROM requests r
        JOIN users u1 ON r.requester_id = u1.user_id
        LEFT JOIN users u2 ON r.giver_id = u2.user_id
        $where
        ORDER BY r.request_date DESC
        LIMIT $perPage OFFSET $offset";

if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) { $requests[] = $row; }
        $res->free();
    }
    $stmt->close();
}

// Optional: count for pagination UI (lightweight)
$total = 0;
$sqlCount = "SELECT COUNT(*) as c FROM requests r JOIN users u1 ON r.requester_id=u1.user_id LEFT JOIN users u2 ON r.giver_id=u2.user_id $where";
if ($stmtc = $conn->prepare($sqlCount)) {
    if ($types !== '') { $stmtc->bind_param($types, ...$params); }
    if ($stmtc->execute()) { $rc = $stmtc->get_result(); $rowc = $rc->fetch_assoc(); $total = (int)($rowc['c'] ?? 0); $rc->free(); }
    $stmtc->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Requests - Admin</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <h2>📋 Admin - Manage Requests</h2>
    <p>
        <a href="<?php echo site_href('admin/panel.php'); ?>" class="btn btn-primary">← Back to Admin Panel</a>
        <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary">Dashboard</a>
    </p>

    <?php if ($msg = flash_get('success')): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
    <?php if ($msg = flash_get('error')): ?><div class="alert alert-danger"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <form method="get" class="filter-bar" style="margin-bottom:12px;">
        <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by username or ID" />
        <select class="form-control" name="status">
            <option value="">All Statuses</option>
            <?php foreach(REQUEST_STATUSES as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-control" name="type">
            <option value="">All Types</option>
            <option value="item" <?php echo $type==='item'?'selected':''; ?>>Item</option>
            <option value="service" <?php echo $type==='service'?'selected':''; ?>>Service</option>
        </select>
        <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
        <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
        <button class="btn btn-default" type="submit">Filter</button>
    </form>

    <div class="muted" style="margin-bottom:8px;">
        Showing <?php echo count($requests); ?> of <?php echo (int)$total; ?>
        <?php if ($total > $perPage): ?> | Page <?php echo $page; ?><?php endif; ?>
    </div>

    <?php if (!empty($requests)): ?>
    <table class="table">
        <thead><tr>
            <th>ID</th><th>Status</th><th>Type</th><th>Requester</th><th>Giver</th><th>Date</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($requests as $r): ?>
            <tr>
                <td><?php echo $r['request_id']; ?></td>
                <td class="status-<?php echo htmlspecialchars($r['status']); ?>"><?php echo ucfirst($r['status']); ?></td>
                <td><?php echo htmlspecialchars($r['request_type']); ?></td>
                <td><?php echo htmlspecialchars($r['requester']); ?></td>
                <td><?php echo $r['giver'] ? htmlspecialchars($r['giver']) : '-'; ?></td>
                <td><?php echo date('M j, Y', strtotime($r['request_date'])); ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" action="<?php echo site_href('admin/requests.php'); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-primary" type="submit">Approve</button>
                        </form>
                        <form method="post" action="<?php echo site_href('admin/requests.php'); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-danger" type="submit">Reject</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'approved'): ?>
                        <form method="post" action="<?php echo site_href('admin/requests.php'); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                            <input type="hidden" name="action" value="complete">
                            <button class="btn btn-default" type="submit">Mark Completed</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?php echo site_href('admin/requests.php'); ?>" onsubmit="return confirm('Delete this request?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-outline" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php render_pagination($page, $perPage, count($requests), $total); ?>
    <?php else: ?>
        <div class="empty-state"><h3>No requests found</h3></div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
