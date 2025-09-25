<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;
$typeFilter = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
$show = isset($_GET['show']) ? (string)($_GET['show'] ?? 'all') : 'all';
$userId = (int)($_SESSION['user_id'] ?? 0);
$total = 0; $rows = [];
if (db_connected() && $userId) {
    global $conn;
    $conds = ['user_id=?']; $params = [$userId]; $types = 'i';
    if ($typeFilter !== '') { $conds[]='type=?'; $params[]=$typeFilter; $types.='s'; }
    if ($show === 'unread') { $conds[]='is_read=0'; }
    elseif ($show === 'read') { $conds[]='is_read=1'; }
    $where = implode(' AND ', $conds);
    if ($st=$conn->prepare("SELECT COUNT(*) c FROM notifications WHERE $where")) { $st->bind_param($types,...$params); if($st->execute()){ $r=$st->get_result()->fetch_assoc(); $total=(int)($r['c']??0);} $st->close(); }
    if ($st=$conn->prepare("SELECT id,type,subject,body,is_read,related_type,related_id,created_at FROM notifications WHERE $where ORDER BY id DESC LIMIT ?,?")) { $params2=$params; $params2[]=$offset; $params2[]=$perPage; $types2=$types.'ii'; $st->bind_param($types2,...$params2); if($st->execute()){ $res=$st->get_result(); while($row=$res->fetch_assoc()) $rows[]=$row; $res->free(); } $st->close(); }
}
$title='Notifications';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e($title); ?> — ShareHub</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head><body class="notifications-page">
<?php render_header(); ?>
<div class="wrapper narrow notifications-wrapper">
    <div class="notifications-header">
        <div class="notifications-heading">
            <h1><?php echo e($title); ?></h1>
            <p class="notifications-sub">Stay up to date with request status changes, system alerts and (soon) messages.</p>
        </div>
        <form method="post" action="#" class="notif-markall-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="mark_all">
            <button type="submit" class="btn btn-default btn-sm">Mark All Read</button>
        </form>
    </div>
    <form method="get" class="filter-bar notifications-filter">
        <div class="filter-group">
            <label for="typeFilter">Type</label>
            <input id="typeFilter" name="type" class="form-control" value="<?php echo e($typeFilter); ?>" placeholder="(any)">
        </div>
        <div class="filter-group">
            <label for="showFilter">State</label>
            <select id="showFilter" name="show" class="form-control">
                <option value="all" <?php if($show==='all') echo 'selected'; ?>>All</option>
                <option value="unread" <?php if($show==='unread') echo 'selected'; ?>>Unread</option>
                <option value="read" <?php if($show==='read') echo 'selected'; ?>>Read</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Apply</button>
            <?php if($typeFilter!=='' || $show!=='all'): ?>
                <a class="btn btn-outline" href="<?php echo site_href('pages/notifications.php'); ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>
    <?php if(!$rows): ?>
        <div class="empty-state notifications-empty">
            <h3>No notifications</h3>
            <p>You're all caught up. New updates will appear here.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach($rows as $n): $isUnread=(int)$n['is_read']===0; ?>
                <div class="notification-item<?php echo $isUnread?' is-unread':''; ?>">
                    <div class="notification-main">
                        <div class="notification-title-row">
                            <span class="notification-subject"><?php echo e($n['subject']); ?></span>
                            <span class="badge badge-info type-badge"><?php echo e($n['type']); ?></span>
                            <?php if($isUnread): ?><span class="badge badge-warning new-badge">NEW</span><?php endif; ?>
                        </div>
                        <?php if(!empty($n['body'])): ?><div class="notification-body"><?php echo $n['body']; ?></div><?php endif; ?>
                        <div class="notification-meta">
                            <span><?php echo date('Y-m-d H:i',strtotime($n['created_at'])); ?></span>
                            <?php if(!empty($n['related_type']) && !empty($n['related_id'])): ?>
                                <span>Ref: <?php echo e($n['related_type']); ?> #<?php echo (int)$n['related_id']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if($isUnread): ?>
                            <form method="post" action="#" class="notif-mark-one">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="ids[]" value="<?php echo (int)$n['id']; ?>">
                                <button class="btn btn-default btn-sm" type="submit">Mark Read</button>
                            </form>
                        <?php else: ?>
                            <span class="already-read">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php $shownCount=count($rows); if($total>$perPage): ?>
            <div class="notifications-pager">
                <?php if($page>1): $qs=http_build_query(array_merge($_GET,['page'=>$page-1])); ?>
                    <a class="btn btn-default btn-sm" href="?<?php echo e($qs); ?>">Prev</a>
                <?php endif; ?>
                <?php if($offset+$shownCount<$total): $qs=http_build_query(array_merge($_GET,['page'=>$page+1])); ?>
                    <a class="btn btn-default btn-sm" href="?<?php echo e($qs); ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
<script>
(function(){
    const apiUrl = '<?php echo site_href('pages/api/notifications.php'); ?>';
    // Intercept Mark All form
    const markAllForm = document.querySelector('.notif-markall-form');
    if(markAllForm){
        markAllForm.addEventListener('submit', async function(ev){
            ev.preventDefault();
            const token = markAllForm.querySelector('input[name="csrf_token"]').value;
            try{
                const r = await fetch(apiUrl, {
                    method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({action:'mark_all', csrf_token: token})
                });
                const j = await r.json();
                if(j && j.success){ window.location.reload(); }
            }catch(e){}
        });
    }
    // Intercept per-item Mark Read
    document.querySelectorAll('.notif-mark-one').forEach(function(form){
        form.addEventListener('submit', async function(ev){
            ev.preventDefault();
            const token = form.querySelector('input[name="csrf_token"]').value;
            const idEl = form.querySelector('input[name="ids[]"]');
            const id = idEl ? idEl.value : null;
            if(!id) return;
            try{
                const r = await fetch(apiUrl, {
                    method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({action:'mark_read', ids: [parseInt(id,10)], csrf_token: token})
                });
                const j = await r.json();
                if(j && j.success){ window.location.reload(); }
            }catch(e){}
        });
    });
})();
</script>
</body></html>