<?php require_once __DIR__ . '/../bootstrap.php';
// Normalize DB handle regardless of bootstrap/config initialization mode
global $conn;
$conn = $conn instanceof mysqli ? $conn : db_handle();
// Moved original login implementation here from project root.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . site_href('pages/dashboard.php'));
    exit;
}

$username = $password = "";
$login_err = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $login_err = "Invalid request. Please refresh and try again.";
    } else {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        if ($username === '' || $password === '') {
            $login_err = "Username and password are required.";
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        if (!isset($_SESSION['rate_limit']['login'][$ip])) {
            $_SESSION['rate_limit']['login'][$ip] = [];
        }
        $_SESSION['rate_limit']['login'][$ip] = array_values(array_filter($_SESSION['rate_limit']['login'][$ip], function ($ts) use ($now) {
            return ($now - (int)$ts) <= 600;
        }));
        if (!$login_err && count($_SESSION['rate_limit']['login'][$ip]) >= 5) {
            $login_err = "Too many attempts. Please wait and try again.";
        }
        if (!$login_err) {
            $_SESSION['rate_limit']['login'][$ip][] = $now;
            $isEmail = (strpos($username, '@') !== false);
            $sql = $isEmail ? "SELECT user_id, username, password_hash, role, email_verified FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1" : "SELECT user_id, username, password_hash, role, email_verified FROM users WHERE username=? LIMIT 1";
            if (!$login_err && function_exists('db_connected') && !db_connected()) {
                $login_err = "Service temporarily unavailable. Please try again.";
            }
            $db = db_handle();
            if (!$login_err && $db instanceof mysqli && $stmt = $db->prepare($sql)) {
                $stmt->bind_param('s', $username);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows === 1) {
                        $stmt->bind_result($user_id, $fetched_username, $hashed_password, $role, $email_verified);
                        if ($stmt->fetch() && password_verify($password, $hashed_password)) {
                            if ((int)$email_verified !== 1) {
                                $login_err = "Your email is not verified. Please check your inbox or resend the verification email.";
                            } else {
                                if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
                                $_SESSION['loggedin'] = true;
                                $_SESSION['user_id'] = $user_id;
                                $_SESSION['username'] = $fetched_username;
                                $_SESSION['role'] = $role;
                                header('Location: ' . site_href('pages/dashboard.php'));
                                exit;
                            }
                        } else {
                            $login_err = "Invalid credentials.";
                        }
                    } else {
                        $login_err = "Invalid credentials.";
                    }
                }
                $stmt->close();
            }
        }
    }
    // If we have a failure at this point, stay on the login page and render the error
    if (!empty($login_err)) {
        // No redirect; the template below will render $login_err
        http_response_code(400);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body class="auth-page"><?php render_header(); ?>
    <div class="auth-container">
        <div class="card card-centered">
            <div class="card-body">
                <h2 class="auth-title">Welcome back</h2>
                <p class="text-muted">Sign in to continue to ShareHub</p>
                <?php if (!empty($_SESSION['flash_success'])) {
                    echo '<div class="alert alert-success">' . e($_SESSION['flash_success']) . '</div>';
                    unset($_SESSION['flash_success']);
                } ?>
                <?php if (!empty($login_err)) {
                    echo '<div class="alert alert-danger">' . e($login_err) . '</div>';
                } ?>
                <form action="" method="post" novalidate><?php echo csrf_field(); ?>
                    <div class="form-group"><label>Username or Email</label><input type="text" name="username" class="form-control" autocomplete="username" value="<?php echo e($username); ?>" required></div>
                    <div class="form-group password-toggle-wrapper"><label>Password</label>
                        <div class="password-field icon-variant"><input id="login_password" type="password" name="password" class="form-control"><button type="button" class="pw-icon-btn" data-target="login_password" aria-label="Show password" aria-pressed="false"><span class="pw-icon pw-icon-show" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg></span><span class="pw-icon pw-icon-hide" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m3 3 18 18" />
                                        <path d="M10.73 5.08A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a21.15 21.15 0 0 1-1.67 2.68" />
                                        <path d="M6.61 6.61A10.94 10.94 0 0 0 1 12s4 8 11 8a10.94 10.94 0 0 0 5.39-1.61" />
                                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    </svg></span></button></div>
                    </div>
                    <div class="auth-actions"><button type="submit" class="btn btn-primary btn-shimmer">Login</button><a class="btn btn-outline" href="<?php echo site_href('pages/register.php'); ?>">Create account</a></div>
                    <p style="margin-top:12px;font-size:0.9rem;"><a href="<?php echo site_href('pages/forgot_password.php'); ?>">Forgot password?</a></p>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function() {
            var btn = document.querySelector('.pw-icon-btn[data-target="login_password"]');
            if (!btn) return;
            btn.addEventListener('click', function() {
                var input = document.getElementById('login_password');
                if (!input) return;
                var showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                btn.setAttribute('aria-pressed', String(!showing));
                btn.classList.toggle('visible', !showing);
            });
        })();
    </script>
</body>

</html>