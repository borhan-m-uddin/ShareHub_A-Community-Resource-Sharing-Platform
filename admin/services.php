<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

$all_services = [];

// Filters & pagination
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$availability = isset($_GET['availability']) && in_array($_GET['availability'], ['available','busy','unavailable'], true) ? $_GET['availability'] : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;

// Build where
$clauses=[];$params=[];$types='';
if ($q !== '') {
    if (ctype_digit($q)) { $clauses[]='s.service_id=?'; $params[]=(int)$q; $types.='i'; }
    $clauses[]='(s.title LIKE ? OR s.description LIKE ? OR u.username LIKE ?)'; $like='%'.$q.'%'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s';
}
if ($availability !== '') { $clauses[]='s.availability=?'; $params[]=$availability; $types.='s'; }
if ($category !== '') { $clauses[]='s.category=?'; $params[]=$category; $types.='s'; }
if ($from !== '') { $clauses[]='DATE(s.posting_date) >= ?'; $params[]=$from; $types.='s'; }
if ($to !== '') { $clauses[]='DATE(s.posting_date) <= ?'; $params[]=$to; $types.='s'; }
$where = $clauses ? ('WHERE '.implode(' AND ', $clauses)) : '';

$sql = "SELECT s.service_id, s.title, s.description, s.category, s.availability,
               s.posting_date, s.location, u.username, u.email
        FROM services s
        JOIN users u ON s.giver_id = u.user_id
        $where
        ORDER BY s.posting_date DESC
        LIMIT $perPage OFFSET $offset";

if($stmt = $conn->prepare($sql)){
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $all_services[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { header('Location: '.site_href('admin/services.php')); exit; }
    if(isset($_POST["delete_service_id"])){
        $service_id = (int)$_POST["delete_service_id"];
        if($stmt = $conn->prepare("DELETE FROM requests WHERE service_id = ?")){
            $stmt->bind_param("i", $service_id); $stmt->execute(); $stmt->close();
        }
        if($stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?")){
            $stmt->bind_param("i", $service_id);
            if($stmt->execute()){ header("location: " . site_href('admin/services.php')); exit; }
            $stmt->close();
        }
    } elseif(isset($_POST["service_id"]) && isset($_POST["new_availability"])){
        $service_id = (int)$_POST["service_id"];
        $new_availability = $_POST["new_availability"];
        if($stmt = $conn->prepare("UPDATE services SET availability = ? WHERE service_id = ?")){
            $stmt->bind_param("si", $new_availability, $service_id);
            if($stmt->execute()){ header("location: " . site_href('admin/services.php')); exit; }
            $stmt->close();
        }
    }
}

// Count total
$total=0; $sqlc="SELECT COUNT(*) c FROM services s JOIN users u ON s.giver_id=u.user_id $where"; if($st=$conn->prepare($sqlc)){ if($types!==''){ $st->bind_param($types,...$params);} if($st->execute()){ $r=$st->get_result(); $row=$r->fetch_assoc(); $total=(int)($row['c']??0); $r->free();} $st->close(); }

$stats = ['available' => 0, 'unavailable' => 0, 'busy' => 0, 'total' => count($all_services)];
$categories = [];
foreach($all_services as $service) {
    if(isset($stats[$service['availability']])) { $stats[$service['availability']]++; }
    $category = $service['category'] ?: 'Uncategorized';
    $categories[$category] = ($categories[$category] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage All Services - Admin</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>🛠️ Admin - Manage All Services</h2>
        <p>Administrative overview of all services in the system.</p>
        <p>
            <a href="<?php echo site_href('admin/panel.php'); ?>" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary">Dashboard</a>
        </p>

        <form method="get" class="filter-bar" style="margin-bottom:12px;">
            <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search title/desc/username or ID" />
            <select class="form-control" name="availability">
                <option value="">All Statuses</option>
                <?php foreach(SERVICE_AVAILABILITIES as $s): ?><option value="<?php echo $s; ?>" <?php echo $availability===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" type="text" name="category" value="<?php echo htmlspecialchars($category); ?>" placeholder="Category" />
            <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
            <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
            <button class="btn btn-default" type="submit">Filter</button>
        </form>

        <div class="muted" style="margin-bottom:8px;">Showing <?php echo count($all_services); ?> of <?php echo (int)$total; ?><?php if($total>$perPage): ?> | Page <?php echo $page; ?><?php endif; ?></div>

        <div class="grid grid-auto" style="margin-top:12px;">
            <div class="card">
                <div class="card-body" style="text-align:center;">
                    <div style="font-weight:800;">Total Services</div>
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
                    <div style="font-weight:800;">Busy</div>
                    <div class="status-busy" style="font-size:1.4rem;"><?php echo $stats['busy']; ?></div>
                </div>
            </div>
            <div class="card">
                <div class="card-body" style="text-align:center;">
                    <div style="font-weight:800;">Unavailable</div>
                    <div class="status-unavailable" style="font-size:1.4rem;"><?php echo $stats['unavailable']; ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($all_services)): ?>
            <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Availability</th>
                        <th>Provider</th>
                        <th>Posted</th>
                        <th>Location</th>
                        <th>Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_services as $service): ?>
                        <tr>
                            <td><?php echo $service["service_id"]; ?></td>
                            <td><strong><?php echo htmlspecialchars($service["title"]); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($service["description"], 0, 80)); ?><?php echo strlen($service["description"]) > 80 ? '...' : ''; ?></td>
                            <td><?php echo htmlspecialchars($service["category"] ?: 'N/A'); ?></td>
                            <td class="status-<?php echo $service["availability"]; ?>"><?php echo htmlspecialchars(ucfirst($service["availability"])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($service["username"]); ?><br>
                                <small><?php echo htmlspecialchars($service["email"]); ?></small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($service["posting_date"])); ?></td>
                            <td><?php echo htmlspecialchars($service["location"] ?: 'Not specified'); ?></td>
                            <td class="action-buttons">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="service_id" value="<?php echo $service["service_id"]; ?>">
                                    <select name="new_availability" onchange="this.form.submit()" class="form-control" style="font-size: 0.9em;">
                                        <option value="">Change Status</option>
                                        <option value="available" <?php echo ($service["availability"] == "available") ? "selected" : ""; ?>>Available</option>
                                        <option value="busy" <?php echo ($service["availability"] == "busy") ? "selected" : ""; ?>>Busy</option>
                                        <option value="unavailable" <?php echo ($service["availability"] == "unavailable") ? "selected" : ""; ?>>Unavailable</option>
                                    </select>
                                </form>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_service_id" value="<?php echo $service["service_id"]; ?>">
                                    <input type="submit" class="btn btn-danger" value="🗑️ Delete">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if (!empty($categories)): ?>
                <div style="margin-top:16px;">
                    <h4>Services by Category</h4>
                    <div class="grid grid-auto" style="margin-top:10px;">
                        <?php foreach($categories as $category => $count): ?>
                            <div class="card"><div class="card-body" style="text-align:center;"><div style="font-weight:800;"><?php echo htmlspecialchars($category); ?></div><div style="font-size:1.1rem; font-weight:800; color:var(--primary);"><?php echo $count; ?></div></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
    <?php render_pagination($page, $perPage, count($all_services), $total); ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No services found</h3>
                <p>There are currently no services in the system.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php render_footer(); ?>