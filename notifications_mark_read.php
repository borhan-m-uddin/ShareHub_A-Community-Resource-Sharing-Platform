<?php
require __DIR__ . '/bootstrap.php';
require_login();
if (!csrf_verify()) { http_response_code(400); echo 'Bad CSRF token'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (isset($_POST['all'])) {
        notifications_mark_read($uid);
    } elseif (!empty($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        notifications_mark_read($uid, $ids);
    }
}
header('Location: ' . site_href('notifications.php'));
exit;