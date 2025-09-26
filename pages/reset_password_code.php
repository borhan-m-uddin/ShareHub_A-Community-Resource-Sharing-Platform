<?php
require_once __DIR__ . '/../bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
if ($uid <= 0) {
    header('Location: ' . site_href('pages/forgot_password.php'));
    exit;
}

$status = '';
$ok = true;
$consumed = false;

if (isset($_SESSION['flash_rpc_status'])) {
    $status = $_SESSION['flash_rpc_status'];
    unset($_SESSION['flash_rpc_status']);
}
if (isset($_SESSION['flash_rpc_ok'])) {
    $ok = (bool)$_SESSION['flash_rpc_ok'];
    unset($_SESSION['flash_rpc_ok']);
}
if (isset($_SESSION['flash_rpc_consumed'])) {
    $consumed = (bool)$_SESSION['flash_rpc_consumed'];
    unset($_SESSION['flash_rpc_consumed']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $status = 'Security token invalid.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['password_confirm'] ?? '';
        if ($code === '') {
            $status = 'Enter the 6-digit code sent to your email.';
        } elseif ($p1 === '') {
            $status = 'Please enter a password.';
        } elseif (strlen($p1) < 8) {
            $status = 'Password must have at least 8 characters.';
        } elseif (!preg_match('/\d/', $p1)) {
            $status = 'Password must contain at least one number.';
        } elseif ($p1 !== $p2) {
            $status = 'Passwords do not match.';
        } else {
            $validation = password_reset_validate($uid, $code);
            if ($validation['ok']) {
                if (password_reset_consume($uid, $validation['code'], $p1)) {
                    $consumed = true;
                    $ok = false;
                    $status = 'Password successfully reset. You can now login.';
                } else {
                    $status = 'Failed to reset password. Code may be invalid or expired.';
                }
            } else {
                switch ($validation['status']) {
                    case 'expired':
                        $status = 'Code expired. Request a new one.';
                        break;
                    case 'mismatch':
                        $status = 'Invalid code.';
                        break;
                    case 'used':
                        $status = 'Code already used. Request a new one.';
                        break;
                    case 'notfound':
                        $status = 'No active reset request. Request a new code.';
                        break;
                    default:
                        $status = 'Unable to validate code.';
                        break;
                }
            }
        }
    }
    $_SESSION['flash_rpc_status'] = $status;
    $_SESSION['flash_rpc_ok'] = $ok;
    $_SESSION['flash_rpc_consumed'] = $consumed;
    header('Location: reset_password_code.php?uid=' . $uid);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password (Code)</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php render_header(); ?>
    <div class="container" style="max-width:480px;margin:40px auto;">
        <h2>Reset Password</h2>
        <?php if ($status): ?>
            <div class="alert <?php echo $consumed ? 'alert-success' : ($ok ? 'alert-warning' : 'alert-danger'); ?>"><?php echo e($status); ?></div>
        <?php endif; ?>

        <?php if ($consumed): ?>
            <p><a class="btn" href="<?php echo site_href('pages/login.php'); ?>">Login</a></p>
        <?php elseif ($ok): ?>
            <form method="post" novalidate>
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="code">Reset Code</label>
                    <input type="text" name="code" id="code" required maxlength="6" pattern="[0-9]{6}" class="form-control" autofocus>
                </div>
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password" required minlength="8" pattern="(?=.*\d).{8,}" title="At least 8 characters with at least one number" class="form-control">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" name="password_confirm" id="password_confirm" required minlength="8" pattern="(?=.*\d).{8,}" title="At least 8 characters with at least one number" class="form-control">
                </div>
                <div class="form-group">
                    <button class="btn" type="submit">Set Password</button>
                    <a href="<?php echo site_href('pages/forgot_password.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <p><a class="btn" href="<?php echo site_href('pages/forgot_password.php'); ?>">Request new code</a></p>
        <?php endif; ?>
    </div>
</body>

</html>