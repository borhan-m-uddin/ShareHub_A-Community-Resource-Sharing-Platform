<?php
// bootstrap.php - central initialization: session, config, headers, helpers

// Apply secure session cookie settings before session_start
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

// Root directory helper
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}

// Ensure config is loaded (always include once)
require_once __DIR__ . '/config.php';

// db_connected helper (if provided in config.php, keep it; else define safe default)
if (!function_exists('db_connected')) {
    function db_connected(): bool {
        if (isset($GLOBALS['DB_OFFLINE']) && $GLOBALS['DB_OFFLINE'] === true) {
            return false;
        }
        if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
            return false;
        }
        return $GLOBALS['conn']->connect_errno === 0;
    }
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
// (Keep single PHP block to avoid accidental output)
// -------- Security Headers (CSP, Clickjacking, MIME sniffing, Referrer, Permissions) --------
if (!headers_sent()) {
    // Conservative CSP suitable for this app. Adjust if adding external assets.
    $csp = "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'";
    header('Content-Security-Policy: ' . $csp);
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
}

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
        // Rotate token only on success to prevent cascading failures in multi-check flows
        if ($ok) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $ok;
    }
}

// Lightweight email helper: try mail(); if it fails, log to storage/mail.log for debugging
if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $message, ?string $from = null): bool {
    // Pull SMTP config from constants, with settings.json fallback
    // Prefer settings.json unless a non-empty constant is explicitly provided
    $smtpHost = defined('SMTP_HOST') && constant('SMTP_HOST') !== '' ? (string)constant('SMTP_HOST') : (string)get_setting('smtp_host', '');
    $smtpUser = defined('SMTP_USER') && constant('SMTP_USER') !== '' ? (string)constant('SMTP_USER') : (string)get_setting('smtp_user', '');
    $smtpPass = defined('SMTP_PASS') && constant('SMTP_PASS') !== '' ? (string)constant('SMTP_PASS') : (string)get_setting('smtp_pass', '');
    $smtpPort = defined('SMTP_PORT') && (string)constant('SMTP_PORT') !== '' ? (int)constant('SMTP_PORT') : (int)get_setting('smtp_port', 587);
    $smtpFrom = defined('SMTP_FROM') && constant('SMTP_FROM') !== '' ? (string)constant('SMTP_FROM') : (string)get_setting('smtp_from', '');
    $smtpFromName = defined('SMTP_FROM_NAME') && constant('SMTP_FROM_NAME') !== '' ? (string)constant('SMTP_FROM_NAME') : (string)get_setting('smtp_from_name', '');
    $smtpSecure = defined('SMTP_SECURE') && constant('SMTP_SECURE') !== '' ? (string)constant('SMTP_SECURE') : (string)get_setting('smtp_secure', '');

    // Prefer configured FROM
    if ($from === null && $smtpFrom) { $from = $smtpFrom; }
        if ($from === null) {
            $from = 'no-reply@sharehub.local';
        }
        // Gmail requires the authenticated user as FROM unless a sender alias is configured
        if (stripos((string)$smtpHost, 'gmail.com') !== false) {
            $fromDomain = strtolower((string)substr(strrchr((string)$from, '@') ?: '', 1));
            $userDomain = strtolower((string)substr(strrchr((string)$smtpUser, '@') ?: '', 1));
            if ($smtpUser && $from && (strcasecmp($from, $smtpUser) !== 0) && $fromDomain !== $userDomain) {
                $from = $smtpUser; // align FROM to authenticated account for Gmail
            }
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

            // If PHPMailer not available or failed, try a minimal direct SMTP client (STARTTLS/SSL)
            if (!$sent) {
                $smtpOk = false;
                $host = $smtpHost;
                $port = (int)$smtpPort;
                $secure = strtolower((string)$smtpSecure);
                // On Windows, OpenSSL may lack a CA bundle; relax verification to allow dev emails
                $isWin = stripos(PHP_OS_FAMILY, 'Windows') !== false;
                $sslOpts = $isWin ? [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    'SNI_enabled' => true,
                ] : [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    'SNI_enabled' => true,
                ];
                $context = stream_context_create(['ssl' => $sslOpts]);
                $tryAttempt = function(string $host, int $port, string $secure) use ($smtpUser, $smtpPass, $smtpFrom, $smtpFromName, $from, $to, $subject, $message, $sslOpts) {
                    $result = ['ok' => false, 'error' => ''];
                    $transport = ($secure === 'ssl') ? 'ssl' : 'tcp';
                    $remote = sprintf('%s://%s:%d', $transport, $host, $port);
                    $errno = 0; $errstr = '';
                    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $sslOpts]));
                    if (!$fp) {
                        $result['error'] = "connect: {$errno} {$errstr}";
                        return $result;
                    }
                    stream_set_timeout($fp, 20);
                    $lastLine = '';
                    $read = function() use ($fp, &$lastLine) { $line = fgets($fp, 2048) ?: ''; $lastLine = $line; return $line; };
                    $write = function($data) use ($fp) { return fwrite($fp, $data); };
                    $expect = function($prefix) use ($read, &$lastLine) {
                        $line = '';
                        do { $line = $read(); } while ($line !== '' && isset($line[3]) && $line[3] === '-');
                        return strpos($line, $prefix) === 0;
                    };
                    if (!$expect('220')) { $result['error'] = 'banner: ' . trim($lastLine); @fclose($fp); return $result; }
                    $ehlo = 'EHLO sharehub.local\r\n';
                    $write($ehlo); $expect('250');
                    if ($secure === 'tls') {
                        $write("STARTTLS\r\n");
                        if (!$expect('220')) { $result['error'] = 'starttls: ' . trim($lastLine); @fclose($fp); return $result; }
                        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) { $result['error'] = 'tls-handshake'; @fclose($fp); return $result; }
                        $write($ehlo); $expect('250');
                    }
                    $write("AUTH LOGIN\r\n");
                    if (!$expect('334')) { $result['error'] = 'auth-login: ' . trim($lastLine); @fclose($fp); return $result; }
                    $write(base64_encode($smtpUser) . "\r\n");
                    if (!$expect('334')) { $result['error'] = 'auth-user: ' . trim($lastLine); @fclose($fp); return $result; }
                    $write(base64_encode(str_replace(' ', '', $smtpPass)) . "\r\n");
                    if (!$expect('235')) { $result['error'] = 'auth-pass: ' . trim($lastLine); @fclose($fp); return $result; }
                    $fromAddr = $from ?: $smtpFrom;
                    $write('MAIL FROM: <' . $fromAddr . ">\r\n"); if (!$expect('250')) { $result['error'] = 'mail-from: ' . trim($lastLine); @fclose($fp); return $result; }
                    $write('RCPT TO: <' . $to . ">\r\n"); if (!$expect('250')) { $result['error'] = 'rcpt-to: ' . trim($lastLine); @fclose($fp); return $result; }
                    $write("DATA\r\n"); if (!$expect('354')) { $result['error'] = 'data: ' . trim($lastLine); @fclose($fp); return $result; }
                    $fromHeaderName = $smtpFromName ? '"' . preg_replace('/[\r\n\"]+/', '', $smtpFromName) . '" ' : '';
                    $headers = '';
                    $headers .= 'From: ' . $fromHeaderName . '<' . $fromAddr . ">\r\n";
                    $headers .= 'To: <' . $to . ">\r\n";
                    $headers .= 'Subject: ' . $subject . "\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                    $body = $headers . $message . "\r\n.\r\n";
                    $write($body);
                    if (!$expect('250')) { $result['error'] = 'post-data: ' . trim($lastLine); @fclose($fp); return $result; }
                    $write("QUIT\r\n");
                    @fclose($fp);
                    $result['ok'] = true;
                    return $result;
                };

                // First try with configured settings
                $try1 = $tryAttempt($host, $port, $secure);
                if ($try1['ok']) { $smtpOk = true; }

                // If TLS:587 failed, try SSL:465 as a fallback (common for Gmail)
                if (!$smtpOk && ($secure === 'tls' || $port === 587)) {
                    $try2 = $tryAttempt($host, 465, 'ssl');
                    if ($try2['ok']) { $smtpOk = true; }
                    // Log both errors if both failed
                    if (!$smtpOk) {
                        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage'; if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                        $logFile = $dir . DIRECTORY_SEPARATOR . 'mail.log';
                        $entry = "[" . date('Y-m-d H:i:s') . "] SMTP send failed to {$to} via {$smtpHost}:{$smtpPort} ({$smtpSecure}) err1={$try1['error']} err2={$try2['error']}\n";
                        @file_put_contents($logFile, $entry, FILE_APPEND);
                    }
                } elseif (!$smtpOk) {
                    // Single attempt failed, log with error detail
                    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage'; if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                    $logFile = $dir . DIRECTORY_SEPARATOR . 'mail.log';
                    $entry = "[" . date('Y-m-d H:i:s') . "] SMTP send failed to {$to} via {$smtpHost}:{$smtpPort} ({$smtpSecure}) err={$try1['error']}\n";
                    @file_put_contents($logFile, $entry, FILE_APPEND);
                }

                if ($smtpOk) { $sent = true; }
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
if (!function_exists('e')) {
    // HTML-escape helper (quotes and UTF-8 safe)
    function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

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
// -------- Shared simple config constants (for reuse in selects/UI) ---------
if (!defined('ROLES')) {
    define('ROLES', ['admin','giver','seeker']);
}
if (!defined('ITEM_STATUSES')) {
    define('ITEM_STATUSES', ['available','pending','unavailable']);
}
if (!defined('SERVICE_AVAILABILITIES')) {
    define('SERVICE_AVAILABILITIES', ['available','busy','unavailable']);
}
if (!defined('REQUEST_STATUSES')) {
    define('REQUEST_STATUSES', ['pending','approved','rejected','completed']);
}

    function require_admin(): void {
        require_login();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ' . site_href('index.php'));
            exit;
        }
    }
}

// -------- Small reusable UI helpers (non-breaking) ---------
if (!function_exists('build_query')) {
    /**
     * Build a query string by merging current $_GET params with updates.
     * Pass a $base array to override the base params; by default uses $_GET.
     */
    function build_query(array $updates = [], ?array $base = null): string {
        $params = $base ?? (isset($_GET) && is_array($_GET) ? $_GET : []);
        foreach ($updates as $k => $v) {
            if ($v === null) { unset($params[$k]); } else { $params[$k] = $v; }
        }
        return http_build_query($params);
    }
}

if (!function_exists('render_pagination')) {
    /**
     * Render Prev/Next pagination controls identical to existing markup/logic.
     * - $page: current page (1-based)
     * - $perPage: items per page
     * - $shownCount: number of items on the current page
     * - $total: total items across all pages
     * - $extraParams: optional overrides to include in query (merged with $_GET)
     */
    function render_pagination(int $page, int $perPage, int $shownCount, int $total, array $extraParams = []): void {
        if ($total <= $perPage) { return; }
        $offset = ($page - 1) * $perPage;
        echo '<div style="margin-top:10px;display:flex;gap:8px;">';
        if ($page > 1) {
            $qs = build_query(array_merge($extraParams, ['page' => $page - 1]));
            echo '<a class="btn btn-default" href="?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') . '">Prev</a>';
        }
        if ($offset + $shownCount < $total) {
            $qs = build_query(array_merge($extraParams, ['page' => $page + 1]));
            echo '<a class="btn btn-default" href="?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') . '">Next</a>';
        }
        echo '</div>';
    }
}

// -------- Shared image upload helper ---------
if (!function_exists('upload_image_secure')) {
    /**
     * Validates and saves an uploaded image file.
     * - Enforces max size (bytes)
     * - Validates MIME via finfo/getimagesize
     * - Randomizes filename with allowed extension
     * - Optionally resizes to fit within maxWidth x maxHeight (requires GD)
     * Returns [ok=>bool, pathRel=>string|null, error=>string|null]
     */
    function upload_image_secure(array $file, string $targetSubdir = 'uploads/items', int $maxBytes = 2_000_000, int $maxWidth = 1600, int $maxHeight = 1200): array {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => 'The file exceeds server limit (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds form limit (MAX_FILE_SIZE).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.'
            ];
            return ['ok' => false, 'pathRel' => null, 'error' => ($map[$err] ?? ('Upload error (code '.$err.').'))];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Image size must be <= ' . (int)round($maxBytes/1024/1024) . 'MB.'];
        }
        $mime = null;
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = @finfo_file($fi, $tmp); @finfo_close($fi); }
        }
        if (!$mime && function_exists('getimagesize')) {
            $gi = @getimagesize($tmp);
            if (is_array($gi) && !empty($gi['mime'])) { $mime = $gi['mime']; }
        }
        if (!$mime && isset($file['type'])) { $mime = $file['type']; }
        $mime = $mime ? strtolower($mime) : '';
        $allowed = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg',
            'image/png'  => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        ];
        if (!$mime || !isset($allowed[$mime])) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Only JPG, PNG, GIF or WEBP images are allowed.'];
        }
        $ext = $allowed[$mime];
        $dirAbs = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace(['\\','/'], DIRECTORY_SEPARATOR, $targetSubdir);
        if (!is_dir($dirAbs)) { @mkdir($dirAbs, 0777, true); }
        try { $name = bin2hex(random_bytes(16)) . '.' . $ext; } catch (\Throwable $e) { $name = uniqid('', true) . '.' . $ext; }
        $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
        $destRel = rtrim($targetSubdir, '/\\') . '/' . $name;

        if (!@move_uploaded_file($tmp, $destAbs)) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Failed to save uploaded image.'];
        }

        // Try to strip metadata and resize if GD available (skip GIF to keep animation)
        $canProcess = function_exists('imagecreatetruecolor') && function_exists('getimagesize');
        if ($canProcess && $ext !== 'gif') {
            $info = @getimagesize($destAbs);
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                $w = (int)$info[0]; $h = (int)$info[1];
                $scale = min($maxWidth / max(1,$w), $maxHeight / max(1,$h), 1.0);
                if ($scale < 1.0) {
                    $newW = max(1, (int)round($w * $scale));
                    $newH = max(1, (int)round($h * $scale));
                    $dst = imagecreatetruecolor($newW, $newH);
                    // Load source
                    $src = null;
                    if ($ext === 'jpg') { $src = @imagecreatefromjpeg($destAbs); }
                    elseif ($ext === 'png') { $src = @imagecreatefrompng($destAbs); imagealphablending($dst,false); imagesavealpha($dst,true); }
                    elseif ($ext === 'webp') { $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($destAbs) : null; }
                    if ($src) {
                        @imagecopyresampled($dst, $src, 0,0,0,0, $newW,$newH, $w,$h);
                        // Re-encode without metadata
                        if ($ext === 'jpg') { @imagejpeg($dst, $destAbs, 85); }
                        elseif ($ext === 'png') { @imagepng($dst, $destAbs, 6); }
                        elseif ($ext === 'webp' && function_exists('imagewebp')) { @imagewebp($dst, $destAbs, 85); }
                        @imagedestroy($src);
                        @imagedestroy($dst);
                    }
                } else {
                    // For JPG/PNG/WEBP, re-encode to strip EXIF even if no resize
                    $dst = imagecreatetruecolor($w, $h);
                    $src = null;
                    if ($ext === 'jpg') { $src = @imagecreatefromjpeg($destAbs); }
                    elseif ($ext === 'png') { $src = @imagecreatefrompng($destAbs); imagealphablending($dst,false); imagesavealpha($dst,true); }
                    elseif ($ext === 'webp') { $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($destAbs) : null; }
                    if ($src) {
                        @imagecopy($dst, $src, 0,0,0,0, $w,$h);
                        if ($ext === 'jpg') { @imagejpeg($dst, $destAbs, 85); }
                        elseif ($ext === 'png') { @imagepng($dst, $destAbs, 6); }
                        elseif ($ext === 'webp' && function_exists('imagewebp')) { @imagewebp($dst, $destAbs, 85); }
                        @imagedestroy($src);
                        @imagedestroy($dst);
                    }
                }
            }
        }

        return ['ok' => true, 'pathRel' => $destRel, 'error' => null];
    }
}


?>
