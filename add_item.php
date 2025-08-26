<?php
// Central bootstrap and auth
require_once __DIR__ . '/bootstrap.php';

// Require giver role
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || ($_SESSION["role"] ?? '') !== "giver") {
    header("location: login.php");
    exit;
}

// Initialize state
$title = $description = $category = $pickup_location = "";
$condition_status = 'good';
$title_err = $description_err = $category_err = $pickup_location_err = $image_err = $condition_err = "";
$saved_image_url = null; // relative path stored into DB image_url

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate title
    $title = trim((string)($_POST["title"] ?? ''));
    if ($title === '') { $title_err = "Please enter a title for the item."; }

    // Validate description
    $description = trim((string)($_POST["description"] ?? ''));
    if ($description === '') { $description_err = "Please enter a description for the item."; }

    // Validate category (whitelist basic options)
    $allowed_categories = ['tools','books','electronics','garden','other'];
    $category = trim((string)($_POST["category"] ?? ''));
    if ($category === '' || !in_array($category, $allowed_categories, true)) {
        $category_err = "Please select a valid category.";
    }

    // Validate pickup location (align with schema)
    $pickup_location = trim((string)($_POST["pickup_location"] ?? ''));
    if ($pickup_location === '') { $pickup_location_err = "Please enter the pickup location."; }

    // Validate condition status (enum)
    $allowed_conditions = ['new','like_new','good','fair','poor'];
    $condition_status = trim((string)($_POST['condition_status'] ?? 'good'));
    if (!in_array($condition_status, $allowed_conditions, true)) {
        $condition_status = 'good';
    }

    // Handle single image upload (stores path in image_url)
    if (isset($_FILES['image']) && is_array($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = (int)$_FILES['image']['error'];
        if ($err === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image']['tmp_name'];

            // Determine MIME with fallbacks
            $mime = null;
            if (function_exists('finfo_open')) {
                $f = finfo_open(FILEINFO_MIME_TYPE);
                if ($f) {
                    $mime = finfo_file($f, $tmp);
                    finfo_close($f);
                }
            }
            if (!$mime && function_exists('getimagesize')) {
                $gi = @getimagesize($tmp);
                if (is_array($gi) && !empty($gi['mime'])) {
                    $mime = $gi['mime'];
                }
            }
            if (!$mime && isset($_FILES['image']['type'])) {
                $mime = $_FILES['image']['type']; // last resort
            }

            // Accept common JPEG variants too
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/jpg'  => 'jpg',
                'image/pjpeg'=> 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp',
            ];

            if (!$mime || !isset($allowed[strtolower($mime)])) {
                $image_err = "Only JPG, PNG, GIF or WEBP images are allowed.";
            } else {
                // Limit size to 2MB and respect PHP limits
                $size = (int)($_FILES['image']['size'] ?? 0);
                if ($size > 2 * 1024 * 1024) {
                    $image_err = "Image size must be 2MB or less.";
                } else {
                    $ext = $allowed[strtolower($mime)];
                    $dir = __DIR__ . '/uploads/items';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0777, true);
                    }
                    try { $name = bin2hex(random_bytes(16)) . '.' . $ext; } catch (Exception $e) { $name = uniqid('', true) . '.' . $ext; }
                    $destAbs = $dir . '/' . $name;
                    $destRel = 'uploads/items/' . $name; // stored in DB
                    if (move_uploaded_file($tmp, $destAbs)) {
                        $saved_image_url = $destRel;
                    } else {
                        $image_err = "Failed to save uploaded image.";
                    }
                }
            }
        } else {
            // Map common PHP upload errors
            $errMap = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk on the server.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $image_err = isset($errMap[$err]) ? $errMap[$err] : ("Upload error (code $err).");
        }
    }

    // Insert when valid
    if ($title_err === '' && $description_err === '' && $category_err === '' && $pickup_location_err === '' && $image_err === '') {
        // Insert with explicit condition_status; availability_status uses default
        $sql = "INSERT INTO items (giver_id, title, description, category, condition_status, pickup_location, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issssss", $param_giver_id, $param_title, $param_description, $param_category, $param_condition, $param_pickup_location, $param_image_url);
            $param_giver_id = (int)$_SESSION['user_id'];
            $param_title = $title;
            $param_description = $description;
            $param_category = $category;
            $param_condition = $condition_status;
            $param_pickup_location = $pickup_location;
            $param_image_url = $saved_image_url; // can be null
            if ($stmt->execute()) {
                header("location: dashboard.php");
                exit;
            } else {
                echo "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Item</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Add New Item</h2>
        <p>Please fill this form to add a new item for sharing.</p>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group <?php echo (!empty($title_err)) ? 'has-error' : ''; ?>">
                <label>Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>">
                <span class="help-block"><?php echo htmlspecialchars($title_err, ENT_QUOTES); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($description_err)) ? 'has-error' : ''; ?>">
                <label>Description</label>
                <textarea name="description" class="form-control"><?php echo htmlspecialchars($description, ENT_QUOTES); ?></textarea>
                <span class="help-block"><?php echo htmlspecialchars($description_err, ENT_QUOTES); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($category_err)) ? 'has-error' : ''; ?>">
                <label>Category</label>
                <select name="category" class="form-control">
                    <option value="">Select Category</option>
                    <option value="tools" <?php echo ($category === "tools") ? "selected" : ""; ?>>Tools</option>
                    <option value="books" <?php echo ($category === "books") ? "selected" : ""; ?>>Books</option>
                    <option value="electronics" <?php echo ($category === "electronics") ? "selected" : ""; ?>>Electronics</option>
                    <option value="garden" <?php echo ($category === "garden") ? "selected" : ""; ?>>Garden Equipment</option>
                    <option value="other" <?php echo ($category === "other") ? "selected" : ""; ?>>Other</option>
                </select>
                <span class="help-block"><?php echo htmlspecialchars($category_err, ENT_QUOTES); ?></span>
            </div>
            <div class="form-group">
                <label>Condition</label>
                <select name="condition_status" class="form-control">
                    <option value="new" <?php echo $condition_status==='new'?'selected':''; ?>>New</option>
                    <option value="like_new" <?php echo $condition_status==='like_new'?'selected':''; ?>>Like New</option>
                    <option value="good" <?php echo $condition_status==='good'?'selected':''; ?>>Good</option>
                    <option value="fair" <?php echo $condition_status==='fair'?'selected':''; ?>>Fair</option>
                    <option value="poor" <?php echo $condition_status==='poor'?'selected':''; ?>>Poor</option>
                </select>
            </div>
            <div class="form-group <?php echo (!empty($pickup_location_err)) ? 'has-error' : ''; ?>">
                <label>Pickup Location</label>
                <input type="text" name="pickup_location" class="form-control" value="<?php echo htmlspecialchars($pickup_location, ENT_QUOTES); ?>">
                <span class="help-block"><?php echo htmlspecialchars($pickup_location_err, ENT_QUOTES); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($image_err)) ? 'has-error' : ''; ?>">
                <label>Item Image (Optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <small class="help-block" style="color:#6b7280">Accepted: JPG, PNG, GIF, WEBP. Max 2MB.</small>
                <span class="help-block"><?php echo htmlspecialchars($image_err, ENT_QUOTES); ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Add Item">
                <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
    <?php render_footer(); ?>
</body>
</html>

