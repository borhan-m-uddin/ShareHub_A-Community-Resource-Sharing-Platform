<?php
// CSRF + verification + password reset wrappers.

// CSRF
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            try { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
            catch (Throwable $e) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
}
if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token = null): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') return true;
        $sent = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
    }
}

// Email verification (fully procedural implementation)
if (!function_exists('verification_generate_and_send')) {
    function verification_generate_and_send(int $userId, string $email, string $username = ''): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn;
        // Short-circuit if already verified
        if ($st = $conn->prepare('SELECT email_verified FROM users WHERE user_id=? LIMIT 1')) {
            $st->bind_param('i', $userId);
            if ($st->execute()) { $res=$st->get_result(); if($row=$res->fetch_assoc()){ if((int)$row['email_verified']===1){ $st->close(); return true; }} if($res){$res->free();} }
            $st->close();
        }
    // Invalidate old tokens (best-effort)
    if ($stOld = $conn->prepare('UPDATE verification_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')) { $stOld->bind_param('i',$userId); $stOld->execute(); $stOld->close(); }
        try { $raw = bin2hex(random_bytes(32)); } catch (Throwable $e) { $raw = bin2hex(random_bytes(16)); }
        $hash = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() + 24*3600);
        if ($st2 = $conn->prepare('INSERT INTO verification_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)')) {
            $st2->bind_param('iss', $userId, $hash, $expires); $ok=$st2->execute(); $st2->close();
            if ($ok) {
                $base = function_exists('get_setting') ? (string)get_setting('app_url','') : '';
                if ($base==='') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https':'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $base = $scheme . '://' . $host . '/';
                }
                $base = rtrim($base,'/') . '/';
                // Use pages/ prefix to reflect relocated page scripts
                $link = $base . 'pages/verify.php?uid=' . urlencode((string)$userId) . '&token=' . urlencode($raw);
                $escapedUser = htmlspecialchars($username ?: 'there', ENT_QUOTES, 'UTF-8');
                $bodyHtml = '<p>Hi ' . $escapedUser . ',</p>'
                    . '<p>Please verify your ShareHub account by clicking this link:<br>'
                    . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Verify Email</a></p>'
                    . '<p>If the button does not work, copy and paste this URL into your browser:<br>'
                    . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p>This link expires in 24 hours.<br>If you did not create an account, you can ignore this email.</p>'
                    . '<p>— ShareHub</p>';
                if (function_exists('send_email')) { return send_email($email, 'Verify your ShareHub account', $bodyHtml); }
            }
        }
        return false;
    }
}
if (!function_exists('verification_mark_verified')) {
    function verification_mark_verified(int $userId, ?string $tokenHash = null): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return false; global $conn; $ok=false;
        if ($st = $conn->prepare('UPDATE users SET email_verified=1 WHERE user_id=?')) { $st->bind_param('i',$userId); $ok=$st->execute(); $st->close(); }
        if ($ok && $tokenHash) { if ($st2=$conn->prepare('UPDATE verification_tokens SET used_at=NOW() WHERE user_id=? AND token_hash=? AND used_at IS NULL')) { $st2->bind_param('is',$userId,$tokenHash); $st2->execute(); $st2->close(); }}
        return $ok;
    }
}
if (!function_exists('verification_is_verified')) {
    function verification_is_verified(int $userId): bool
    {
        if (!function_exists('db_connected') || !db_connected()) return true; global $conn;
        if ($st=$conn->prepare('SELECT email_verified FROM users WHERE user_id=? LIMIT 1')) { $st->bind_param('i',$userId); if($st->execute()){ $res=$st->get_result(); if($row=$res->fetch_assoc()){ $v=((int)$row['email_verified']===1); $st->close(); return $v; }} $st->close(); }
        return false;
    }
}
if (!function_exists('verification_require')) {
    function verification_require(): void
    {
        if (!isset($_SESSION['user_id'])) return; if (!verification_is_verified((int)$_SESSION['user_id'])) { header('Location: ' . site_href('verify_notice.php?uid=' . (int)$_SESSION['user_id'])); exit; }
    }
}

// Password reset logic now lives in `includes/password_reset.php` (loaded via bootstrap)
