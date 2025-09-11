<?php
require_once __DIR__ . '/bootstrap.php';
// Email verification feature removed; redirect to dashboard
header('Location: ' . site_href('dashboard.php'));
exit;
