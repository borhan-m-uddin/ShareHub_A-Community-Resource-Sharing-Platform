<?php
require_once __DIR__ . '/bootstrap.php';
// Email verification feature removed; go to dashboard
header('Location: ' . site_href('dashboard.php'));
exit;
