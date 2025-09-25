<?php
require_once __DIR__ . '/../bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$statusMsg = '';
$sent = false;

if (isset($_SESSION['flash_fp_status'])) {
    $statusMsg = $_SESSION['flash_fp_status'];
    unset($_SESSION['flash_fp_status']);
}
if (isset($_SESSION['flash_fp_sent'])) {
    $sent = (bool)$_SESSION['flash_fp_sent'];
    unset($_SESSION['flash_fp_sent']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $statusMsg = 'Security token invalid. Please retry.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $statusMsg = 'Enter a valid email address.';
        } else {
            password_reset_create($email);
            $sent = true;
            $statusMsg = 'If that email is registered and verified, a reset code has been sent.';
        }
    }
    $_SESSION['flash_fp_status'] = $statusMsg;
    $_SESSION['flash_fp_sent'] = $sent;
    header('Location: forgot_password.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="container" style="max-width:480px;margin:40px auto;">
    <div class="page-top-actions">
    <a href="<?php echo site_href('pages/login.php'); ?>" class="btn btn-outline">← Back to login</a>
    </div>
    <h2>Forgot Password</h2>
    <?php if ($statusMsg): ?>
        <div class="alert <?php echo $sent ? 'alert-success':'alert-danger'; ?>"><?php echo e($statusMsg); ?></div>
    <?php endif; ?>
    <?php if (!$sent): ?>
    <form action="" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label for="email">Your account email</label>
            <input type="email" name="email" id="email" required class="form-control" maxlength="190" autofocus>
        </div>
        <div class="form-group">
            <button class="btn" type="submit">Send Reset Code</button>
        </div>
    </form>
    <?php else: ?>
    <p><a class="btn" href="<?php echo site_href('pages/login.php'); ?>">Return to Login</a></p>
    <?php endif; ?>
</div>
</body>
</html>
