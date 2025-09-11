<?php
require_once __DIR__ . '/bootstrap.php';
// Email verification feature has been removed. Redirect to home.
header('Location: ' . site_href('index.php'));
exit;
