<?php
// bootstrap.php - central initialization: start session safely, load config, and helpers
// Start session only if not started and headers are not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Root directory helper
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}

// Ensure config is loaded
if (!defined('DB_SERVER')) {
    require_once __DIR__ . '/config.php';
}

// Settings storage path
if (!defined('SETTINGS_FILE')) {
    define('SETTINGS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'settings.json');
}

// Settings helpers
if (!function_exists('get_settings_all')) {
    function get_settings_all(): array {
        if (isset($GLOBALS['__SETTINGS_CACHE'])) {
            return $GLOBALS['__SETTINGS_CACHE'];
        }
        $file = SETTINGS_FILE;
        if (!is_file($file)) {
            $GLOBALS['__SETTINGS_CACHE'] = [];
            return $GLOBALS['__SETTINGS_CACHE'];
        }
        $json = @file_get_contents($file);
        $data = json_decode((string)$json, true);
        $GLOBALS['__SETTINGS_CACHE'] = is_array($data) ? $data : [];
        return $GLOBALS['__SETTINGS_CACHE'];
    }
}

if (!function_exists('get_setting')) {
    function get_setting(string $key, $default = null) {
        $all = get_settings_all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }
}

if (!function_exists('save_settings')) {
    function save_settings(array $values, bool $merge = true): bool {
        $dir = dirname(SETTINGS_FILE);
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $current = $merge ? get_settings_all() : [];
        foreach ($values as $k => $v) {
            if ($v === null) { unset($current[$k]); } else { $current[$k] = $v; }
        }
        $tmp = $dir . DIRECTORY_SEPARATOR . 'settings.tmp.' . uniqid();
        $ok = @file_put_contents($tmp, json_encode($current, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) !== false;
        if ($ok) {
            $ok = @rename($tmp, SETTINGS_FILE);
        }
        // reset cache
        if ($ok) { unset($GLOBALS['__SETTINGS_CACHE']); }
        return $ok;
    }
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
<?php
// Lightweight email helper: try mail(); if it fails, log to storage/mail.log for debugging
// CSRF helpers
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $t . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): bool {
        $ok = is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        // Rotate token whether ok or not to reduce replay
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $ok;
    }
}

// Lightweight email helper: try mail(); if it fails, log to storage/mail.log for debugging
if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $message, ?string $from = null): bool {
    // Pull SMTP config from constants, with settings.json fallback
    $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : (string)get_setting('smtp_host', '');
    $smtpUser = defined('SMTP_USER') ? SMTP_USER : (string)get_setting('smtp_user', '');
    $smtpPass = defined('SMTP_PASS') ? SMTP_PASS : (string)get_setting('smtp_pass', '');
    $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : (int)get_setting('smtp_port', 587);
    $smtpFrom = defined('SMTP_FROM') ? SMTP_FROM : (string)get_setting('smtp_from', '');
    $smtpFromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (string)get_setting('smtp_from_name', '');
    $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : (string)get_setting('smtp_secure', '');

    // Prefer configured FROM
    if ($from === null && $smtpFrom) { $from = $smtpFrom; }
        if ($from === null) {
            $from = 'no-reply@sharehub.local';
        }

        // Attempt PHPMailer SMTP if available and credentials provided
        $sent = false;
    $haveCreds = ($smtpHost && $smtpUser && $smtpPass);
        if ($haveCreds) {
            // Try common autoload locations
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('PHPMailer')) {
                @include_once __DIR__ . '/vendor/autoload.php';
                if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('PHPMailer')) {
                    @include_once __DIR__ . '/includes/PHPMailerAutoload.php';
                }
            }
            try {
                $phClass = null;
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $phClass = 'PHPMailer\\PHPMailer\\PHPMailer';
                } elseif (class_exists('PHPMailer')) {
                    $phClass = 'PHPMailer';
                }
                if ($phClass) {
                    $mailer = new $phClass(true);
                    $mailer->isSMTP();
            $mailer->Host = $smtpHost;
            $mailer->Port = (int)$smtpPort;
                    $mailer->SMTPAuth = true;
            $mailer->Username = $smtpUser;
            $mailer->Password = str_replace(' ', '', $smtpPass);
            if ($smtpSecure) { $mailer->SMTPSecure = $smtpSecure; }
                    $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($from, $smtpFromName ?: '');
                    $mailer->addAddress($to);
                    $mailer->Subject = $subject;
                    $mailer->Body = $message;
                    $mailer->AltBody = $message;
                    $sent = $mailer->send();
                }
            } catch (\Throwable $e) {
                // Log PHPMailer failure details for diagnostics
                $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
                if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                $logFile = $dir . DIRECTORY_SEPARATOR . 'mail.log';
                $entry = "[" . date('Y-m-d H:i:s') . "] PHPMailer error: " . $e->getMessage() . "\n";
                @file_put_contents($logFile, $entry, FILE_APPEND);
                $sent = false;
            }
        }

        if (!$sent) {
            // On Windows, configure SMTP host/port dynamically if provided
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                if ($smtpHost) { @ini_set('SMTP', $smtpHost); }
                if ($smtpPort) { @ini_set('smtp_port', (string)$smtpPort); }
                if ($from) { @ini_set('sendmail_from', $from); }
            }

            // Optional friendly from name
            $fromHeader = $from;
            if ($smtpFromName) {
                // Remove quotes/newlines to prevent header injection
                $name = preg_replace('/[\r\n\"]+/', '', (string)$smtpFromName);
                $fromHeader = '"' . $name . '" <' . $from . '>';
            }

            $headers = "From: {$fromHeader}\r\n" .
                       "MIME-Version: 1.0\r\n" .
                       "Content-Type: text/plain; charset=UTF-8\r\n" .
                       "X-Mailer: PHP/" . phpversion();
            $sent = @mail($to, $subject, $message, $headers);
        }
        if (!$sent) {
            // Fallback: write the email content to a log file for development
            $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $logFile = $dir . DIRECTORY_SEPARATOR . 'mail.log';
            $entry = "[" . date('Y-m-d H:i:s') . "]\nTO: {$to}\nSUBJECT: {$subject}\nMESSAGE:\n{$message}\n----\n";
            @file_put_contents($logFile, $entry, FILE_APPEND);
        }
        return $sent;
    }
}

// -------- Path & layout helpers ---------
if (!function_exists('path_prefix')) {
    function path_prefix(): string {
        // Compute how many levels deep the current script is relative to ROOT
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $dir = trim(dirname($script), '/');
        if ($dir === '' || $dir === '.') return '';
        $depth = substr_count($dir, '/');
        // One ../ per level
        return str_repeat('../', $depth + 0); // +0 for clarity; adjust if app root served from project root
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        // Use root-absolute URLs so links work from any nested directory
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('site_href')) {
    function site_href(string $path): string {
        // Pages are also root-absolute
        return asset_url($path);
    }
}

if (!function_exists('render_header')) {
    function render_header(): void {
    include ROOT_DIR . '/header.php';
    }
}

if (!function_exists('render_footer')) {
    function render_footer(): void {
        if (file_exists(ROOT_DIR . '/footer.php')) {
            include ROOT_DIR . '/footer.php';
        } else {
            echo "</main></body></html>"; // minimal fallback
        }
    }
}

if (!function_exists('require_login')) {
    function require_login(): void {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header('Location: ' . site_href('index.php'));
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void {
        require_login();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ' . site_href('index.php'));
            exit;
        }
    }
}

?>
