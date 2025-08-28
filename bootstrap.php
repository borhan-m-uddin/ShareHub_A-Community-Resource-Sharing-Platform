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
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'allow_self_signed' => false,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                    ],
                ]);
                $transport = ($secure === 'ssl') ? 'ssl' : 'tcp';
                $remote = sprintf('%s://%s:%d', $transport, $host, $port);

                $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
                if ($fp) {
                    stream_set_timeout($fp, 15);
                    $read = function() use ($fp) { return fgets($fp, 515) ?: ''; };
                    $write = function($data) use ($fp) { return fwrite($fp, $data); };
                    $expect = function($prefix) use ($read) {
                        $line = '';
                        do { $line = $read(); } while ($line !== '' && isset($line[3]) && $line[3] === '-');
                        return strpos($line, $prefix) === 0;
                    };

                    if ($expect('220')) {
                        $ehlo = 'EHLO sharehub.local\r\n';
                        $write($ehlo);
                        $expect('250');

                        if ($secure === 'tls') {
                            $write("STARTTLS\r\n");
                            if ($expect('220')) {
                                if (@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                                    $write($ehlo);
                                    $expect('250');
                                }
                            }
                        }

                        $write("AUTH LOGIN\r\n");
                        if ($expect('334')) {
                            $write(base64_encode($smtpUser) . "\r\n");
                            if ($expect('334')) {
                                $write(base64_encode(str_replace(' ', '', $smtpPass)) . "\r\n");
                                if ($expect('235')) {
                                    $fromAddr = $from ?: $smtpFrom;
                                    $write('MAIL FROM: <' . $fromAddr . ">\r\n");
                                    $expect('250');
                                    $write('RCPT TO: <' . $to . ">\r\n");
                                    $expect('250');
                                    $write("DATA\r\n");
                                    if ($expect('354')) {
                                        $fromHeaderName = $smtpFromName ? '"' . preg_replace('/[\r\n\"]+/', '', $smtpFromName) . '" ' : '';
                                        $headers = '';
                                        $headers .= 'From: ' . $fromHeaderName . '<' . $fromAddr . ">\r\n";
                                        $headers .= 'To: <' . $to . ">\r\n";
                                        $headers .= 'Subject: ' . $subject . "\r\n";
                                        $headers .= "MIME-Version: 1.0\r\n";
                                        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                                        $body = $headers . $message . "\r\n.\r\n";
                                        $write($body);
                                        if ($expect('250')) {
                                            $smtpOk = true;
                                        }
                                    }
                                    $write("QUIT\r\n");
                                }
                            }
                        }
                    }
                    @fclose($fp);
                }

                if ($smtpOk) {
                    $sent = true;
                } else {
                    // Log direct SMTP failure
                    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
                    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                    $logFile = $dir . DIRECTORY_SEPARATOR . 'mail.log';
                    $entry = "[" . date('Y-m-d H:i:s') . "] SMTP send failed to {$to} via {$smtpHost}:{$smtpPort} ({$smtpSecure})\n";
                    @file_put_contents($logFile, $entry, FILE_APPEND);
                }
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

?>
