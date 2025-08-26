<?php
require_once __DIR__ . '/bootstrap.php';

// Ensure no output is sent before redirect
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Unset all of the session variables
$_SESSION = [];

// Destroy the session and cookie
if (session_status() === PHP_SESSION_ACTIVE) {
	// Clear the session cookie
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'], $params['domain'],
			$params['secure'], $params['httponly']
		);
	}
	session_destroy();
}

// Redirect to home
header('Location: ' . site_href('index.php'));
exit;

