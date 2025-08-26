<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

$notices = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review_id'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) { header('Location: '.site_href('admin/reviews.php')); exit; }
    $rid = (int)$_POST['delete_review_id'];
    if ($rid > 0 && ($stmt = $conn->prepare('DELETE FROM reviews WHERE review_id = ?'))) {
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $stmt->close();
        $notices[] = 'Review deleted.';
    }
}

// Filters & pagination
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$minRating = isset($_GET['min_rating']) && ctype_digit($_GET['min_rating']) ? max(1, min(5, (int)$_GET['min_rating'])) : '';
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;

$clauses=[];$params=[];$types='';
if ($q !== '') {
    if (ctype_digit($q)) { $clauses[]='r.review_id=?'; $params[]=(int)$q; $types.='i'; }
    $clauses[]='(ur.username LIKE ? OR uu.username LIKE ? OR r.comment LIKE ?)';
    $like='%'.$q.'%'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s'; $params[]=$like; $types.='s';
}
if ($minRating !== '') { $clauses[]='r.rating >= ?'; $params[]=(int)$minRating; $types.='i'; }
if ($from !== '') { $clauses[]='DATE(r.review_date) >= ?'; $params[]=$from; $types.='s'; }
if ($to !== '') { $clauses[]='DATE(r.review_date) <= ?'; $params[]=$to; $types.='s'; }
$where = $clauses ? ('WHERE '.implode(' AND ', $clauses)) : '';

$reviews = [];
$sql = "SELECT r.review_id, r.rating, r.comment, r.review_date, ur.username AS reviewer, uu.username AS reviewed
        FROM reviews r
        JOIN users ur ON r.reviewer_id = ur.user_id
        JOIN users uu ON r.reviewed_user_id = uu.user_id
        $where
        ORDER BY r.review_date DESC
        LIMIT $perPage OFFSET $offset";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $reviews[] = $row; }
        $res->free();
    }
    $stmt->close();
}
// Count
$total=0; $sqlc="SELECT COUNT(*) c FROM reviews r JOIN users ur ON r.reviewer_id=ur.user_id JOIN users uu ON r.reviewed_user_id=uu.user_id $where";
if($st=$conn->prepare($sqlc)){ if($types!==''){ $st->bind_param($types,...$params);} if($st->execute()){ $r=$st->get_result(); $row=$r->fetch_assoc(); $total=(int)($row['c']??0); $r->free(); } $st->close(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Reviews</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <h2>⭐ Admin - Manage Reviews</h2>
    <p>
        <a class="btn btn-primary" href="<?php echo site_href('admin/panel.php'); ?>">← Back to Admin Panel</a>
        <a class="btn btn-secondary" href="<?php echo site_href('dashboard.php'); ?>">Dashboard</a>
    </p>

    <form method="get" class="filter-bar" style="margin-bottom:12px;">
        <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search reviewer/reviewed/comment or ID" />
        <select class="form-control" name="min_rating">
            <option value="">Any rating</option>
            <?php for($i=1;$i<=5;$i++): ?>
                <option value="<?php echo $i; ?>" <?php echo (string)$minRating===(string)$i?'selected':''; ?>>Min <?php echo $i; ?>/5</option>
            <?php endfor; ?>
        </select>
        <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
        <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
        <button class="btn btn-default" type="submit">Filter</button>
    </form>

    <div class="muted" style="margin-bottom:8px;">Showing <?php echo count($reviews); ?> of <?php echo (int)$total; ?><?php if($total>$perPage): ?> | Page <?php echo $page; ?><?php endif; ?></div>

    <?php foreach($notices as $n): ?><div class="alert alert-success"><?php echo htmlspecialchars($n); ?></div><?php endforeach; ?>

    <?php if (!empty($reviews)): ?>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Rating</th><th>Reviewer</th><th>Reviewed User</th><th>Comment</th><th>Date</th><th>Admin</th></tr>
            </thead>
            <tbody>
                <?php foreach($reviews as $rv): ?>
                <tr>
                    <td><?php echo $rv['review_id']; ?></td>
                    <td><?php echo (int)$rv['rating']; ?>/5</td>
                    <td><?php echo htmlspecialchars($rv['reviewer']); ?></td>
                    <td><?php echo htmlspecialchars($rv['reviewed']); ?></td>
                    <td><?php echo htmlspecialchars(mb_strimwidth($rv['comment'], 0, 80, '...')); ?></td>
                    <td><?php echo date('M j, Y', strtotime($rv['review_date'])); ?></td>
                    <td>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Delete this review?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delete_review_id" value="<?php echo $rv['review_id']; ?>">
                            <button class="btn btn-danger btn-sm" type="submit">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($total > $perPage): ?>
        <div style="margin-top:10px;display:flex;gap:8px;">
            <?php if ($page>1): ?><a class="btn btn-default" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>">Prev</a><?php endif; ?>
            <?php if ($offset + count($reviews) < $total): ?><a class="btn btn-default" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>">Next</a><?php endif; ?>
        </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state"><h3>No reviews found</h3></div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>