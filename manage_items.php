<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

// Check if user is logged in and is giver or admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || 
   ($_SESSION["role"] !== "giver" && $_SESSION["role"] !== "admin")){
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle item actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check for all item mutations
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = "Invalid request. Please try again.";
    } else {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_item':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category = $_POST['category'];
                $condition_status = $_POST['condition_status'];
                $pickup_location = trim($_POST['pickup_location']);
                $image_url = null;

                // Handle optional single image upload
                if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $err = (int)$_FILES['image']['error'];
                    if ($err === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['image']['tmp_name'];
                        $size = (int)($_FILES['image']['size'] ?? 0);
                        // Validate MIME with fallbacks and JPEG variants
                        $mime = null;
                        if (function_exists('finfo_open')) {
                            $fi = finfo_open(FILEINFO_MIME_TYPE);
                            if ($fi) { $mime = finfo_file($fi, $tmp); finfo_close($fi); }
                        }
                        if (!$mime && function_exists('getimagesize')) {
                            $gi = @getimagesize($tmp);
                            if (is_array($gi) && !empty($gi['mime'])) { $mime = $gi['mime']; }
                        }
                        if (!$mime && isset($_FILES['image']['type'])) { $mime = $_FILES['image']['type']; }
                        $mime = $mime ? strtolower($mime) : null;
                        $allowed = [
                            'image/jpeg' => 'jpg',
                            'image/jpg'  => 'jpg',
                            'image/pjpeg'=> 'jpg',
                            'image/png'  => 'png',
                            'image/gif'  => 'gif',
                            'image/webp' => 'webp',
                        ];
                        if ($mime && isset($allowed[$mime]) && $size <= 2*1024*1024) {
                            $ext = $allowed[$mime];
                            $dir = __DIR__ . '/uploads/items';
                            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                            try { $name = bin2hex(random_bytes(16)) . '.' . $ext; } catch (Exception $e) { $name = uniqid('', true) . '.' . $ext; }
                            $destAbs = $dir . '/' . $name;
                            $destRel = 'uploads/items/' . $name;
                            if (move_uploaded_file($tmp, $destAbs)) {
                                $image_url = $destRel;
                            } else {
                                $error = "Error saving uploaded image.";
                            }
                        } else {
                            $error = "Invalid image (type or size). Allowed: JPG/PNG/GIF/WEBP up to 2MB.";
                        }
                    } else {
                        $errMap = [
                            UPLOAD_ERR_INI_SIZE => 'The file exceeds server limit (upload_max_filesize).',
                            UPLOAD_ERR_FORM_SIZE => 'The file exceeds form limit (MAX_FILE_SIZE).',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.'
                        ];
                        $error = isset($errMap[$err]) ? $errMap[$err] : ("Image upload error (code $err).");
                    }
                }

                if (!empty($title) && !empty($description) && !empty($pickup_location) && empty($error)) {
                    // Soft input limits
                    if (strlen($title) > 200) { $title = substr($title, 0, 200); }
                    if (strlen($description) > 2000) { $description = substr($description, 0, 2000); }
                    if (strlen($pickup_location) > 255) { $pickup_location = substr($pickup_location, 0, 255); }
                    $stmt = $conn->prepare("INSERT INTO items (giver_id, title, description, category, condition_status, pickup_location, image_url, posting_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("issssss", $_SESSION['user_id'], $title, $description, $category, $condition_status, $pickup_location, $image_url);
                    if ($stmt->execute()) {
                        $message = "Item added successfully!";
                    } else {
                        $error = "Error adding item.";
                    }
                    $stmt->close();
                } else if (empty($error)) {
                    $error = "Please fill in all required fields!";
                }
                break;

            case 'update_item':
                $item_id = intval($_POST['item_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category = $_POST['category'];
                $condition_status = $_POST['condition_status'];
                $availability_status = $_POST['availability_status'];
                $pickup_location = trim($_POST['pickup_location']);
                $new_image_url = null;
                $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

                // Optional: process new image upload
                if (!$remove_image && isset($_FILES['edit_image']) && is_array($_FILES['edit_image']) && ($_FILES['edit_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $err = (int)$_FILES['edit_image']['error'];
                    if ($err === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['edit_image']['tmp_name'];
                        $size = (int)($_FILES['edit_image']['size'] ?? 0);
                        $mime = null;
                        if (function_exists('finfo_open')) {
                            $fi = finfo_open(FILEINFO_MIME_TYPE);
                            if ($fi) { $mime = finfo_file($fi, $tmp); finfo_close($fi); }
                        }
                        if (!$mime && function_exists('getimagesize')) {
                            $gi = @getimagesize($tmp);
                            if (is_array($gi) && !empty($gi['mime'])) { $mime = $gi['mime']; }
                        }
                        if (!$mime && isset($_FILES['edit_image']['type'])) { $mime = $_FILES['edit_image']['type']; }
                        $mime = $mime ? strtolower($mime) : null;
                        $allowed = [
                            'image/jpeg' => 'jpg',
                            'image/jpg'  => 'jpg',
                            'image/pjpeg'=> 'jpg',
                            'image/png'  => 'png',
                            'image/gif'  => 'gif',
                            'image/webp' => 'webp',
                        ];
                        if ($mime && isset($allowed[$mime]) && $size <= 2*1024*1024) {
                            $ext = $allowed[$mime];
                            $dir = __DIR__ . '/uploads/items';
                            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                            try { $name = bin2hex(random_bytes(16)) . '.' . $ext; } catch (Exception $e) { $name = uniqid('', true) . '.' . $ext; }
                            $destAbs = $dir . '/' . $name;
                            $destRel = 'uploads/items/' . $name;
                            if (move_uploaded_file($tmp, $destAbs)) {
                                $new_image_url = $destRel;
                            } else {
                                $error = "Error saving uploaded image.";
                            }
                        } else {
                            $error = "Invalid image (type or size). Allowed: JPG/PNG/GIF/WEBP up to 2MB.";
                        }
                    } else {
                        $errMap = [
                            UPLOAD_ERR_INI_SIZE => 'The file exceeds server limit (upload_max_filesize).',
                            UPLOAD_ERR_FORM_SIZE => 'The file exceeds form limit (MAX_FILE_SIZE).',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.'
                        ];
                        $error = isset($errMap[$err]) ? $errMap[$err] : ("Image upload error (code $err).");
                    }
                }
                
                if (empty($error)) {
                    if (strlen($title) > 200) { $title = substr($title, 0, 200); }
                    if (strlen($description) > 2000) { $description = substr($description, 0, 2000); }
                    if (strlen($pickup_location) > 255) { $pickup_location = substr($pickup_location, 0, 255); }
                    if ($remove_image) {
                        $stmt = $conn->prepare("UPDATE items SET title = ?, description = ?, category = ?, condition_status = ?, availability_status = ?, pickup_location = ?, image_url = NULL WHERE item_id = ? AND giver_id = ?");
                        $stmt->bind_param("ssssssii", $title, $description, $category, $condition_status, $availability_status, $pickup_location, $item_id, $_SESSION['user_id']);
                    } elseif ($new_image_url !== null) {
                        $stmt = $conn->prepare("UPDATE items SET title = ?, description = ?, category = ?, condition_status = ?, availability_status = ?, pickup_location = ?, image_url = ? WHERE item_id = ? AND giver_id = ?");
                        $stmt->bind_param("sssssssii", $title, $description, $category, $condition_status, $availability_status, $pickup_location, $new_image_url, $item_id, $_SESSION['user_id']);
                    } else {
                        $stmt = $conn->prepare("UPDATE items SET title = ?, description = ?, category = ?, condition_status = ?, availability_status = ?, pickup_location = ? WHERE item_id = ? AND giver_id = ?");
                        $stmt->bind_param("ssssssii", $title, $description, $category, $condition_status, $availability_status, $pickup_location, $item_id, $_SESSION['user_id']);
                    }
                }
                if (empty($error)) {
                    if ($stmt->execute()) {
                        $message = "Item updated successfully!";
                    } else {
                        $error = "Error updating item.";
                    }
                    $stmt->close();
                }
                break;

            case 'delete_item':
                $item_id = intval($_POST['item_id']);
                $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ? AND giver_id = ?");
                $stmt->bind_param("ii", $item_id, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $message = "Item deleted successfully!";
                } else {
                    $error = "Error deleting item.";
                }
                $stmt->close();
                break;
        }
    }
    }
}

// Get user's items
$stmt = $conn->prepare("SELECT * FROM items WHERE giver_id = ? ORDER BY posting_date DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$items_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - Community Resource Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>📦 Manage Your Items</h2>
        <p class="muted">Share items with your community</p>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add New Item Panel -->
        <div class="card" style="margin-top:12px;">
            <div class="card-body">
                <h3 style="margin-bottom: 12px;">➕ Add New Item</h3>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_item">
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 16px; align-items:start;">
                        <div class="form-group">
                            <label for="title">Item Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="Electronics">Electronics</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Clothing">Clothing</option>
                                <option value="Books">Books</option>
                                <option value="Kitchen">Kitchen</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Sports">Sports</option>
                                <option value="Education">Education</option>
                                <option value="Tools">Tools</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="condition_status">Condition</label>
                            <select id="condition_status" name="condition_status" class="form-control">
                                <option value="new">New</option>
                                <option value="like_new">Like New</option>
                                <option value="good" selected>Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pickup_location">Pickup Location *</label>
                            <input type="text" id="pickup_location" name="pickup_location" class="form-control" placeholder="e.g., Downtown, 123 Main St" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="image">Image (Optional)</label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/*">
                            <small class="muted">Accepted: JPG, PNG, GIF, WEBP. Max 2MB.</small>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" class="form-control" placeholder="Describe your item in detail..." required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">➕ Add Item</button>
                </form>
            </div>
        </div>

        <!-- Items List -->
        <h3 style="margin: 18px 0 12px;">📦 Your Items</h3>
        <?php if ($items_result->num_rows > 0): ?>
            <div class="grid grid-auto">
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
                                <div style="font-weight:800; font-size:1.05rem;"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div class="muted"><?php echo htmlspecialchars($item['category']); ?></div>
                            </div>
                            <?php if (!empty($item['image_url'])): ?>
                                <img style="width:100%;max-height:220px;object-fit:cover;border:1px solid var(--border);border-radius:8px;margin-top:8px;background:var(--card);" src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php endif; ?>
                            <p class="muted" style="line-height:1.55;margin:8px 0 10px;"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="grid" style="gap:8px;grid-template-columns: repeat(2, minmax(0,1fr));">
                                <div><strong>Status:</strong> <span class="badge badge-<?php echo $item['availability_status']; ?>"><?php echo ucfirst($item['availability_status']); ?></span></div>
                                <div><strong>Condition:</strong> <span class="badge badge-<?php echo $item['condition_status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $item['condition_status'])); ?></span></div>
                                <div><strong>Location:</strong> <?php echo htmlspecialchars($item['pickup_location']); ?></div>
                                <div><strong>Posted:</strong> <?php echo date('M j, Y', strtotime($item['posting_date'])); ?></div>
                            </div>
                        </div>
                        <div class="card-body" style="border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end;">
                            <button class="btn btn-warning btn-sm" onclick="editItem(<?php echo $item['item_id']; ?>)">✏️ Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteItem(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>')">🗑️ Delete</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert-info" style="padding:16px;border-radius:12px;">
                <h4>📦 No Items Yet</h4>
                <p>Start sharing by adding your first item above! Items you share will appear here and be visible to the community.</p>
            </div>
        <?php endif; ?>

        <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-default" style="margin-top:16px;">← Back to Dashboard</a>
    </div>

    <!-- Edit Item Modal -->
    <div id="editModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ Edit Item</h3>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_item">
                    <input type="hidden" name="item_id" id="edit_item_id">
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label for="edit_title">Item Title *</label>
                            <input type="text" id="edit_title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_category">Category</label>
                            <select id="edit_category" name="category" class="form-control">
                                <option value="Electronics">Electronics</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Clothing">Clothing</option>
                                <option value="Books">Books</option>
                                <option value="Kitchen">Kitchen</option>
                                <option value="Transportation">Transportation</option>
                                <option value="Sports">Sports</option>
                                <option value="Education">Education</option>
                                <option value="Tools">Tools</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_condition_status">Condition</label>
                            <select id="edit_condition_status" name="condition_status" class="form-control">
                                <option value="new">New</option>
                                <option value="like_new">Like New</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_availability_status">Availability</label>
                            <select id="edit_availability_status" name="availability_status" class="form-control">
                                <option value="available">Available</option>
                                <option value="pending">Pending</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="edit_pickup_location">Pickup Location *</label>
                            <input type="text" id="edit_pickup_location" name="pickup_location" class="form-control" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="edit_description">Description *</label>
                            <textarea id="edit_description" name="description" class="form-control" required></textarea>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="edit_image">Change Image</label>
                            <input type="file" id="edit_image" name="edit_image" class="form-control" accept="image/*">
                            <small class="muted">Leave empty to keep current image. Max 2MB. JPG/PNG/GIF/WEBP.</small>
                            <div style="margin-top:8px">
                                <label style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="edit_remove_image" name="remove_image" value="1">
                                    Remove current image
                                </label>
                            </div>
                            <img id="edit_current_image" src="" alt="Current image" style="margin-top:10px;max-width:100%;max-height:220px;display:none;border:1px solid var(--border);border-radius:6px;object-fit:cover;">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-default" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn btn-warning">✏️ Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editItem(itemId) {
            // Get item data from the server
            fetch(`get_item.php?id=${itemId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_item_id').value = data.item.item_id;
                        document.getElementById('edit_title').value = data.item.title;
                        document.getElementById('edit_category').value = data.item.category;
                        document.getElementById('edit_condition_status').value = data.item.condition_status;
                        document.getElementById('edit_availability_status').value = data.item.availability_status;
                        document.getElementById('edit_pickup_location').value = data.item.pickup_location;
                        document.getElementById('edit_description').value = data.item.description;
                        const img = document.getElementById('edit_current_image');
                        const removeCb = document.getElementById('edit_remove_image');
                        if (data.item.image_url) {
                            img.src = data.item.image_url;
                            img.style.display = 'block';
                            removeCb.checked = false;
                            removeCb.disabled = false;
                        } else {
                            img.src = '';
                            img.style.display = 'none';
                            removeCb.checked = false;
                            removeCb.disabled = true;
                        }
                        document.getElementById('editModal').classList.add('open');
                    } else {
                        alert('Error loading item data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading item data');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('open');
        }

        function deleteItem(itemId, itemTitle) {
            if (confirm(`Are you sure you want to delete "${itemTitle}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    ${`<?php echo str_replace("`", "\\`", csrf_field()); ?>`}
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="item_id" value="${itemId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        }
    </script>
    <?php render_footer(); ?>
