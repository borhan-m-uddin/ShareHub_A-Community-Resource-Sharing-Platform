<?php
// Simple DB diagnostic endpoint. Do NOT expose in production long-term.
// Shows effective connection params (masked) and connection error details.

header('Content-Type: application/json');

$cfg = [
    'server' => getenv('DB_SERVER') ?: getenv('DB_HOST') ?: '127.0.0.1',
    'username' => getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ? '***set***' : '(empty)',
    'database' => getenv('DB_NAME') ?: 'community_sharing',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
];

mysqli_report(MYSQLI_REPORT_OFF);
$t0 = microtime(true);
$link = @new mysqli($cfg['server'], getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', getenv('DB_NAME') ?: 'community_sharing', (int)(getenv('DB_PORT') ?: 3306));
$elapsed = round((microtime(true) - $t0) * 1000);

$ok = ($link && !$link->connect_errno);
if ($ok) {
    @mysqli_set_charset($link, 'utf8mb4');
}

echo json_encode([
    'ok' => $ok,
    'elapsed_ms' => $elapsed,
    'effective' => $cfg,
    'connect_errno' => $ok ? 0 : ($link instanceof mysqli ? $link->connect_errno : -1),
    'connect_error' => $ok ? null : ($link instanceof mysqli ? $link->connect_error : 'no mysqli'),
    'php_version' => PHP_VERSION,
], JSON_PRETTY_PRINT);
