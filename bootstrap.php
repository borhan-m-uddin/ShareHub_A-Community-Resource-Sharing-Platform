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
if (file_exists($__autoload)) {
    require_once $__autoload;
}

if (!function_exists('send_email')) {
    // send_email with multi-strategy fallback (PHPMailer -> raw SMTP -> mail() -> log)
    function send_email(string $to, string $subject, string $message, ?string $from = null): bool
    {
        // Pull SMTP config (constants override settings.json if non-empty)
        $smtpHost       = defined('SMTP_HOST') && constant('SMTP_HOST') !== '' ? (string)constant('SMTP_HOST') : (string)get_setting('smtp_host', '');
        $smtpUser       = defined('SMTP_USER') && constant('SMTP_USER') !== '' ? (string)constant('SMTP_USER') : (string)get_setting('smtp_user', '');
        $smtpPass       = defined('SMTP_PASS') && constant('SMTP_PASS') !== '' ? (string)constant('SMTP_PASS') : (string)get_setting('smtp_pass', '');
        $smtpPort       = defined('SMTP_PORT') && (string)constant('SMTP_PORT') !== '' ? (int)constant('SMTP_PORT') : (int)get_setting('smtp_port', 587);
        $smtpFrom       = defined('SMTP_FROM') && constant('SMTP_FROM') !== '' ? (string)constant('SMTP_FROM') : (string)get_setting('smtp_from', '');
        $smtpFromName   = defined('SMTP_FROM_NAME') && constant('SMTP_FROM_NAME') !== '' ? (string)constant('SMTP_FROM_NAME') : (string)get_setting('smtp_from_name', '');
        $smtpSecure     = defined('SMTP_SECURE') && constant('SMTP_SECURE') !== '' ? (string)constant('SMTP_SECURE') : (string)get_setting('smtp_secure', '');
        $smtpLocalDomain = (string)get_setting('smtp_local_domain', 'sharehub.local'); // configurable HELO/EHLO domain
        $smtpDebugOn    = (bool)get_setting('smtp_debug', false);

        // Basic sanitisation of local domain (must contain a dot, fallback if not)
        if (strpos($smtpLocalDomain, '.') === false) {
            $smtpLocalDomain = 'localhost.localdomain';
        }
        $smtpLocalDomain = preg_replace('/[^A-Za-z0-9.-]/', '', $smtpLocalDomain) ?: 'localhost.localdomain';

        if ($from === null && $smtpFrom) {
            $from = $smtpFrom;
        }
        if ($from === null) {
            $from = 'no-reply@' . (strpos($smtpLocalDomain, '.') !== false ? $smtpLocalDomain : 'sharehub.local');
        }

        // Gmail: enforce matching from address domain
        if ($smtpHost && stripos($smtpHost, 'gmail.com') !== false) {
            $fromDomain = strtolower(substr(strrchr($from, '@') ?: '', 1));
            $userDomain = strtolower(substr(strrchr($smtpUser, '@') ?: '', 1));
            if ($smtpUser && $fromDomain !== $userDomain) {
                $from = $smtpUser;
            }
        }

        $storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        $logFile = $storageDir . DIRECTORY_SEPARATOR . 'mail.log';

        $sent = false;
        $haveCreds = ($smtpHost && $smtpUser && $smtpPass);

        // 1. PHPMailer
        if ($haveCreds) {
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !class_exists('PHPMailer')) {
                @include_once __DIR__ . '/vendor/autoload.php';
            }
            try {
                $phClass = class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'PHPMailer\\PHPMailer\\PHPMailer' : (class_exists('PHPMailer') ? 'PHPMailer' : null);
                if ($phClass) {
                    $debugBuffer = '';
                    $mailer = new $phClass(true);
                    $mailer->isSMTP();
                    $mailer->Host       = $smtpHost;
                    $mailer->Port       = (int)$smtpPort;
                    $mailer->SMTPAuth   = true;
                    $mailer->Username   = $smtpUser;
                    $mailer->Password   = $smtpPass; // keep exact (Gmail app password has no spaces anyway)
                    if ($smtpSecure) {
                        $mailer->SMTPSecure = $smtpSecure;
                    }
                    $mailer->CharSet    = 'UTF-8';
                    $mailer->Hostname   = $smtpLocalDomain; // influences EHLO
                    if ($smtpDebugOn) {
                        $mailer->SMTPDebug = 2; // verbose
                        $mailer->Debugoutput = function ($str) use (&$debugBuffer) {
                            $debugBuffer .= $str . "\n";
                        };
                    }
                    $mailer->setFrom($from, $smtpFromName ?: '');
                    $mailer->addAddress($to);
                    $mailer->Subject = $subject;
                    // Detect simple HTML content (anchor or block tags) and configure accordingly
                    $isHtml = (strpos($message, '<a ') !== false) || (stripos($message, '<html') !== false) || (strpos($message, '<p') !== false) || (strpos($message, '<br') !== false);
                    if ($isHtml) {
                        $mailer->isHTML(true);
                        $mailer->Body = $message;
                        // Basic plaintext alternative
                        $alt = preg_replace('/<br\s*\/?>(\r?\n)?/i', "\n", $message);
                        $alt = strip_tags($alt);
                        $mailer->AltBody = html_entity_decode($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    } else {
                        $mailer->Body    = $message;
                        $mailer->AltBody = $message;
                    }
                    $sent = $mailer->send();
                    if (!$sent) {
                        $errEntry = '[' . date('Y-m-d H:i:s') . '] PHPMailer error: ' . $mailer->ErrorInfo . "\n";
                        if ($debugBuffer) {
                            $errEntry .= rtrim($debugBuffer) . "\n";
                        }
                        @file_put_contents($logFile, $errEntry, FILE_APPEND);
                    }
                }
            } catch (\Throwable $e) {
                $errEntry = '[' . date('Y-m-d H:i:s') . '] PHPMailer exception: ' . $e->getMessage() . "\n";
                @file_put_contents($logFile, $errEntry, FILE_APPEND);
            }
        }

        // 2. Direct minimal SMTP (only if PHPMailer path failed and credentials exist)
        if (!$sent && $haveCreds) {
            $smtpOk = false;
            $secure = strtolower($smtpSecure);
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
            $tryAttempt = function (string $host, int $port, string $secure) use ($smtpUser, $smtpPass, $smtpFrom, $smtpFromName, $from, $to, $subject, $message, $sslOpts, $smtpLocalDomain) {
                $result = ['ok' => false, 'error' => ''];
                $transport = ($secure === 'ssl') ? 'ssl' : 'tcp';
                $remote = sprintf('%s://%s:%d', $transport, $host, $port);
                $errno = 0;
                $errstr = '';
                $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $sslOpts]));
                if (!$fp) {
                    $result['error'] = "connect: {$errno} {$errstr}";
                    return $result;
                }
                stream_set_timeout($fp, 20);
                $lastLine = '';
                $read = function () use ($fp, &$lastLine) {
                    $line = fgets($fp, 2048) ?: '';
                    $lastLine = $line;
                    return $line;
                };
                $write = function ($data) use ($fp) {
                    return fwrite($fp, $data);
                };
                $expect = function ($prefix) use ($read, &$lastLine) {
                    $line = '';
                    do {
                        $line = $read();
                    } while ($line !== '' && isset($line[3]) && $line[3] === '-');
                    return strpos($line, $prefix) === 0;
                };
                if (!$expect('220')) {
                    $result['error'] = 'banner: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $ehlo = 'EHLO ' . $smtpLocalDomain . "\r\n";
                $write($ehlo);
                $expect('250');
                if ($secure === 'tls') {
                    $write("STARTTLS\r\n");
                    if (!$expect('220')) {
                        $result['error'] = 'starttls: ' . trim($lastLine);
                        @fclose($fp);
                        return $result;
                    }
                    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                        $result['error'] = 'tls-handshake';
                        @fclose($fp);
                        return $result;
                    }
                    $write($ehlo);
                    $expect('250');
                }
                $write("AUTH LOGIN\r\n");
                if (!$expect('334')) {
                    $result['error'] = 'auth-login: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $write(base64_encode($smtpUser) . "\r\n");
                if (!$expect('334')) {
                    $result['error'] = 'auth-user: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $write(base64_encode($smtpPass) . "\r\n");
                if (!$expect('235')) {
                    $result['error'] = 'auth-pass: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $fromAddr = $from ?: $smtpFrom;
                $write('MAIL FROM: <' . $fromAddr . ">\r\n");
                if (!$expect('250')) {
                    $result['error'] = 'mail-from: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $write('RCPT TO: <' . $to . ">\r\n");
                if (!$expect('250')) {
                    $result['error'] = 'rcpt-to: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $write("DATA\r\n");
                if (!$expect('354')) {
                    $result['error'] = 'data: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $fromHeaderName = $smtpFromName ? '"' . preg_replace('/[\r\n\"]+/', '', $smtpFromName) . '" ' : '';
                $headers = '';
                $headers .= 'From: ' . $fromHeaderName . '<' . $fromAddr . ">\r\n";
                $headers .= 'To: <' . $to . ">\r\n";
                $headers .= 'Subject: ' . $subject . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                $body = $headers . $message . "\r\n.\r\n";
                $write($body);
                if (!$expect('250')) {
                    $result['error'] = 'post-data: ' . trim($lastLine);
                    @fclose($fp);
                    return $result;
                }
                $write("QUIT\r\n");
                @fclose($fp);
                $result['ok'] = true;
                return $result;
            };
            $try1 = $tryAttempt($smtpHost, $smtpPort, $secure);
            if ($try1['ok']) {
                $smtpOk = true;
            }
            if (!$smtpOk && ($secure === 'tls' || $smtpPort === 587)) {
                $try2 = $tryAttempt($smtpHost, 465, 'ssl');
                if ($try2['ok']) {
                    $smtpOk = true;
                }
            }
            if (!$smtpOk) {
                $err = '[' . date('Y-m-d H:i:s') . '] SMTP send failed to ' . $to . ' via ' . $smtpHost . ':' . $smtpPort . ' (' . $smtpSecure . ')';
                $err .= ' err1=' . ($try1['error'] ?? '');
                if (isset($try2)) {
                    $err .= ' err2=' . ($try2['error'] ?? '');
                }
                $err .= "\n";
                @file_put_contents($logFile, $err, FILE_APPEND);
            } else {
                $sent = true;
            }
        }

        // 3. PHP mail() fallback
        if (!$sent) {
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                if ($smtpHost) {
                    @ini_set('SMTP', $smtpHost);
                }
                if ($smtpPort) {
                    @ini_set('smtp_port', (string)$smtpPort);
                }
                if ($from) {
                    @ini_set('sendmail_from', $from);
                }
            }
            $fromHeader = $from;
            if ($smtpFromName) {
                $name = preg_replace('/[\r\n\"]+/', '', (string)$smtpFromName);
                $fromHeader = '"' . $name . '" <' . $from . '>';
            }
            $headers = "From: {$fromHeader}\r\n" .
                "MIME-Version: 1.0\r\n" .
                "Content-Type: text/plain; charset=UTF-8\r\n" .
                "X-Mailer: PHP/" . phpversion();
            $sent = @mail($to, $subject, $message, $headers);
        }

        // 4. Always log if not sent (development visibility)
        if (!$sent) {
            $entry = '[' . date('Y-m-d H:i:s') . "]\nTO: {$to}\nSUBJECT: {$subject}\nMESSAGE:\n{$message}\n----\n";
            @file_put_contents($logFile, $entry, FILE_APPEND);
        }
        return $sent;
    }
}
// Settings (JSON-backed)
if (!function_exists('get_setting')) {
    function get_setting(string $key, $default = null)
    {
        static $cache = null; // in-request cache
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'settings.json';
        if ($cache === null) {
            if (is_file($file)) {
                $json = @file_get_contents($file);
                $data = json_decode((string)$json, true);
                $cache = is_array($data) ? $data : [];
            } else {
                $cache = [];
            }
        }
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }
}
if (!function_exists('save_setting')) {
    function save_setting(string $key, $value): bool
    {
        static $cache = null;
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'settings.json';
        if ($cache === null) {
            if (is_file($file)) {
                $json = @file_get_contents($file);
                $data = json_decode((string)$json, true);
                $cache = is_array($data) ? $data : [];
            } else {
                $cache = [];
            }
        }
        $cache[$key] = $value;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0777, true);
        }
        return (bool)@file_put_contents($file, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
// Bulk settings helpers
if (!function_exists('get_settings_all')) {
    function get_settings_all(): array
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'settings.json';
        if (is_file($file)) {
            $json = @file_get_contents($file);
            $data = json_decode((string)$json, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }
}
if (!function_exists('save_settings')) {
    function save_settings(array $data, bool $merge = true): bool
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'settings.json';
        $existing = $merge ? get_settings_all() : [];
        $merged = $merge ? array_merge($existing, $data) : $data;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0777, true);
        }
        return (bool)@file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

// Flash messages
if (!function_exists('flash_set')) {
    function flash_set(string $key, $value): void
    {
        $_SESSION['__flash'][$key] = $value;
    }
}
if (!function_exists('flash_get')) {
    function flash_get(string $key, $default = null)
    {
        if (!isset($_SESSION['__flash'][$key])) return $default;
        $val = $_SESSION['__flash'][$key];
        unset($_SESSION['__flash'][$key]);
        return $val;
    }
}

// CSRF
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Throwable $e) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            }
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
}
if (!function_exists('csrf_verify')) {
    function csrf_verify(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') return true; // idempotent safe
        $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($sent) && hash_equals(csrf_token(), $sent);
    }
}

// Path & layout helpers
if (!function_exists('e')) {
    // HTML-escape helper (quotes and UTF-8 safe)
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('path_prefix')) {
    function path_prefix(): string
    {
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
    function asset_url(string $path): string
    {
        // Use root-absolute URLs so links work from any nested directory
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('site_href')) {
    function site_href(string $path): string
    {
        // Pages are also root-absolute
        return asset_url($path);
    }
}

if (!function_exists('render_header')) {
    function render_header(): void
    {
        include ROOT_DIR . '/header.php';
    }
}

if (!function_exists('render_footer')) {
    function render_footer(): void
    {
        if (file_exists(ROOT_DIR . '/footer.php')) {
            include ROOT_DIR . '/footer.php';
        } else {
            echo "</main></body></html>"; // minimal fallback
        }
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header('Location: ' . site_href('index.php'));
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    // Shared constants
    if (!defined('ROLES')) {
        define('ROLES', ['admin', 'giver', 'seeker']);
    }
    if (!defined('ITEM_STATUSES')) {
        define('ITEM_STATUSES', ['available', 'pending', 'unavailable']);
    }
    if (!defined('SERVICE_AVAILABILITIES')) {
        define('SERVICE_AVAILABILITIES', ['available', 'busy', 'unavailable']);
    }
    if (!defined('REQUEST_STATUSES')) {
        define('REQUEST_STATUSES', ['pending', 'approved', 'rejected', 'completed']);
    }

    function require_admin(): void
    {
        require_login();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: ' . site_href('index.php'));
            exit;
        }
    }
}

// UI helpers
if (!function_exists('build_query')) {
    /**
     * Build a query string by merging current $_GET params with updates.
     * Pass a $base array to override the base params; by default uses $_GET.
     */
    function build_query(array $updates = [], ?array $base = null): string
    {
        $params = $base ?? (isset($_GET) && is_array($_GET) ? $_GET : []);
        foreach ($updates as $k => $v) {
            if ($v === null) {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
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
    function render_pagination(int $page, int $perPage, int $shownCount, int $total, array $extraParams = []): void
    {
        if ($total <= $perPage) {
            return;
        }
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

// Image upload
if (!function_exists('upload_image_secure')) {
    /**
     * Validates and saves an uploaded image file.
     * - Enforces max size (bytes)
     * - Validates MIME via finfo/getimagesize
     * - Randomizes filename with allowed extension
     * - Optionally resizes to fit within maxWidth x maxHeight (requires GD)
     * Returns [ok=>bool, pathRel=>string|null, error=>string|null]
     */
    function upload_image_secure(array $file, string $targetSubdir = 'uploads/items', int $maxBytes = 2_000_000, int $maxWidth = 1600, int $maxHeight = 1200): array
    {
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
            return ['ok' => false, 'pathRel' => null, 'error' => ($map[$err] ?? ('Upload error (code ' . $err . ').'))];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Image size must be <= ' . (int)round($maxBytes / 1024 / 1024) . 'MB.'];
        }
        $mime = null;
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = @finfo_file($fi, $tmp);
                @finfo_close($fi);
            }
        }
        if (!$mime && function_exists('getimagesize')) {
            $gi = @getimagesize($tmp);
            if (is_array($gi) && !empty($gi['mime'])) {
                $mime = $gi['mime'];
            }
        }
        if (!$mime && isset($file['type'])) {
            $mime = $file['type'];
        }
        $mime = $mime ? strtolower($mime) : '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!$mime || !isset($allowed[$mime])) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Only JPG, PNG, GIF or WEBP images are allowed.'];
        }
        $ext = $allowed[$mime];
        $dirAbs = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $targetSubdir);
        if (!is_dir($dirAbs)) {
            @mkdir($dirAbs, 0777, true);
        }
        try {
            $name = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (\Throwable $e) {
            $name = uniqid('', true) . '.' . $ext;
        }
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
                $w = (int)$info[0];
                $h = (int)$info[1];
                $scale = min($maxWidth / max(1, $w), $maxHeight / max(1, $h), 1.0);
                if ($scale < 1.0) {
                    $newW = max(1, (int)round($w * $scale));
                    $newH = max(1, (int)round($h * $scale));
                    $dst = imagecreatetruecolor($newW, $newH);
                    // Load source
                    $src = null;
                    if ($ext === 'jpg') {
                        $src = @imagecreatefromjpeg($destAbs);
                    } elseif ($ext === 'png') {
                        $src = @imagecreatefrompng($destAbs);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                    } elseif ($ext === 'webp') {
                        $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($destAbs) : null;
                    }
                    if ($src) {
                        @imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
                        // Re-encode without metadata
                        if ($ext === 'jpg') {
                            @imagejpeg($dst, $destAbs, 85);
                        } elseif ($ext === 'png') {
                            @imagepng($dst, $destAbs, 6);
                        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
                            @imagewebp($dst, $destAbs, 85);
                        }
                        @imagedestroy($src);
                        @imagedestroy($dst);
                    }
                } else {
                    // For JPG/PNG/WEBP, re-encode to strip EXIF even if no resize
                    $dst = imagecreatetruecolor($w, $h);
                    $src = null;
                    if ($ext === 'jpg') {
                        $src = @imagecreatefromjpeg($destAbs);
                    } elseif ($ext === 'png') {
                        $src = @imagecreatefrompng($destAbs);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                    } elseif ($ext === 'webp') {
                        $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($destAbs) : null;
                    }
                    if ($src) {
                        @imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                        if ($ext === 'jpg') {
                            @imagejpeg($dst, $destAbs, 85);
                        } elseif ($ext === 'png') {
                            @imagepng($dst, $destAbs, 6);
                        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
                            @imagewebp($dst, $destAbs, 85);
                        }
                        @imagedestroy($src);
                        @imagedestroy($dst);
                    }
                }
            }
        }

        return ['ok' => true, 'pathRel' => $destRel, 'error' => null];
    }
}

// Email verification
// Uses table `verification_tokens` (id, user_id, token_hash, expires_at, used_at)
// Also keeps users.email_verified in users table; optional shadow columns remain but are not required.

if (!function_exists('verification_generate_and_send')) {
    function verification_generate_and_send(int $userId, string $email, string $username = ''): bool
    {
        if (!db_connected()) return false;
        global $conn;
        // Check if already verified
        if ($stmt = $conn->prepare('SELECT email_verified FROM users WHERE user_id=? LIMIT 1')) {
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if ((int)$row['email_verified'] === 1) {
                        $stmt->close();
                        return true;
                    }
                }
                if ($res) $res->free();
            }
            $stmt->close();
        }
        // Invalidate previous unused tokens (optional cleanup)
        @$conn->query('UPDATE verification_tokens SET used_at=NOW() WHERE user_id=' . (int)$userId . ' AND used_at IS NULL');
        try {
            $raw = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $raw = bin2hex(random_bytes(16));
        }
        $hash = hash('sha256', $raw);
        $expires = date('Y-m-d H:i:s', time() + 24 * 3600);
        if ($stmt2 = $conn->prepare('INSERT INTO verification_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)')) {
            $stmt2->bind_param('iss', $userId, $hash, $expires);
            $ok = $stmt2->execute();
            $stmt2->close();
            if ($ok) {
                // Build absolute base URL (setting 'app_url' preferred). Ensure trailing slash.
                $base = (string)get_setting('app_url', '');
                if ($base === '') {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $base   = $scheme . '://' . $host . '/';
                }
                $base = rtrim($base, '/') . '/';
                $link = $base . 'verify.php?uid=' . urlencode((string)$userId) . '&token=' . urlencode($raw);
                // HTML email with clickable link + plain display of URL
                $escapedUser = htmlspecialchars($username ?: 'there', ENT_QUOTES, 'UTF-8');
                $bodyHtml = '<p>Hi ' . $escapedUser . ',</p>'
                    . '<p>Please verify your ShareHub account by clicking this link:<br>'
                    . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Verify Email</a></p>'
                    . '<p>If the button does not work, copy and paste this URL into your browser:<br>'
                    . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p>This link expires in 24 hours.<br>If you did not create an account, you can ignore this email.</p>'
                    . '<p>— ShareHub</p>';
                return send_email($email, 'Verify your ShareHub account', $bodyHtml);
            }
        }
        return false;
    }
}

if (!function_exists('verification_mark_verified')) {
    function verification_mark_verified(int $userId, ?string $tokenHash = null): bool
    {
        if (!db_connected()) return false;
        global $conn;
        $ok = false;
        if ($stmt = $conn->prepare('UPDATE users SET email_verified=1 WHERE user_id=?')) {
            $stmt->bind_param('i', $userId);
            $ok = $stmt->execute();
            $stmt->close();
        }
        if ($ok && $tokenHash) {
            if ($stmt2 = $conn->prepare('UPDATE verification_tokens SET used_at=NOW() WHERE user_id=? AND token_hash=? AND used_at IS NULL')) {
                $stmt2->bind_param('is', $userId, $tokenHash);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        return $ok;
    }
}

if (!function_exists('verification_is_verified')) {
    function verification_is_verified(int $userId): bool
    {
        if (!db_connected()) return true;
        global $conn;
        if ($stmt = $conn->prepare('SELECT email_verified FROM users WHERE user_id=? LIMIT 1')) {
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $v = (int)$row['email_verified'] === 1;
                    $stmt->close();
                    return $v;
                }
            }
            $stmt->close();
        }
        return false;
    }
}

if (!function_exists('verification_require')) {
    function verification_require(): void
    {
        if (!isset($_SESSION['user_id'])) return;
        if (!verification_is_verified((int)$_SESSION['user_id'])) {
            header('Location: ' . site_href('verify_notice.php?uid=' . (int)$_SESSION['user_id']));
            exit;
        }
    }
}

// Password reset (OTP)
// Uses legacy OTP-style password_resets table: id, user_id, email, otp_code, expires_at, used_at
if (!function_exists('password_reset_create')) {
    function password_reset_create(string $email): bool
    {
        if (!db_connected()) return false;
        global $conn;
        if ($email === '') return false;
        $storageDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        $logFile = $storageDir . DIRECTORY_SEPARATOR . 'mail.log';
        // Quick table existence check (logs but keeps external response generic)
        $tableOk = true;
        if (!$conn->query('SELECT 1 FROM password_resets LIMIT 1')) {
            $tableOk = false;
            @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: table password_resets missing or unreadable (" . $conn->error . ")\n", FILE_APPEND);
        }
        if (!$tableOk) {
            // We cannot proceed – return true (silent) to avoid enumeration.
            return true;
        }
        // OTP-only mode (no token link logic)
        // Locate user id + email_verified (optional: require verified email)
        $uid = null;
        $username = '';
        $userEmail = '';
        if ($stmt = $conn->prepare('SELECT user_id, username, email_verified, email FROM users WHERE email = ? LIMIT 1')) {
            $stmt->bind_param('s', $email);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    // Only allow reset if verified to avoid enumeration-based spam
                    if ((int)$row['email_verified'] === 1) {
                        $uid = (int)$row['user_id'];
                        $username = (string)$row['username'];
                        $userEmail = (string)$row['email'];
                    } else {
                        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: email '$email' found but not verified, skipping send.\n", FILE_APPEND);
                    }
                } else {
                    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: email '$email' not found (silent success).\n", FILE_APPEND);
                }
                if ($res) $res->free();
            }
            $stmt->close();
        }
        if ($uid === null) {
            // Silent success to avoid leaking which emails exist
            return true;
        }
        // Invalidate previous unused (both modes)
        @$conn->query('UPDATE password_resets SET used_at=NOW() WHERE user_id=' . (int)$uid . ' AND used_at IS NULL');
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        $base = (string)get_setting('app_url', '');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base   = $scheme . '://' . $host . '/';
        }
        $escapedUser = htmlspecialchars($username ?: 'there', ENT_QUOTES, 'UTF-8');
        // Generate 6-digit code and send
        try {
            $raw = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            $raw = substr(bin2hex(random_bytes(4)), 0, 6);
        }
        if ($stmt2 = $conn->prepare('INSERT INTO password_resets (user_id, email, otp_code, expires_at) VALUES (?,?,?,?)')) {
            $stmt2->bind_param('isss', $uid, $userEmail, $raw, $expires);
            $ok = $stmt2->execute();
            if (!$ok) {
                @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: INSERT failed (" . $stmt2->error . ") for uid $uid email '$email' mode=otp\n", FILE_APPEND);
            }
            $stmt2->close();
            if ($ok) {
                $link = rtrim($base, '/') . '/reset_password_code.php?uid=' . urlencode((string)$uid);
                $escapedUser = htmlspecialchars($username ?: 'there', ENT_QUOTES, 'UTF-8');
                $bodyHtml = '<p style="margin:0 0 6px 0;">Hi ' . $escapedUser . ', your password reset code is:</p>'
                    . '<h2 style="letter-spacing:1px;margin:4px 0 8px;">' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '</h2>'
                    . '<p style="margin:0 0 6px 0;">Enter this code on the reset page: '
                    . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">Reset Password Page</a></p>'
                    . '<p style="margin:0 0 6px 0;">Expires in 1 hour. Request a new one if it stops working.</p>'
                    . '<p style="margin:0;">If you didn\'t request this, ignore this email. — ShareHub</p>';
                $sent = send_email($userEmail, 'Your ShareHub password reset code', $bodyHtml);
                if (!$sent) {
                    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: send_email returned FAIL for '$userEmail' uid $uid mode=otp\n", FILE_APPEND);
                } else {
                    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: email '$userEmail' uid $uid send=OK mode=otp\n", FILE_APPEND);
                }
                return $sent;
            }
        } else {
            @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: prepare failed for INSERT (" . $conn->error . ") mode=otp\n", FILE_APPEND);
        }
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] password_reset_create: insertion or send failed for email '$email'\n", FILE_APPEND);
        return false;
    }
}

if (!function_exists('password_reset_validate')) {
    function password_reset_validate(int $userId, string $code): array
    {
        $result = ['ok' => false, 'status' => '', 'code' => null];
        if (!db_connected()) {
            $result['status'] = 'db';
            return $result;
        }
        global $conn;
        if ($userId <= 0 || $code === '') {
            $result['status'] = 'missing';
            return $result;
        }
        if ($stmt = $conn->prepare('SELECT otp_code, expires_at, used_at FROM password_resets WHERE user_id=? AND used_at IS NULL ORDER BY id DESC LIMIT 1')) {
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $expTs = strtotime($row['expires_at']);
                    $now = time();
                    if ($row['used_at']) {
                        $result['status'] = 'used';
                    } elseif ($now > $expTs) {
                        $result['status'] = 'expired';
                    } elseif (!hash_equals($row['otp_code'], $code)) {
                        $result['status'] = 'mismatch';
                    } else {
                        $result = ['ok' => true, 'status' => 'ok', 'code' => $row['otp_code']];
                    }
                } else {
                    $result['status'] = 'notfound';
                }
                if ($res) $res->free();
            }
            $stmt->close();
        }
        return $result;
    }
}

if (!function_exists('password_reset_consume')) {
    function password_reset_consume(int $userId, string $code, string $newPassword): bool
    {
        if (!db_connected()) return false;
        global $conn;
        $ok = false;
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND otp_code=? AND used_at IS NULL');
            if ($stmt) {
                $stmt->bind_param('is', $userId, $code);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                if ($affected === 1) {
                    $hashPwd = password_hash($newPassword, PASSWORD_DEFAULT);
                    if ($stmt2 = $conn->prepare('UPDATE users SET password_hash=? WHERE user_id=?')) {
                        $stmt2->bind_param('si', $hashPwd, $userId);
                        $ok = $stmt2->execute();
                        $stmt2->close();
                    }
                }
            }
            if ($ok) {
                $conn->commit();
            } else {
                $conn->rollback();
            }
        } catch (Throwable $e) {
            $conn->rollback();
        }
        return $ok;
    }
}

// Notifications
if (!function_exists('notify_user')) {
    /**
     * Create an in-app notification (and optional email) for a user.
     * Auto-creates the notifications table if missing.
     * @param int $userId target user id
     * @param string $type short machine type (request_status, system, etc.)
     * @param string $subject short title
     * @param string $body HTML/plain body snippet (sanitise before output elsewhere)
     * @param string|null $relatedType optional related entity type (request, item, service)
     * @param int|null $relatedId optional related entity id
     * @param bool $alsoEmail send email copy (uses send_email helper if user has verified email)
     */
    function notify_user(int $userId, string $type, string $subject, string $body, ?string $relatedType = null, ?int $relatedId = null, bool $alsoEmail = false): bool
    {
        if ($userId <= 0) return false;
        if (!db_connected()) return false;
        global $conn;
        // Create table if needed
        @$conn->query("CREATE TABLE IF NOT EXISTS notifications (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            user_id INT NOT NULL,\n            type VARCHAR(40) NOT NULL,\n            subject VARCHAR(160) NOT NULL,\n            body TEXT NULL,\n            related_type VARCHAR(40) NULL,\n            related_id INT NULL,\n            is_read TINYINT(1) NOT NULL DEFAULT 0,\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            read_at DATETIME NULL,\n            INDEX idx_user_read (user_id, is_read),\n            INDEX idx_related (related_type, related_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $sql = 'INSERT INTO notifications (user_id,type,subject,body,related_type,related_id) VALUES (?,?,?,?,?,?)';
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('issssi', $userId, $type, $subject, $body, $relatedType, $relatedId);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok && $alsoEmail) {
                // Fetch user email if verified
                if ($uStmt = $conn->prepare('SELECT email, email_verified FROM users WHERE user_id=? LIMIT 1')) {
                    $uStmt->bind_param('i', $userId);
                    if ($uStmt->execute()) {
                        $res = $uStmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            if ((int)$row['email_verified'] === 1) {
                                @send_email($row['email'], $subject, nl2br($body));
                            }
                            if ($res) $res->free();
                        }
                    }
                    $uStmt->close();
                }
            }
            return $ok;
        }
        return false;
    }
}
if (!function_exists('notifications_fetch_unread')) {
    function notifications_fetch_unread(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) return [];
        // Attempt reconnect if gone
        if (function_exists('db_reconnect_if_needed')) {
            db_reconnect_if_needed();
        }
        if (!db_connected()) return [];
        global $conn;
        $out = [];
        try {
            if ($stmt = $conn->prepare('SELECT id,type,subject,body,related_type,related_id,created_at FROM notifications WHERE user_id=? AND is_read=0 ORDER BY id DESC LIMIT ?')) {
                $stmt->bind_param('ii', $userId, $limit);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $out[] = $r;
                    }
                    if ($res) $res->free();
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Silently ignore (header should never fatal because of notifications)
            return [];
        }
        return $out;
    }
}
if (!function_exists('notifications_mark_read')) {
    /** Mark specific notifications (ids array) or all unread if empty */
    function notifications_mark_read(int $userId, array $ids = []): int
    {
        if ($userId <= 0 || !db_connected()) return 0;
        global $conn;
        $count = 0;
        if ($ids) {
            // Sanitize ints
            $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
            if (!$ids) return 0;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids) + 1);
            $params = array_merge([$userId], $ids);
            $sql = 'UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND id IN (' . $placeholders . ') AND is_read=0';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $count = $stmt->affected_rows;
                $stmt->close();
            }
        } else {
            if ($stmt = $conn->prepare('UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0')) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $count = $stmt->affected_rows;
                $stmt->close();
            }
        }
        return $count;
    }
}

// Audit log
if (!function_exists('audit_log')) {
    /** Log admin actions */
    function audit_log(string $action, string $targetTable, int $targetId = 0, array $meta = []): bool
    {
        if (!db_connected()) return false;
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') return false;
        global $conn;
        $adminId = (int)($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) return false;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        @$conn->query("CREATE TABLE IF NOT EXISTS audit_log (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            admin_user_id INT NOT NULL,\n            action VARCHAR(60) NOT NULL,\n            target_table VARCHAR(60) NOT NULL,\n            target_id INT NOT NULL DEFAULT 0,\n            meta_json JSON NULL,\n            ip VARCHAR(45) NULL,\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            INDEX idx_admin (admin_user_id),\n            INDEX idx_target (target_table,target_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $json = $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        if ($stmt = $conn->prepare('INSERT INTO audit_log (admin_user_id,action,target_table,target_id,meta_json,ip) VALUES (?,?,?,?,?,?)')) {
            $stmt->bind_param('ississ', $adminId, $action, $targetTable, $targetId, $json, $ip);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }
        return false;
    }
}

// Conversations (threaded messaging)
if (!function_exists('conversations_ensure_schema')) {
    function conversations_ensure_schema(): void
    {
        if (!db_connected()) return;
        global $conn;
        static $done = false;
        if ($done) return;
        $done = true;
        // Create conversations table
        @$conn->query("CREATE TABLE IF NOT EXISTS conversations (
                conversation_id INT AUTO_INCREMENT PRIMARY KEY,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Create participants table
        @$conn->query("CREATE TABLE IF NOT EXISTS conversation_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                user_id INT NOT NULL,
                last_read_at DATETIME NULL,
                joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_conv_user (conversation_id, user_id),
                KEY idx_user (user_id),
                CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Add columns to messages if missing
        $colsRes = @$conn->query('SHOW COLUMNS FROM messages');
        $have = [];
        if ($colsRes) {
            while ($r = $colsRes->fetch_assoc()) {
                $have[strtolower($r['Field'])] = true;
            }
            $colsRes->free();
        }
        $alterParts = [];
        if (!isset($have['conversation_id'])) {
            $alterParts[] = 'ADD COLUMN conversation_id INT NULL';
        }
        if (!isset($have['read_at'])) {
            $alterParts[] = 'ADD COLUMN read_at DATETIME NULL';
        }
        if (!isset($have['sender_deleted_at'])) {
            $alterParts[] = 'ADD COLUMN sender_deleted_at DATETIME NULL';
        }
        if (!isset($have['recipient_deleted_at'])) {
            $alterParts[] = 'ADD COLUMN recipient_deleted_at DATETIME NULL';
        }
        if ($alterParts) {
            @$conn->query('ALTER TABLE messages ' . implode(', ', $alterParts));
        }
        // Add index if not present
        $idxRes = @$conn->query("SHOW INDEX FROM messages WHERE Key_name='idx_conv'");
        if (!$idxRes || $idxRes->num_rows === 0) {
            @$conn->query('ALTER TABLE messages ADD INDEX idx_conv (conversation_id)');
        }
        if ($idxRes) {
            $idxRes->free();
        }
    }
}

// Start or fetch a conversation among given user IDs (currently supports 2 participants typical for legacy messages)
if (!function_exists('conversation_start')) {
    function conversation_start(array $userIds): ?int
    {
        conversations_ensure_schema();
        if (!db_connected()) return null;
        global $conn;
        // Normalise & limit (initially 2 participants)
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $userIds = array_filter($userIds, fn($v) => $v > 0);
        if (count($userIds) < 2) return null; // need at least two users
        sort($userIds);
        // For 2-user conversation we try to find existing one via participants intersection
        if (count($userIds) === 2) {
            [$a, $b] = $userIds;
            $sql = "SELECT cp1.conversation_id FROM conversation_participants cp1
                    JOIN conversation_participants cp2 ON cp1.conversation_id=cp2.conversation_id
                    WHERE cp1.user_id=? AND cp2.user_id=? LIMIT 1";
            if ($st = $conn->prepare($sql)) {
                $st->bind_param('ii', $a, $b);
                if ($st->execute()) {
                    $r = $st->get_result()->fetch_assoc();
                    if ($r) {
                        $cid = (int)$r['conversation_id'];
                        $st->close();
                        return $cid;
                    }
                }
                $st->close();
            }
        }
        // Create new conversation
        if (!$conn->query('INSERT INTO conversations () VALUES ()')) {
            return null;
        }
        $cid = (int)$conn->insert_id;
        if ($cid <= 0) return null;
        // Insert participants
        foreach ($userIds as $uid) {
            @$conn->query('INSERT IGNORE INTO conversation_participants (conversation_id,user_id) VALUES (' . (int)$cid . ',' . (int)$uid . ')');
        }
        return $cid;
    }
}

// Lazy backfill just for the currently logged-in user's view to ensure their legacy messages appear as threads
if (!function_exists('conversation_lazy_backfill_for_user')) {
    function conversation_lazy_backfill_for_user(int $userId, int $limitPairs = 100): void
    {
        conversations_ensure_schema();
        if (!db_connected()) return;
        global $conn;
        $userId = (int)$userId;
        if ($userId <= 0) return;
        // Find up to N distinct pairs involving this user lacking conversation_id
        $sql = "SELECT LEAST(sender_id,receiver_id) a, GREATEST(sender_id,receiver_id) b
                FROM messages
                WHERE conversation_id IS NULL AND (sender_id=$userId OR receiver_id=$userId) AND sender_id<>receiver_id
                GROUP BY a,b
                LIMIT " . (int)$limitPairs;
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $a = (int)$row['a'];
                $b = (int)$row['b'];
                if ($a <= 0 || $b <= 0) continue;
                if ($a === $b) continue;
                $cid = conversation_start([$a, $b]);
                if (!$cid) continue;
                // Attach any lingering messages for that pair
                @$conn->query('UPDATE messages SET conversation_id=' . (int)$cid . ' WHERE conversation_id IS NULL AND ((sender_id=' . (int)$a . ' AND receiver_id=' . (int)$b . ') OR (sender_id=' . (int)$b . ' AND receiver_id=' . (int)$a . '))');
            }
            $res->free();
        }
    }
}

if (!function_exists('conversation_send')) {
    /**
     * Insert a message into a conversation.
     * @param int $conversationId
     * @param int $senderId
     * @param string $body message body (required)
     * @param string|null $subject optional subject
     * @param int|null $requestId optional legacy request linkage (if provided will populate messages.request_id)
     */
    function conversation_send(int $conversationId, int $senderId, string $body, ?string $subject = null, ?int $requestId = null): ?int
    {
        conversations_ensure_schema();
        if (!db_connected()) return null;
        global $conn;
        $conversationId = (int)$conversationId;
        $senderId = (int)$senderId;
        $body = trim($body);
        $subject = trim((string)$subject);
        if ($conversationId <= 0 || $senderId <= 0 || $body === '') return null;
        // Ensure sender is participant
        if ($st = $conn->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1')) {
            $st->bind_param('ii', $conversationId, $senderId);
            $st->execute();
            $ok = $st->get_result()->fetch_row();
            $st->close();
            if (!$ok) return null;
        }
        // Insert message (reuse legacy messages table)
        // Legacy table columns: sender_id, receiver_id, request_id, subject, message_content, sent_date, is_read
        // We will store receiver_id as 0 (unused) because participants table defines membership; keep subject optional.
        $receiverPlaceholder = 0; // conversation-based
        $mid = null;
        // clear previous error tracker
        $GLOBALS['conversation_send_error'] = null;
        if ($requestId !== null && $requestId > 0) {
            $sql = 'INSERT INTO messages (sender_id, receiver_id, request_id, subject, message_content, conversation_id, sent_date, is_read) VALUES (?,?,?,?,?,?,NOW(),0)';
            if ($st = $conn->prepare($sql)) {
                $st->bind_param('iiissi', $senderId, $receiverPlaceholder, $requestId, $subject, $body, $conversationId);
                if ($st->execute()) {
                    $mid = (int)$st->insert_id;
                } else {
                    $GLOBALS['conversation_send_error'] = $st->error ?: $conn->error;
                }
                $st->close();
            }
        } else {
            $sql = 'INSERT INTO messages (sender_id, receiver_id, subject, message_content, conversation_id, sent_date, is_read) VALUES (?,?,?,?,?,NOW(),0)';
            if ($st = $conn->prepare($sql)) {
                $st->bind_param('iissi', $senderId, $receiverPlaceholder, $subject, $body, $conversationId);
                if ($st->execute()) {
                    $mid = (int)$st->insert_id;
                } else {
                    $GLOBALS['conversation_send_error'] = $st->error ?: $conn->error;
                }
                $st->close();
            }
        }
        if ($mid) {
            @$conn->query('UPDATE conversations SET updated_at=NOW() WHERE conversation_id=' . (int)$conversationId . ' LIMIT 1');
            // Notify other participants (exclude sender). Use short preview.
            $preview = mb_substr($body, 0, 120);
            if (function_exists('notify_user')) {
                $resP = @$conn->query('SELECT user_id FROM conversation_participants WHERE conversation_id=' . (int)$conversationId . ' AND user_id<>' . (int)$senderId);
                if ($resP) {
                    while ($rp = $resP->fetch_assoc()) {
                        notify_user((int)$rp['user_id'], 'message_new', 'New message', e($preview), 'conversation', $conversationId, false);
                    }
                    $resP->free();
                }
            }
            return $mid;
        }
        return null;
    }
}

if (!function_exists('conversation_list')) {
    function conversation_list(int $userId, int $limit = 20, int $offset = 0): array
    {
        conversations_ensure_schema();
        if (!db_connected()) return [];
        global $conn;
        $out = [];
        conversation_lazy_backfill_for_user($userId); // ensure legacy messages appear
        $sql = "SELECT c.conversation_id,
                       c.updated_at,
                       MAX(m.sent_date) last_msg_at,
                       SUBSTRING_INDEX(MAX(CONCAT(m.sent_date,'\t',m.message_content)), '\t', -1) last_msg_body,
                       COUNT(CASE WHEN (m.is_read=0 OR m.read_at IS NULL) AND m.sender_id<>? THEN 1 END) unread_count,
                       COALESCE(u_other.username, '(user)') AS other_username
                FROM conversations c
                JOIN conversation_participants cp_self ON cp_self.conversation_id=c.conversation_id AND cp_self.user_id=?
                LEFT JOIN conversation_participants cp_other ON cp_other.conversation_id=c.conversation_id AND cp_other.user_id<>?
                LEFT JOIN users u_other ON u_other.user_id=cp_other.user_id
                LEFT JOIN messages m ON m.conversation_id=c.conversation_id
                GROUP BY c.conversation_id, other_username
                ORDER BY c.updated_at DESC
                LIMIT ?,?";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('iiiii', $userId, $userId, $userId, $offset, $limit);
            if ($st->execute()) {
                $res = $st->get_result();
                while ($r = $res->fetch_assoc()) {
                    $out[] = $r;
                }
                $res->free();
            }
            $st->close();
        }
        return $out;
    }
}

if (!function_exists('conversation_fetch')) {
    /**
     * Fetch messages for a conversation plus sender username/name.
     * (Lightweight; for large histories implement pagination via beforeMessageId.)
     */
    function conversation_fetch(int $userId, int $conversationId, int $limit = 200, int $beforeMessageId = 0): array
    {
        conversations_ensure_schema();
        if (!db_connected()) return ['ok' => false, 'messages' => []];
        global $conn;
        $conversationId = (int)$conversationId;
        $userId = (int)$userId;
        $limit = max(1, min(400, $limit));
        $beforeMessageId = (int)$beforeMessageId;
        // Verify membership
        if ($st = $conn->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1')) {
            $st->bind_param('ii', $conversationId, $userId);
            $st->execute();
            $m = $st->get_result()->fetch_row();
            $st->close();
            if (!$m) return ['ok' => false, 'messages' => []];
        }
        // Fetch in ascending order so earliest first
        $cond = $beforeMessageId > 0 ? 'AND m.message_id > ' . (int)$beforeMessageId : '';
        $sql = "SELECT m.message_id, m.sender_id, m.subject, m.message_content, m.sent_date, m.is_read, m.read_at,
                       u.username AS sender_username, u.first_name, u.last_name
                FROM messages m
                LEFT JOIN users u ON u.user_id = m.sender_id
                WHERE m.conversation_id=? $cond
                ORDER BY m.message_id ASC
                LIMIT ?";
        $out = [];
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('ii', $conversationId, $limit);
            if ($st->execute()) {
                $res = $st->get_result();
                while ($r = $res->fetch_assoc()) {
                    $out[] = $r;
                }
                $res->free();
            }
            $st->close();
        }
        return ['ok' => true, 'messages' => $out];
    }
}

if (!function_exists('conversation_mark_read')) {
    function conversation_mark_read(int $userId, int $conversationId): int
    {
        conversations_ensure_schema();
        if (!db_connected()) return 0;
        global $conn;
        $userId = (int)$userId;
        $conversationId = (int)$conversationId;
        if ($userId <= 0 || $conversationId <= 0) return 0;
        // Ensure membership
        if ($st = $conn->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=? LIMIT 1')) {
            $st->bind_param('ii', $conversationId, $userId);
            $st->execute();
            $ok = $st->get_result()->fetch_row();
            $st->close();
            if (!$ok) return 0;
        }
        // Mark messages (recipient model changed; mark all not sent by this user)
        @$conn->query('UPDATE messages SET is_read=1, read_at=NOW() WHERE conversation_id=' . (int)$conversationId . ' AND sender_id<>' . (int)$userId . ' AND (is_read=0 OR read_at IS NULL)');
        @$conn->query('UPDATE conversation_participants SET last_read_at=NOW() WHERE conversation_id=' . (int)$conversationId . ' AND user_id=' . (int)$userId . ' LIMIT 1');
        return (int)$conn->affected_rows; // approximate
    }
}

// END threaded messaging additions
