<?php
// Simple health endpoint to verify Vercel PHP function is working
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'time' => gmdate('c'),
    'php' => PHP_VERSION,
]);
