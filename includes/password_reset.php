<?php
/**
 * Procedural password reset (OTP code) helpers.
 * Replaces legacy App\Security\PasswordReset class.
 */

if (!defined('PASSWORD_RESET_CODE_TTL')) { define('PASSWORD_RESET_CODE_TTL', 3600); } // 1 hour

/**
 * Begin a password reset request (idempotent, silent on unknown email).
 * Always returns true to avoid leaking which emails exist.
 */
function password_reset_create(string $email): bool {
    if (!function_exists('db_connected') || !db_connected()) return true; // pretend success
    global $conn;
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return true;

    // Basic throttle: one request per 60 seconds per session per email
    $now = time();
    if (!isset($_SESSION['pw_reset_requests'])) { $_SESSION['pw_reset_requests'] = []; }
    $key = strtolower($email);
    $last = $_SESSION['pw_reset_requests'][$key] ?? 0;
    if ($now - $last < 60) {
        // Silent skip (act like success) to avoid spamming email channel
        return true;
    }
    $_SESSION['pw_reset_requests'][$key] = $now;

    $root = defined('ROOT_DIR') ? ROOT_DIR : __DIR__;
    $logFile = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mail.log';

    // Ensure table exists (best effort) - if missing just log and return success (non-fatal)
    if (!$conn->query('SELECT 1 FROM password_resets LIMIT 1')) {
        @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] password_reset_create: table missing or unreadable (".$conn->error.")\n", FILE_APPEND);
        return true;
    }

    $uid = null; $username=''; $userEmail='';
    if ($stmt = $conn->prepare('SELECT user_id, username, email_verified, email FROM users WHERE email=? LIMIT 1')) {
        $stmt->bind_param('s', $email);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if ((int)$row['email_verified'] === 1) {
                    $uid = (int)$row['user_id'];
                    $username = (string)$row['username'];
                    $userEmail = (string)$row['email'];
                } else {
                    @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] password_reset_create: email '$email' not verified.\n", FILE_APPEND);
                }
            } else {
                @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] password_reset_create: email '$email' not found (silent).\n", FILE_APPEND);
            }
            if ($res) $res->free();
        }
        $stmt->close();
    }
    if ($uid === null) return true; // silent

    // Invalidate previous unused codes
    if ($stOld = $conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')) { $stOld->bind_param('i',$uid); $stOld->execute(); $stOld->close(); }

    $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_CODE_TTL);
    $base = function_exists('get_setting') ? (string)get_setting('app_url','') : '';
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme.'://'.$host.'/';
    }
    try { $raw = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); } catch (Throwable $e) { $raw = substr(bin2hex(random_bytes(4)),0,6); }

    if ($stmt2 = $conn->prepare('INSERT INTO password_resets (user_id,email,otp_code,expires_at) VALUES (?,?,?,?)')) {
        $stmt2->bind_param('isss', $uid, $userEmail, $raw, $expires);
        $ok = $stmt2->execute();
        $stmt2->close();
        if ($ok) {
            $link = rtrim($base,'/').'/pages/reset_password_code.php?uid='.urlencode((string)$uid);
            $escapedUser = htmlspecialchars($username ?: 'there', ENT_QUOTES, 'UTF-8');
            $bodyHtml = '<p style="margin:0 0 6px 0;">Hi '.$escapedUser.', your password reset code is:</p>'
                .'<h2 style="letter-spacing:1px;margin:4px 0 8px;">'.htmlspecialchars($raw, ENT_QUOTES, 'UTF-8').'</h2>'
                .'<p style="margin:0 0 6px 0;">Enter this code on the reset page: '
                .'<a href="'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">Reset Password Page</a></p>'
                .'<p style="margin:0 0 6px 0;">Expires in 1 hour. Request a new one if it stops working.</p>'
                .'<p style="margin:0;">If you didn\'t request this, ignore this email. — ShareHub</p>';
            $sent = false;
            if (function_exists('send_email')) { $sent = send_email($userEmail, 'Your ShareHub password reset code', $bodyHtml); }
            elseif (class_exists('App\\Mail\\Mailer')) { $sent = App\Mail\Mailer::send($userEmail, 'Your ShareHub password reset code', $bodyHtml); }
            if (!$sent) { @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] password_reset_create: send FAIL for '$userEmail'\n", FILE_APPEND); }
            else { @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] password_reset_create: send OK for '$userEmail'\n", FILE_APPEND); }
            return true; // always true for privacy
        } else {
            @file_put_contents($logFile, '['.date('Y-m-d H:i:s')."] password_reset_create: insert fail for '$email' (".$conn->error.")\n", FILE_APPEND);
            return true; // still silent
        }
    }
    return true;
}

/** Validate a user code. Returns array: ['ok'=>bool,'status'=>string,'code'=>?string] */
function password_reset_validate(int $userId, string $code): array {
    $res = ['ok'=>false,'status'=>'','code'=>null];
    if (!function_exists('db_connected') || !db_connected()) { $res['status']='db'; return $res; }
    global $conn; if ($userId<=0 || $code==='') { $res['status']='missing'; return $res; }
    if ($stmt = $conn->prepare('SELECT otp_code, expires_at, used_at FROM password_resets WHERE user_id=? AND used_at IS NULL ORDER BY id DESC LIMIT 1')) {
        $stmt->bind_param('i',$userId);
        if ($stmt->execute()) {
            $r = $stmt->get_result();
            if ($row = $r->fetch_assoc()) {
                $expTs = strtotime($row['expires_at']); $now = time();
                if ($row['used_at']) { $res['status']='used'; }
                elseif ($now > $expTs) { $res['status']='expired'; }
                elseif (!hash_equals($row['otp_code'], $code)) { $res['status']='mismatch'; }
                else { $res = ['ok'=>true,'status'=>'ok','code'=>$row['otp_code']]; }
            } else { $res['status']='notfound'; }
            if ($r) $r->free();
        }
        $stmt->close();
    }
    return $res;
}

/** Consume a valid code and update password */
function password_reset_consume(int $userId, string $code, string $newPassword): bool {
    if (!function_exists('db_connected') || !db_connected()) return false;
    global $conn; $ok=false; $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND otp_code=? AND used_at IS NULL');
        if ($stmt) {
            $stmt->bind_param('is',$userId,$code);
            $stmt->execute(); $affected=$stmt->affected_rows; $stmt->close();
            if ($affected===1) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                if ($stmt2 = $conn->prepare('UPDATE users SET password_hash=? WHERE user_id=?')) {
                    $stmt2->bind_param('si',$hash,$userId);
                    $ok = $stmt2->execute();
                    $stmt2->close();
                }
            }
        }
        if ($ok) { $conn->commit(); } else { $conn->rollback(); }
    } catch (Throwable $e) { $conn->rollback(); }
    return $ok;
}
