<?php
// Make About page the home. If POST login attempt exists, handle login logic; otherwise redirect to about.php
session_start();
require_once "config.php";

// If a login POST occurred, process it and redirect to dashboard on success
if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username !== '' && $password !== '') {
        $sql = "SELECT user_id, username, password_hash, role FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $username);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($user_id, $stored_username, $hashed_password, $role);
                    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $user_id;
                        $_SESSION["username"] = $stored_username;
                        $_SESSION["role"] = $role;
                        header('Location: dashboard.php');
                        exit;
                    }
                }
            }
            $stmt->close();
        }
    }
    // On failure, fall through to redirect to about page
}

// For GET and other requests, redirect to about.php as home
header('Location: about.php');
exit;
?>
