<?php
require_once __DIR__ . '/../bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
header('Location: ' . site_href('pages/index.php'));
exit;
