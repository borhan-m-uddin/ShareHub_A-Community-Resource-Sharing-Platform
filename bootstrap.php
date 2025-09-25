<?php
// Core bootstrap: session, config, autoload, shared helpers.

// Secure session init (set cookie params before start)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    // Harden session behavior
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    // Compute HTTPS status
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,           // secure cookie only on HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    }
    session_start();
}

// Root directory constant
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}

// Ensure config is loaded (always include once)
require_once __DIR__ . '/config.php';

// Composer autoload
$__autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($__autoload)) { require_once $__autoload; }

// Load new procedural includes (step 1 of simplification)
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/password_reset.php';
require_once __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/messages.php';
require_once __DIR__ . '/includes/items.php';
require_once __DIR__ . '/includes/services.php';
require_once __DIR__ . '/includes/requests.php';

// End bootstrap (remaining wrappers moved to includes)
