<?php
// Primary index page: if logged in redirect to appropriate area; if guest show marketing landing (about_alt).
require_once __DIR__ . '/../bootstrap.php';

// If logged in, route to role home immediately on GET.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_SESSION['loggedin'])) {
    $role = $_SESSION['role'] ?? '';
    header('Location: ' . site_href($role === 'seeker' ? 'seeker_feed.php' : 'dashboard.php'));
    exit;
}

// Handle login POST inline (kept minimal; consider extracting later)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim((string)$_POST['username']);
    $password = trim((string)$_POST['password']);
    if ($username !== '' && $password !== '' && function_exists('db_connected') && db_connected()) {
        if ($stmt = $conn->prepare('SELECT user_id, username, password_hash, role FROM users WHERE username = ?')) {
            $stmt->bind_param('s', $username);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($user_id, $stored_username, $hashed_password, $role);
                    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
                        $_SESSION['loggedin'] = true;
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $stored_username;
                        $_SESSION['role'] = $role;
                        header('Location: ' . site_href($role === 'seeker' ? 'seeker_feed.php' : 'dashboard.php'));
                        exit;
                    }
                }
            }
            $stmt->close();
        }
    }
    // If login fails we still show the marketing page (could add flash later)
}

// Include the full landing design directly (no redirect) for guests.
require_once __DIR__ . '/about_alt.php';
exit;
