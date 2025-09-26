<?php
require_once __DIR__ . '/../bootstrap.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'giver') {
    header('Location: ' . site_href('pages/login.php'));
    exit;
}

$title = $description = $category = $pickup_location = '';
$condition_status = 'good';
$title_err = $description_err = $category_err = $pickup_location_err = $image_err = '';
$saved_image_url = null;

// Generate a one-time form token to prevent accidental double submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    try {
        $_SESSION['form_token_add_item'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['form_token_add_item'] = bin2hex(random_bytes(8));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $image_err = 'Invalid request. Please refresh.';
    } else {
        // Enforce one-time form token
        $sent_form_token = (string)($_POST['form_token'] ?? '');
        $stored_form_token = (string)($_SESSION['form_token_add_item'] ?? '');
        // Always consume the token so refresh won't reuse it
        unset($_SESSION['form_token_add_item']);
        if ($sent_form_token === '' || $stored_form_token === '' || !hash_equals($stored_form_token, $sent_form_token)) {
            $image_err = 'Duplicate or invalid submission detected. Please try again.';
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') $title_err = 'Please enter a title for the item.';
        elseif (mb_strlen($title) > 150) $title_err = 'Title must be 150 characters or less.';
        $description = trim((string)($_POST['description'] ?? ''));
        if ($description === '') $description_err = 'Please enter a description for the item.';
        elseif (mb_strlen($description) > 2000) $description_err = 'Description must be 2000 characters or less.';
        $allowed_categories = ['tools', 'books', 'electronics', 'garden', 'other'];
        $category = trim((string)($_POST['category'] ?? ''));
        if ($category === '' || !in_array($category, $allowed_categories, true)) $category_err = 'Please select a valid category.';
        $pickup_location = trim((string)($_POST['pickup_location'] ?? ''));
        if ($pickup_location === '') $pickup_location_err = 'Please enter the pickup location.';
        elseif (mb_strlen($pickup_location) > 255) $pickup_location_err = 'Pickup location must be 255 characters or less.';
        $allowed_conditions = ['new', 'like_new', 'good', 'fair', 'poor'];
        $condition_status = trim((string)($_POST['condition_status'] ?? 'good'));
        if (!in_array($condition_status, $allowed_conditions, true)) $condition_status = 'good';
        if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $res = upload_image_secure($_FILES['image'], 'uploads/items', 2_000_000, 1600, 1200);
            if ($res['ok']) {
                $saved_image_url = $res['pathRel'];
            } else {
                $image_err = $res['error'];
            }
        }
        // Server-side duplicate guard: if same user submits identical data within a short window, skip insert
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $payload_hash = hash('sha256', $uid . '|' . $title . '|' . $description . '|' . $category . '|' . $condition_status . '|' . $pickup_location . '|' . (string)$saved_image_url);
        $now = time();
        $last_hash = $_SESSION['last_add_item_hash'] ?? '';
        $last_time = (int)($_SESSION['last_add_item_time'] ?? 0);
        $withinWindow = ($now - $last_time) <= 15; // 15-second window
        $isDuplicatePayload = ($payload_hash !== '' && $payload_hash === $last_hash && $withinWindow);

        if ($title_err === '' && $description_err === '' && $category_err === '' && $pickup_location_err === '' && $image_err === '' && !$isDuplicatePayload) {
            $sql = 'INSERT INTO items (giver_id,title,description,category,condition_status,pickup_location,image_url) VALUES (?,?,?,?,?,?,?)';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('issssss', $uid, $title, $description, $category, $condition_status, $pickup_location, $saved_image_url);
                if ($stmt->execute()) {
                    // Record payload to prevent immediate duplicates on accidental resubmits
                    $_SESSION['last_add_item_hash'] = $payload_hash;
                    $_SESSION['last_add_item_time'] = $now;
                    header('Location: ' . site_href('pages/dashboard.php'));
                    exit;
                } else {
                    $image_err = 'Something went wrong. Please try again later.';
                }
                $stmt->close();
            } else {
                $image_err = 'Database error.';
            }
        } elseif ($isDuplicatePayload) {
            // For duplicate within the window, just redirect to avoid inserting again
            header('Location: ' . site_href('pages/dashboard.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <div class="page-top-actions"><a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">← Back</a></div>
        <h2>Add New Item</h2>
        <p>Please fill this form to add a new item for sharing.</p>
        <form method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_token" value="<?php echo e($_SESSION['form_token_add_item'] ?? ''); ?>" />
            <div class="form-group <?php echo $title_err ? 'has-error' : ''; ?>">
                <label>Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title); ?>">
                <span class="help-block"><?php echo htmlspecialchars($title_err); ?></span>
            </div>
            <div class="form-group <?php echo $description_err ? 'has-error' : ''; ?>">
                <label>Description</label>
                <textarea name="description" class="form-control"><?php echo htmlspecialchars($description); ?></textarea>
                <span class="help-block"><?php echo htmlspecialchars($description_err); ?></span>
            </div>
            <div class="form-group <?php echo $category_err ? 'has-error' : ''; ?>">
                <label>Category</label>
                <select name="category" class="form-control">
                    <option value="">Select Category</option>
                    <?php foreach (['tools' => 'Tools', 'books' => 'Books', 'electronics' => 'Electronics', 'garden' => 'Garden Equipment', 'other' => 'Other'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $category === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="help-block"><?php echo htmlspecialchars($category_err); ?></span>
            </div>
            <div class="form-group">
                <label>Condition</label>
                <select name="condition_status" class="form-control">
                    <?php foreach (['new' => 'New', 'like_new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $condition_status === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group <?php echo $pickup_location_err ? 'has-error' : ''; ?>">
                <label>Pickup Location</label>
                <input type="text" name="pickup_location" class="form-control" value="<?php echo htmlspecialchars($pickup_location); ?>">
                <span class="help-block"><?php echo htmlspecialchars($pickup_location_err); ?></span>
            </div>
            <div class="form-group <?php echo $image_err ? 'has-error' : ''; ?>">
                <label>Item Image (Optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <small class="help-block" style="color:#6b7280">Accepted: JPG, PNG, GIF, WEBP. Max 2MB.</small>
                <span class="help-block"><?php echo htmlspecialchars($image_err); ?></span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="btnAddItem">Add Item</button>
                <a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
        <script>
            (function() {
                var f = document.querySelector('form');
                var btn = document.getElementById('btnAddItem');
                if (!f || !btn) return;
                f.addEventListener('submit', function() {
                    btn.disabled = true;
                    btn.textContent = 'Saving…';
                });
            })();
        </script>
    </div>
    <?php render_footer(); ?>
</body>

</html>