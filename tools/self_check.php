<?php
// Lightweight environment self-check script.
// Usage: php tools/self_check.php (CLI) or visit in browser (outputs text/plain).

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

$results = [];

function check($label, callable $fn) {
    global $results; $start = microtime(true);
    try { $val = $fn(); $ok = $val === true || $val === null; $info = $ok ? 'OK' : (is_string($val) ? $val : json_encode($val)); }
    catch (Throwable $e) { $ok = false; $info = $e->getMessage(); }
    $results[] = [ 'label' => $label, 'ok' => $ok, 'info' => $info, 'ms' => (int)((microtime(true)-$start)*1000) ];
}

// PHP version
check('PHP Version >= 8.1', function(){ return version_compare(PHP_VERSION, '8.1.0', '>='); });

// Extensions
foreach (['mysqli','json','mbstring','openssl'] as $ext) {
    check('Extension '.$ext, function() use ($ext){ return extension_loaded($ext); });
}

// Root & paths
$root = realpath(__DIR__.'/..');
check('ROOT_DIR defined', function() { return defined('ROOT_DIR'); });

// Load bootstrap if not already loaded for DB helpers
if (!function_exists('db_connected')) {
    require_once $root.'/bootstrap.php';
}

// DB Connection
check('DB Connected', function(){ return function_exists('db_connected') && db_connected(); });

// Required tables minimal existence (best effort)
$tables = ['users','items','services','requests','password_resets','verification_tokens'];
if (function_exists('db_connected') && db_connected()) {
    global $conn; foreach ($tables as $t) {
        check('Table '.$t, function() use ($t,$conn){ return (bool)$conn->query('SELECT 1 FROM `'.$t.'` LIMIT 1'); });
    }
}

// Writable directories
$writable = [ 'uploads' => $root.'/uploads', 'storage' => $root.'/storage' ];
foreach ($writable as $label => $path) {
    check('Writable '.$label, function() use ($path){ if (!is_dir($path)) return 'missing'; return is_writable($path); });
}

// Mail log existence
check('Mail log writable', function() use ($root){
    $f = $root.'/storage/mail.log';
    if (!file_exists($f)) { @file_put_contents($f, "\n"); }
    return is_writable($f);
});

// Session check
check('Session started', function(){ return session_status() === PHP_SESSION_ACTIVE; });

// Output results
$failures = 0; foreach ($results as $r) { if (!$r['ok']) $failures++; }
$summary = sprintf("Self-check complete: %d OK, %d FAIL\n", count($results)-$failures, $failures);

echo $summary; echo str_repeat('-', 50)."\n";
foreach ($results as $r) {
    echo sprintf("[%s] %s (%sms)%s\n", $r['ok'] ? ' OK ' : 'FAIL', $r['label'], $r['ms'], $r['ok'] ? '' : ' :: '.$r['info']);
}
if ($failures>0) { echo "\nInvestigate failing checks above.\n"; }
