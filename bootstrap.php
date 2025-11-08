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

// Ensure config is loaded (always include once). Fallback inline if file missing (serverless packaging case).
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Inline copy from config.php (fallback when config.php not bundled)
    if (!defined('DB_SERVER'))    define('DB_SERVER', getenv('DB_SERVER') ?: getenv('DB_HOST') ?: 'sql12.freesqldatabase.com');
    if (!defined('DB_USERNAME'))  define('DB_USERNAME', getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'sql12806539');
    if (!defined('DB_PASSWORD'))  define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'KNCeSirxeR');
    if (!defined('DB_NAME'))      define('DB_NAME', getenv('DB_NAME') ?: 'sql12806539');
    if (!defined('DB_PORT'))      define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
    $GLOBALS['conn'] = $conn; // make accessible globally
    if ($conn && !$conn->connect_errno) {
        @mysqli_set_charset($conn, 'utf8mb4');
        $GLOBALS['DB_OFFLINE'] = false;
        $GLOBALS['DB_ERROR'] = null;
    } else {
        $GLOBALS['DB_OFFLINE'] = true;
        $GLOBALS['DB_ERROR'] = ($conn instanceof mysqli) ? $conn->connect_error : 'mysqli not initialized';
    }

    if (!function_exists('db_connected')) {
        function db_connected(): bool
        {
            if (isset($GLOBALS['DB_OFFLINE']) && $GLOBALS['DB_OFFLINE'] === true) {
                return false;
            }
            if (!isset($GLOBALS['conn']) || !$GLOBALS['conn'] instanceof mysqli) return false;
            $c = $GLOBALS['conn'];
            if ($c->connect_errno !== 0) return false;
            static $lastProbeOk = true;
            static $lastProbeAt = 0;
            $now = time();
            if (($now - $lastProbeAt) < 5) {
                return $lastProbeOk;
            }
            $lastProbeAt = $now;
            $probeOk = false;
            try {
                if (@$c->query('SELECT 1')) {
                    $probeOk = true;
                }
            } catch (Throwable $e) {
                $probeOk = false;
            }
            if (!$probeOk) {
                return ($lastProbeOk = false);
            }
            return ($lastProbeOk = true);
        }
    }

    if (!function_exists('db_reconnect_if_needed')) {
        function db_reconnect_if_needed(): bool
        {
            if (db_connected()) return true;
            try {
                $GLOBALS['conn'] = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
                if ($GLOBALS['conn'] && !$GLOBALS['conn']->connect_errno) {
                    @mysqli_set_charset($GLOBALS['conn'], 'utf8mb4');
                    $GLOBALS['DB_OFFLINE'] = false;
                    return true;
                }
            } catch (Throwable $e) {
            }
            $GLOBALS['DB_OFFLINE'] = true;
            return false;
        }
    }
}

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

// Finalize: always expose $conn via $GLOBALS and a local alias for included scripts
if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    $conn = $GLOBALS['conn'];
}

// End bootstrap (remaining wrappers moved to includes)
