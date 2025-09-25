<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (!in_array($_SESSION['role'], ['giver','admin'], true))){
    header('Location: ' . site_href('pages/dashboard.php'));
    exit;
}

$message=''; $error='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
        switch ($action) {
            case 'add_item':
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $category = (string)($_POST['category'] ?? 'Other');
                $condition_status = (string)($_POST['condition_status'] ?? 'good');
                $pickup_location = trim((string)($_POST['pickup_location'] ?? ''));
                $image_url = null;
                if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $res = upload_image_secure($_FILES['image'], 'uploads/items', 2_000_000, 1600, 1200);
                    if ($res['ok']) { $image_url = $res['pathRel']; } else { $error = $res['error']; }
                }
                if (!$title || !$description || !$pickup_location) { if(!$error) $error='Please fill in all required fields!'; }
                if(!$error){
                    if(strlen($title)>200) $title=substr($title,0,200);
                    if(strlen($description)>2000) $description=substr($description,0,2000);
                    if(strlen($pickup_location)>255) $pickup_location=substr($pickup_location,0,255);
                    $id = item_create([
                        'giver_id'=>$_SESSION['user_id'], 'title'=>$title, 'description'=>$description,
                        'category'=>$category,'condition_status'=>$condition_status,'pickup_location'=>$pickup_location,
                        'image_url'=>$image_url,
                    ]);
                    $message = $id ? 'Item added successfully!' : 'Error adding item.';
                }
                break;
            case 'update_item':
                $item_id = (int)($_POST['item_id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $category = (string)($_POST['category'] ?? 'Other');
                $condition_status = (string)($_POST['condition_status'] ?? 'good');
                $availability_status = (string)($_POST['availability_status'] ?? 'available');
                $pickup_location = trim((string)($_POST['pickup_location'] ?? ''));
                $remove_image = isset($_POST['remove_image']) && $_POST['remove_image']==='1';
                $new_image_url = null;
                if(!$remove_image && isset($_FILES['edit_image']) && is_array($_FILES['edit_image']) && ($_FILES['edit_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
                    $res = upload_image_secure($_FILES['edit_image'], 'uploads/items', 2_000_000, 1600, 1200);
                    if($res['ok']) { $new_image_url=$res['pathRel']; } else { $error=$res['error']; }
                }
                if(!$error){
                    if(strlen($title)>200) $title=substr($title,0,200);
                    if(strlen($description)>2000) $description=substr($description,0,2000);
                    if(strlen($pickup_location)>255) $pickup_location=substr($pickup_location,0,255);
                    $data=[ 'title'=>$title,'description'=>$description,'category'=>$category,'condition_status'=>$condition_status,'availability_status'=>$availability_status,'pickup_location'=>$pickup_location ];
                    if($remove_image){ $data['image_url']=null; } elseif($new_image_url!==null){ $data['image_url']=$new_image_url; }
                    $message = item_update_owned($item_id, $_SESSION['user_id'], $data) ? 'Item updated successfully!' : 'Error updating item.';
                }
                break;
            case 'delete_item':
                $item_id = (int)($_POST['item_id'] ?? 0);
                $message = item_delete($item_id, $_SESSION['user_id']) ? 'Item deleted successfully!' : 'Error deleting item.';
                break;
        }
    }
}

$items = items_list(['giver_id'=>$_SESSION['user_id']], 100, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Manage Items - Community Resource Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <div class="page-top-actions"><a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">← Back to Dashboard</a></div>
    <h2>📦 Manage Your Items</h2><p class="muted">Share items with your community</p>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="card" style="margin-top:12px;">
        <div class="card-body">
            <h3 style="margin-bottom:12px;">➕ Add New Item</h3>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_item">
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap:16px; align-items:start;">
                    <div class="form-group"><label for="title">Item Title *</label><input type="text" id="title" name="title" class="form-control" required></div>
                    <div class="form-group"><label for="category">Category</label><select id="category" name="category" class="form-control">
                        <option value="Electronics">Electronics</option><option value="Furniture">Furniture</option><option value="Clothing">Clothing</option><option value="Books">Books</option><option value="Kitchen">Kitchen</option><option value="Transportation">Transportation</option><option value="Sports">Sports</option><option value="Education">Education</option><option value="Tools">Tools</option><option value="Other">Other</option>
                    </select></div>
                    <div class="form-group"><label for="condition_status">Condition</label><select id="condition_status" name="condition_status" class="form-control">
                        <option value="new">New</option><option value="like_new">Like New</option><option value="good" selected>Good</option><option value="fair">Fair</option><option value="poor">Poor</option>
                    </select></div>
                    <div class="form-group"><label for="pickup_location">Pickup Location *</label><input type="text" id="pickup_location" name="pickup_location" class="form-control" placeholder="e.g., Downtown, 123 Main St" required></div>
                    <div class="form-group" style="grid-column:1 / -1;"><label for="image">Image (Optional)</label><input type="file" id="image" name="image" class="form-control" accept="image/*"><small class="muted">Accepted: JPG, PNG, GIF, WEBP. Max 2MB.</small></div>
                    <div class="form-group" style="grid-column:1 / -1;"><label for="description">Description *</label><textarea id="description" name="description" class="form-control" placeholder="Describe your item in detail..." required></textarea></div>
                </div>
                <button type="submit" class="btn btn-success">➕ Add Item</button>
            </form>
        </div>
    </div>
    <h3 style="margin: 18px 0 12px;">📦 Your Items</h3>
    <?php if($items): ?>
        <div class="grid grid-auto">
        <?php foreach($items as $item): ?>
            <div class="card">
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
                        <div style="font-weight:800;font-size:1.05rem;"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="muted"><?php echo htmlspecialchars($item['category']); ?></div>
                    </div>
                    <?php if(!empty($item['image_url'])): ?>
                        <?php $img = (string)$item['image_url'];
                            if (strpos($img,'http://')===0 || strpos($img,'https://')===0) {
                                // leave
                            } else {
                                $img = asset_url($img);
                            }
                        ?>
                        <img style="width:100%;max-height:220px;object-fit:cover;border:1px solid var(--border);border-radius:8px;margin-top:8px;background:var(--card);" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    <p class="muted" style="line-height:1.55;margin:8px 0 10px;"><?php echo htmlspecialchars($item['description']); ?></p>
                    <div class="grid" style="gap:8px;grid-template-columns: repeat(2,minmax(0,1fr));">
                        <div><strong>Status:</strong> <span class="badge badge-<?php echo $item['availability_status']; ?>"><?php echo ucfirst($item['availability_status']); ?></span></div>
                        <div><strong>Condition:</strong> <span class="badge badge-<?php echo $item['condition_status']; ?>"><?php echo ucfirst(str_replace('_',' ',$item['condition_status'])); ?></span></div>
                        <div><strong>Location:</strong> <?php echo htmlspecialchars($item['pickup_location']); ?></div>
                        <div><strong>Posted:</strong> <?php echo date('M j, Y', strtotime($item['posting_date'])); ?></div>
                    </div>
                </div>
                <div class="card-body" style="border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;">
                    <button class="btn btn-warning btn-sm" onclick="editItem(<?php echo $item['item_id']; ?>)">✏️ Edit</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteItem(<?php echo $item['item_id']; ?>,'<?php echo htmlspecialchars($item['title']); ?>')">🗑️ Delete</button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert-info" style="padding:16px;border-radius:12px;">
            <h4>📦 No Items Yet</h4>
            <p>Start sharing by adding your first item above! Items you share will appear here and be visible to the community.</p>
        </div>
    <?php endif; ?>
</div>

<div id="editModal" class="modal">
    <div class="modal-card">
        <div class="modal-header"><h3>✏️ Edit Item</h3></div>
        <div class="modal-body">
            <form method="POST" id="editForm" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_item"><input type="hidden" name="item_id" id="edit_item_id">
                <div class="grid" style="grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group"><label for="edit_title">Item Title *</label><input type="text" id="edit_title" name="title" class="form-control" required></div>
                    <div class="form-group"><label for="edit_category">Category</label><select id="edit_category" name="category" class="form-control"><option value="Electronics">Electronics</option><option value="Furniture">Furniture</option><option value="Clothing">Clothing</option><option value="Books">Books</option><option value="Kitchen">Kitchen</option><option value="Transportation">Transportation</option><option value="Sports">Sports</option><option value="Education">Education</option><option value="Tools">Tools</option><option value="Other">Other</option></select></div>
                    <div class="form-group"><label for="edit_condition_status">Condition</label><select id="edit_condition_status" name="condition_status" class="form-control"><option value="new">New</option><option value="like_new">Like New</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option></select></div>
                    <div class="form-group"><label for="edit_availability_status">Availability</label><select id="edit_availability_status" name="availability_status" class="form-control"><option value="available">Available</option><option value="pending">Pending</option><option value="unavailable">Unavailable</option></select></div>
                    <div class="form-group" style="grid-column:1 / -1;"><label for="edit_pickup_location">Pickup Location *</label><input type="text" id="edit_pickup_location" name="pickup_location" class="form-control" required></div>
                    <div class="form-group" style="grid-column:1 / -1;"><label for="edit_description">Description *</label><textarea id="edit_description" name="description" class="form-control" required></textarea></div>
                    <div class="form-group" style="grid-column:1 / -1;"><label for="edit_image">Change Image</label><input type="file" id="edit_image" name="edit_image" class="form-control" accept="image/*"><small class="muted">Leave empty to keep current image. Max 2MB. JPG/PNG/GIF/WEBP.</small><div style="margin-top:8px"><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="edit_remove_image" name="remove_image" value="1">Remove current image</label></div><img id="edit_current_image" src="" alt="Current image" style="margin-top:10px;max-width:100%;max-height:220px;display:none;border:1px solid var(--border);border-radius:6px;object-fit:cover;"></div>
                </div>
                <div class="modal-actions"><button type="button" class="btn btn-default" onclick="closeEditModal()">Cancel</button><button type="submit" class="btn btn-warning">✏️ Update Item</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function editItem(id){
    fetch('<?php echo site_href('pages/api/get_item.php'); ?>?id='+id)
        .then(r=>r.json())
        .then(data=>{ if(data.success){
            document.getElementById('edit_item_id').value=data.item.item_id;
            document.getElementById('edit_title').value=data.item.title;
            document.getElementById('edit_category').value=data.item.category;
            document.getElementById('edit_condition_status').value=data.item.condition_status;
            document.getElementById('edit_availability_status').value=data.item.availability_status;
            document.getElementById('edit_pickup_location').value=data.item.pickup_location;
            document.getElementById('edit_description').value=data.item.description;
            const img=document.getElementById('edit_current_image'); const removeCb=document.getElementById('edit_remove_image');
            if(data.item.image_url){
                let src = String(data.item.image_url||'');
                if(!/^https?:\/\//i.test(src)){
                    if(src.charAt(0)!=='/') src = '/' + src;
                }
                img.src = src; img.style.display='block'; removeCb.checked=false; removeCb.disabled=false;
            } else { img.src=''; img.style.display='none'; removeCb.checked=false; removeCb.disabled=true; }
            document.getElementById('editModal').classList.add('open');
        } else { alert('Error loading item data'); }})
        .catch(()=>alert('Error loading item data'));
}
function closeEditModal(){ document.getElementById('editModal').classList.remove('open'); }
function deleteItem(id,title){ if(confirm('Are you sure you want to delete "'+title+'"? This action cannot be undone.')){ const f=document.createElement('form'); f.method='POST'; f.innerHTML=`<?php echo str_replace('`','\\`', csrf_field()); ?>` + '<input type="hidden" name="action" value="delete_item">' + '<input type="hidden" name="item_id" value="'+id+'">'; document.body.appendChild(f); f.submit(); }}
window.onclick=function(e){ const m=document.getElementById('editModal'); if(e.target===m){ m.classList.remove('open'); }}
</script>
<?php render_footer(); ?>
</body>
</html>