<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

$all_items = [];

// Filters & pagination
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$statusF = isset($_GET['status']) && in_array($_GET['status'], ['available', 'pending', 'unavailable'], true) ? $_GET['status'] : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build where
$clauses = [];
$params = [];
$types = '';
if ($q !== '') {
    if (ctype_digit($q)) {
        $clauses[] = 'i.item_id=?';
        $params[] = (int)$q;
        $types .= 'i';
    }
    $clauses[] = '(i.title LIKE ? OR i.description LIKE ? OR u.username LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $types .= 's';
    $params[] = $like;
    $types .= 's';
    $params[] = $like;
    $types .= 's';
}
if ($statusF !== '') {
    $clauses[] = 'i.availability_status=?';
    $params[] = $statusF;
    $types .= 's';
}
if ($category !== '') {
    $clauses[] = 'i.category=?';
    $params[] = $category;
    $types .= 's';
}
if ($from !== '') {
    $clauses[] = 'DATE(i.posting_date) >= ?';
    $params[] = $from;
    $types .= 's';
}
if ($to !== '') {
    $clauses[] = 'DATE(i.posting_date) <= ?';
    $params[] = $to;
    $types .= 's';
}
$where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

$sql = "SELECT i.item_id, i.title, i.description, i.category, i.condition_status, i.availability_status,
               i.posting_date, i.pickup_location, u.username, u.email
        FROM items i
        JOIN users u ON i.giver_id = u.user_id
        $where
        ORDER BY i.posting_date DESC
        LIMIT $perPage OFFSET $offset";

if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $all_items[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        header('Location: ' . site_href('admin/items.php'));
        exit;
    }
    if (isset($_POST["delete_item_id"])) {
        $item_id = (int)$_POST["delete_item_id"];
        if ($stmt = $conn->prepare("DELETE FROM requests WHERE item_id = ?")) {
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();
        }
        if ($stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?")) {
            $stmt->bind_param("i", $item_id);
            if ($stmt->execute()) {
                header("location: " . site_href('admin/items.php'));
                exit;
            }
            $stmt->close();
        }
    } elseif (isset($_POST["item_id"], $_POST["new_status"])) {
        $item_id = (int)$_POST["item_id"];
        $new_status = $_POST["new_status"];
        if ($stmt = $conn->prepare("UPDATE items SET availability_status = ? WHERE item_id = ?")) {
            $stmt->bind_param("si", $new_status, $item_id);
            if ($stmt->execute()) {
                header("location: " . site_href('admin/items.php'));
                exit;
            }
            $stmt->close();
        }
    }
}

// Count total for pagination
$total = 0;
$sqlc = "SELECT COUNT(*) c FROM items i JOIN users u ON i.giver_id=u.user_id $where";
if ($st = $conn->prepare($sqlc)) {
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    if ($st->execute()) {
        $r = $st->get_result();
        $row = $r->fetch_assoc();
        $total = (int)($row['c'] ?? 0);
        $r->free();
    }
    $st->close();
}

$stats = ['available' => 0, 'unavailable' => 0, 'pending' => 0, 'total' => count($all_items)];
$categories = [];
foreach ($all_items as $it) {
    if (isset($stats[$it['availability_status']])) {
        $stats[$it['availability_status']]++;
    }
    $cat = $it['category'] ?: 'Uncategorized';
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Manage All Items - Admin</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />

</head>

<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <div class="page-top-actions">
            <a href="<?php echo site_href('admin/panel.php'); ?>" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary">Dashboard</a>
        </div>
        <h2>📦 Admin - Manage All Items</h2>
        <p>Administrative overview of all items in the system.</p>

        <form method="get" class="filter-bar" style="margin-bottom:12px;">
            <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search title/desc/username or ID" />
            <select class="form-control" name="status">
                <option value="">All Statuses</option>
                <?php foreach (ITEM_STATUSES as $s): ?><option value="<?php echo $s; ?>" <?php echo $statusF === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" type="text" name="category" value="<?php echo htmlspecialchars($category); ?>" placeholder="Category" />
            <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
            <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
            <button class="btn btn-primary" type="submit">Filter</button>
        </form>

        <div class="muted" style="margin-bottom:8px;">Showing <?php echo count($all_items); ?> of <?php echo (int)$total; ?><?php if ($total > $perPage): ?> | Page <?php echo $page; ?><?php endif; ?></div>

        <div class="grid grid-auto" style="margin-top:12px;">
            <div class="card">
                <div class="card-body" style="text-align:center;">
                    <div style="font-weight:800;">Total Items</div>
                    <div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo $stats['total']; ?></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align:center;">
                    <div style="font-weight:800;">Available</div>
                    <div class="status-available" style="font-size:1.4rem;"><?php echo $stats['available']; ?></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align:center;">
                    <div style="font-weight:800;">Pending</div>
                    <div class="status-pending" style="font-size:1.4rem;"><?php echo $stats['pending']; ?></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align:center;">
                    <div style="font-weight:800;">Unavailable</div>
                    <div class="status-unavailable" style="font-size:1.4rem;"><?php echo $stats['unavailable']; ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($all_items)): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="td-id">ID</th>
                            <th class="td-title">Title</th>
                            <th class="td-desc">Description</th>
                            <th class="td-category">Category</th>
                            <!-- <th class="td-condition">Condition</th> -->
                            <th class="td-status">Status</th>
                            <th class="td-giver">Giver</th>
                            <th class="td-posted">Posted</th>
                            <th class="td-location">Location</th>
                            <th class="td-actions">Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_items as $item): ?>
                            <tr>
                                <td class="td-id"><?php echo $item["item_id"]; ?></td>
                                <td class="td-title"><strong><?php echo htmlspecialchars($item["title"]); ?></strong></td>
                                <td class="td-desc"><?php echo htmlspecialchars($item["description"]); ?></td>
                                <td class="td-category"><?php echo htmlspecialchars($item["category"] ?: 'N/A'); ?></td>
                                <!-- <td class="td-condition"><?php echo htmlspecialchars(ucfirst($item["condition_status"])); ?></td> -->
                                <td class="td-status status-<?php echo $item["availability_status"]; ?>"><?php echo htmlspecialchars(ucfirst($item["availability_status"])); ?></td>
                                <td class="td-giver">
                                    <?php echo htmlspecialchars($item["username"]); ?><br>
                                    <small><?php echo htmlspecialchars($item["email"]); ?></small>
                                </td>
                                <td class="td-posted"><?php echo date('M j, Y', strtotime($item["posting_date"])); ?></td>
                                <td class="td-location"><?php echo htmlspecialchars($item["pickup_location"] ?: 'Not specified'); ?></td>
                                <td class="td-actions">
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline-block; margin-right:8px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="item_id" value="<?php echo $item["item_id"]; ?>">
                                        <select name="new_status" onchange="this.form.submit()" class="form-control">
                                            <option value="">Change Status</option>
                                            <option value="available" <?php echo ($item["availability_status"] == "available") ? "selected" : ""; ?>>Available</option>
                                            <option value="pending" <?php echo ($item["availability_status"] == "pending") ? "selected" : ""; ?>>Pending</option>
                                            <option value="unavailable" <?php echo ($item["availability_status"] == "unavailable") ? "selected" : ""; ?>>Unavailable</option>
                                        </select>
                                    </form>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');" style="display:inline-block;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="delete_item_id" value="<?php echo $item["item_id"]; ?>">
                                        <input type="submit" class="btn btn-danger" value="🗑️ Delete">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php render_pagination($page, $perPage, count($all_items), $total); ?>

            <?php if (!empty($categories)): ?>
                <div style="margin-top:16px;">
                    <h4>Items by Category</h4>
                    <div class="grid grid-auto" style="margin-top:10px;">
                        <?php foreach ($categories as $category => $count): ?>
                            <div class="card">
                                <div class="card-body" style="text-align:center;">
                                    <div style="font-weight:800;"><?php echo htmlspecialchars($category); ?></div>
                                    <div style="font-size:1.1rem; font-weight:800; color:var(--primary);"><?php echo $count; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <h3>No items found</h3>
                <p>There are currently no items listed in the system.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php render_footer(); ?>