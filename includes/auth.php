<?php
// Authentication related simple procedural helpers.

if (!function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}
if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return (($_SESSION['role'] ?? '') === 'admin');
    }
}
if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !current_user_id()) {
            header('Location: ' . site_href('pages/index.php'));
            exit;
        }
    }
}
if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        require_login();
        if (!is_admin()) {
            header('Location: ' . site_href('pages/index.php'));
            exit;
        }
    }
}
