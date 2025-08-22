<?php
// bootstrap.php - central initialization: start session safely, load config, and helpers
// Start session only if not started and headers are not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Ensure config is loaded
if (!defined('DB_SERVER')) {
    require_once __DIR__ . '/config.php';
}

// Simple flash helper
function flash_set($key, $message) {
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    $_SESSION['flash_' . $key] = $message;
    return true;
}

function flash_get($key) {
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    $k = 'flash_' . $key;
    if (isset($_SESSION[$k])) {
        $m = $_SESSION[$k];
        unset($_SESSION[$k]);
        return $m;
    }
    return null;
}

?>
