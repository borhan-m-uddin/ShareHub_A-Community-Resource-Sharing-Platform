<?php
require_once __DIR__ . '/bootstrap.php';

// -----------------------------------------------------------------------------
// Email Verification Endpoint
// Accepts GET params: uid (int), token (raw token sent via email)
// Tries table-based verification first (verification_tokens), then falls back to
// legacy inline columns still present on users table (if any) for backward compat.
// -----------------------------------------------------------------------------

$uid   = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$debug = isset($_GET['debug']); // optional ?debug=1 to surface limited technical hints

$status = '';
$ok     = false;

if ($uid <= 0 || $token === '' || !db_connected()) {
    $status = 'Missing or invalid verification data.';
} else {
    global $conn;
    $hash = hash('sha256', $token);

    // 1. Short‑circuit if already verified.
    if ($stmtUser = $conn->prepare('SELECT email_verified FROM users WHERE user_id = ? LIMIT 1')) {
        $stmtUser->bind_param('i', $uid);
        if ($stmtUser->execute()) {
            $resUser = $stmtUser->get_result();
            if ($u = $resUser->fetch_assoc()) {
                if ((int)$u['email_verified'] === 1) {
                    $status = 'Your email is already verified.';
                    $ok = true;
                }
            }
            if ($resUser) { $resUser->free(); }
        }
        $stmtUser->close();
    }

    // 2. Attempt table-based token validation (sole source of truth).
    if (!$ok) {
        $foundTokenRow = false;
        if ($stmtTok = $conn->prepare('SELECT id, token_hash, expires_at, used_at FROM verification_tokens WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1')) {
            $stmtTok->bind_param('i', $uid);
            if ($stmtTok->execute()) {
                $resTok = $stmtTok->get_result();
                if ($rowTok = $resTok->fetch_assoc()) {
                    $foundTokenRow = true;
                    $dbHash = (string)$rowTok['token_hash'];
                    $expTs  = strtotime($rowTok['expires_at']);
                    $now    = time();

                    if ($rowTok['used_at']) {
                        $status = 'Token already used. Request a new one.';
                    } elseif ($now > $expTs) {
                        $status = 'This verification link has expired. Please request a new one.';
                    } elseif (!hash_equals($dbHash, $hash)) {
                        $status = 'Invalid verification token.';
                    } else {
                        if (verification_mark_verified($uid, $dbHash)) {
                            $status = 'Email successfully verified. You can now login.';
                            $ok = true;
                        } else {
                            $status = 'Failed to mark as verified. Try later.';
                        }
                    }
                }
                if ($resTok) { $resTok->free(); }
            }
            $stmtTok->close();
        }

        // (Legacy fallback removed: users table columns deprecated)
    }

    // 4. If still no status message, supply generic guidance.
    if ($status === '') {
        $status = 'No active verification token. Please request a new one.';
    }
}

// Optional debug output (safe, no sensitive data) appended to status when ?debug=1
if ($debug && !$ok) {
    $status .= ' [debug:view=' . ($ok ? 'ok' : 'pending') . ']';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Email Verification</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="container" style="max-width:640px;margin:40px auto;">
    <h2>Email Verification</h2>
    <div class="alert <?php echo $ok ? 'alert-success' : 'alert-danger'; ?>"><?php echo e($status); ?></div>
    <?php if ($ok): ?>
        <p><a class="btn" href="login.php">Go to Login</a></p>
    <?php else: ?>
        <p>
            <a class="btn" href="verify_notice.php?uid=<?php echo htmlspecialchars((string)$uid, ENT_QUOTES, 'UTF-8'); ?>">Request new link</a>
            <a class="btn btn-outline" href="login.php">Back to login</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
