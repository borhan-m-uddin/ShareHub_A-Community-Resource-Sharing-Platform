<?php
require_once __DIR__ . '/../bootstrap.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'giver') {
    header('Location: ' . site_href('pages/login.php'));
    exit;
}

$title = $description = $category = $expertise_level = $availability = $preferred_exchange = '';
$title_err = $description_err = $category_err = $expertise_level_err = $availability_err = $preferred_exchange_err = '';

// Generate a one-time form token for duplicate submission protection
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    try {
        $_SESSION['form_token_add_service'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['form_token_add_service'] = bin2hex(random_bytes(8));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $title_err = 'Invalid request. Refresh page.';
    } else {
        // Enforce one-time form token
        $sent_form_token = (string)($_POST['form_token'] ?? '');
        $stored_form_token = (string)($_SESSION['form_token_add_service'] ?? '');
        unset($_SESSION['form_token_add_service']);
        if ($sent_form_token === '' || $stored_form_token === '' || !hash_equals($stored_form_token, $sent_form_token)) {
            $title_err = 'Duplicate or invalid submission detected. Please try again.';
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') $title_err = 'Please enter a title for the service.';
        elseif (mb_strlen($title) > 150) $title_err = 'Title must be 150 characters or less.';
        $description = trim((string)($_POST['description'] ?? ''));
        if ($description === '') $description_err = 'Please enter a description for the service.';
        elseif (mb_strlen($description) > 2000) $description_err = 'Description must be 2000 characters or less.';
        $category = trim((string)($_POST['category'] ?? ''));
        if ($category === '') $category_err = 'Please select a category.';
        $expertise_level = trim((string)($_POST['expertise_level'] ?? ''));
        if ($expertise_level === '') $expertise_level_err = 'Please enter your expertise level.';
        elseif (mb_strlen($expertise_level) > 100) $expertise_level_err = 'Expertise must be 100 characters or less.';
        $availability = trim((string)($_POST['availability'] ?? ''));
        if ($availability === '') $availability_err = 'Please specify your availability.';
        elseif (mb_strlen($availability) > 2000) $availability_err = 'Availability must be 2000 characters or less.';
        $preferred_exchange = trim((string)($_POST['preferred_exchange'] ?? ''));
        if ($preferred_exchange === '') $preferred_exchange_err = 'Please specify preferred exchange terms.';
        elseif (mb_strlen($preferred_exchange) > 1000) $preferred_exchange_err = 'Preferred exchange must be 1000 characters or less.';
        // Short-window duplicate guard by payload hash
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $payload_hash = hash('sha256', $uid . '|' . $title . '|' . $description . '|' . $category . '|' . $expertise_level . '|' . $availability . '|' . $preferred_exchange);
        $now = time();
        $last_hash = $_SESSION['last_add_service_hash'] ?? '';
        $last_time = (int)($_SESSION['last_add_service_time'] ?? 0);
        $withinWindow = ($now - $last_time) <= 15;
        $isDuplicatePayload = ($payload_hash !== '' && $payload_hash === $last_hash && $withinWindow);

        if (!$title_err && !$description_err && !$category_err && !$expertise_level_err && !$availability_err && !$preferred_exchange_err && !$isDuplicatePayload) {
            $sql = 'INSERT INTO services (giver_id,title,description,category,expertise_level,availability,preferred_exchange) VALUES (?,?,?,?,?,?,?)';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('issssss', $uid, $title, $description, $category, $expertise_level, $availability, $preferred_exchange);
                if ($stmt->execute()) {
                    header('Location: ' . site_href('pages/dashboard.php'));
                    exit;
                } else {
                    $title_err = 'Something went wrong. Try again later.';
                }
                $stmt->close();
            } else {
                $title_err = 'Database error.';
            }
        } elseif ($isDuplicatePayload) {
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
    <title>Add New Service</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <div class="page-top-actions"><a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">← Back</a></div>
        <h2>Add New Service</h2>
        <p>Please fill this form to offer a new service for sharing.</p>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_token" value="<?php echo e($_SESSION['form_token_add_service'] ?? ''); ?>" />
            <div class="form-group <?php echo $title_err ? 'has-error' : ''; ?>">
                <label>Service Title</label>
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
                    <?php foreach (['tutoring' => 'Tutoring', 'repairs' => 'Minor Repairs', 'gardening' => 'Gardening', 'tech_support' => 'Tech Support', 'other' => 'Other'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $category === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="help-block"><?php echo htmlspecialchars($category_err); ?></span>
            </div>
            <div class="form-group <?php echo $expertise_level_err ? 'has-error' : ''; ?>">
                <label>Expertise Level</label>
                <input type="text" name="expertise_level" class="form-control" value="<?php echo htmlspecialchars($expertise_level); ?>" placeholder="e.g., Beginner, Intermediate, Advanced">
                <span class="help-block"><?php echo htmlspecialchars($expertise_level_err); ?></span>
            </div>
            <div class="form-group <?php echo $availability_err ? 'has-error' : ''; ?>">
                <label>Availability</label>
                <textarea name="availability" class="form-control" placeholder="e.g., Weekends, Mon-Wed evenings"><?php echo htmlspecialchars($availability); ?></textarea>
                <span class="help-block"><?php echo htmlspecialchars($availability_err); ?></span>
            </div>
            <div class="form-group <?php echo $preferred_exchange_err ? 'has-error' : ''; ?>">
                <label>Preferred Exchange Terms</label>
                <textarea name="preferred_exchange" class="form-control" placeholder="e.g., Barter for gardening help, small fee, goodwill"><?php echo htmlspecialchars($preferred_exchange); ?></textarea>
                <span class="help-block"><?php echo htmlspecialchars($preferred_exchange_err); ?></span>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="btnAddService">Add Service</button>
                <a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
        <script>
            (function() {
                var f = document.querySelector('form');
                var btn = document.getElementById('btnAddService');
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