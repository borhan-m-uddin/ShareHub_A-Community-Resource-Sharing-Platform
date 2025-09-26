<?php

namespace App\Mail;

/**
 * Centralized Mailer service that mirrors the legacy send_email helper.
 * Uses settings from storage/settings.json and supports PHPMailer, direct SMTP, mail(), and logging.
 */
class Mailer
{
    /**
     * Send an email with graceful fallbacks.
     * @return bool true if any transport reported success
     */
    public static function send(string $to, string $subject, string $message, ?string $from = null): bool
    {
        // Access global helpers if bootstrap is included
        $get = function (string $key, $default = null) {
            if (function_exists('get_setting')) {
                return get_setting($key, $default);
            }
            return $default;
        };

        $root = \defined('ROOT_DIR') ? ROOT_DIR : \dirname(__DIR__, 2);
        $storageDir = $root . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }
        $logFile = $storageDir . DIRECTORY_SEPARATOR . 'mail.log';

        $smtpHost        = \defined('SMTP_HOST') && constant('SMTP_HOST') !== '' ? (string)constant('SMTP_HOST') : (string)$get('smtp_host', '');
        $smtpUser        = \defined('SMTP_USER') && constant('SMTP_USER') !== '' ? (string)constant('SMTP_USER') : (string)$get('smtp_user', '');
        $smtpPass        = \defined('SMTP_PASS') && constant('SMTP_PASS') !== '' ? (string)constant('SMTP_PASS') : (string)$get('smtp_pass', '');
        $smtpPort        = \defined('SMTP_PORT') && (string)constant('SMTP_PORT') !== '' ? (int)constant('SMTP_PORT') : (int)$get('smtp_port', 587);
        $smtpFrom        = \defined('SMTP_FROM') && constant('SMTP_FROM') !== '' ? (string)constant('SMTP_FROM') : (string)$get('smtp_from', '');
        $smtpFromName    = \defined('SMTP_FROM_NAME') && constant('SMTP_FROM_NAME') !== '' ? (string)constant('SMTP_FROM_NAME') : (string)$get('smtp_from_name', '');
        $smtpSecure      = \defined('SMTP_SECURE') && constant('SMTP_SECURE') !== '' ? (string)constant('SMTP_SECURE') : (string)$get('smtp_secure', '');
        $smtpLocalDomain = (string)$get('smtp_local_domain', 'sharehub.local');
        $smtpDebugOn     = (bool)$get('smtp_debug', false);

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

        // Gmail constraint on From domain
        if ($smtpHost && stripos($smtpHost, 'gmail.com') !== false) {
            $fromDomain = strtolower(substr(strrchr($from, '@') ?: '', 1));
            $userDomain = strtolower(substr(strrchr($smtpUser, '@') ?: '', 1));
            if ($smtpUser && $fromDomain !== $userDomain) {
                $from = $smtpUser;
            }
        }

        $sent = false;
        $haveCreds = ($smtpHost && $smtpUser && $smtpPass);

        // 1) PHPMailer
        if ($haveCreds) {
            if (!\class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !\class_exists('PHPMailer')) {
                @include_once $root . '/vendor/autoload.php';
            }
            try {
                $phClass = \class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'PHPMailer\\PHPMailer\\PHPMailer' : (\class_exists('PHPMailer') ? 'PHPMailer' : null);
                if ($phClass) {
                    $debugBuffer = '';
                    $mailer = new $phClass(true);
                    $mailer->isSMTP();
                    $mailer->Host       = $smtpHost;
                    $mailer->Port       = (int)$smtpPort;
                    $mailer->SMTPAuth   = true;
                    $mailer->Username   = $smtpUser;
                    $mailer->Password   = $smtpPass;
                    if ($smtpSecure) {
                        $mailer->SMTPSecure = $smtpSecure;
                    }
                    $mailer->CharSet    = 'UTF-8';
                    $mailer->Hostname   = $smtpLocalDomain;
                    if ($smtpDebugOn) {
                        $mailer->SMTPDebug = 2;
                        $mailer->Debugoutput = function ($str) use (&$debugBuffer) {
                            $debugBuffer .= $str . "\n";
                        };
                    }
                    $mailer->setFrom($from, $smtpFromName ?: '');
                    $mailer->addAddress($to);
                    $mailer->Subject = $subject;
                    $isHtml = (strpos($message, '<a ') !== false) || (stripos($message, '<html') !== false) || (strpos($message, '<p') !== false) || (strpos($message, '<br') !== false);
                    if ($isHtml) {
                        $mailer->isHTML(true);
                        $mailer->Body = $message;
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

        // 2) Minimal direct SMTP if PHPMailer failed
        if (!$sent && $haveCreds) {
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
            $smtpOk = $try1['ok'];
            if (!$smtpOk && ($secure === 'tls' || $smtpPort === 587)) {
                $try2 = $tryAttempt($smtpHost, 465, 'ssl');
                $smtpOk = $try2['ok'];
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

        // 3) mail()
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

        // 4) Log failures
        if (!$sent) {
            $entry = '[' . date('Y-m-d H:i:s') . "]\nTO: {$to}\nSUBJECT: {$subject}\nMESSAGE:\n{$message}\n----\n";
            @file_put_contents($logFile, $entry, FILE_APPEND);
        }
        return $sent;
    }
}
