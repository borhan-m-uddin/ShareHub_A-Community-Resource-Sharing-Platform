<?php require_once __DIR__ . '/bootstrap.php';
// Check if the user is already logged in, if yes then redirect him to welcome page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}


$username = $password = "";
$login_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // CSRF
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $login_err = "Invalid request. Please refresh and try again.";
    } else {
    $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";

        // Basic required field validation prior to any DB work
        if ($username === '' || $password === '') {
            $login_err = "Username and password are required.";
        }

        // Simple rate limit: 5 attempts per 10 minutes per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $now = time();
        if (!isset($_SESSION['rate_limit']['login'][$ip])) { $_SESSION['rate_limit']['login'][$ip] = []; }
        // keep last 10 minutes
        $_SESSION['rate_limit']['login'][$ip] = array_values(array_filter(
            $_SESSION['rate_limit']['login'][$ip],
            function ($ts) use ($now) { return ($now - (int)$ts) <= 600; }
        ));
        if (count($_SESSION['rate_limit']['login'][$ip]) >= 5) {
            $login_err = "Too many attempts. Please wait and try again.";
        } else {
            $_SESSION['rate_limit']['login'][$ip][] = $now;

            // Allow login with username OR email (case-insensitive for email)
            $isEmail = (strpos($username, '@') !== false);
            if ($isEmail) {
                $sql = "SELECT user_id, username, password_hash, role, email_verified FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1";
            } else {
                $sql = "SELECT user_id, username, password_hash, role, email_verified FROM users WHERE username = ? LIMIT 1";
            }
            if (!$login_err && function_exists('db_connected') && !db_connected()) {
                $login_err = "Service temporarily unavailable. Please try again.";
            }
            if(!$login_err && $stmt = $conn->prepare($sql)){
                $stmt->bind_param("s", $username);
                if($stmt->execute()){
                    $stmt->store_result();
                    if($stmt->num_rows === 1){
                        $stmt->bind_result($user_id, $fetched_username, $hashed_password, $role, $email_verified);
                        if($stmt->fetch() && password_verify($password, $hashed_password)){
                            if ((int)$email_verified !== 1) {
                                $login_err = "Your email is not verified. Please check your inbox or resend the verification email.";
                            } else {
                            // Prevent session fixation
                            if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["username"] = $fetched_username;
                            $_SESSION["role"] = $role;
                            header("location: dashboard.php");
                            exit;
                            }
                        } else {
                            $login_err = "Invalid credentials.";
                        }
                    } else {
                        $login_err = "Invalid credentials.";
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
            // Removed manual $conn->close(); keep for header rendering.
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body class="auth-page">
<?php render_header(); ?>
<div class="auth-container">
    <div class="card card-centered">
        <div class="card-body">
            <h2 class="auth-title">Welcome back</h2>
            <p class="text-muted">Sign in to continue to ShareHub</p>

            <?php
            if(!empty($_SESSION['flash_success'])){
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') . '</div>';
                unset($_SESSION['flash_success']);
            }
            if(!empty($login_err)){
                echo "<div class=\"alert alert-danger\">" . htmlspecialchars($login_err, ENT_QUOTES, 'UTF-8') . "</div>";
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" method="post" novalidate>
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="username" class="form-control" autocomplete="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group password-toggle-wrapper">
                    <label>Password</label>
                    <div class="password-field icon-variant">
                        <input id="login_password" type="password" name="password" class="form-control">
                        <button type="button" class="pw-icon-btn" data-target="login_password" aria-label="Show password" aria-pressed="false">
                            <span class="pw-icon pw-icon-show" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                            <span class="pw-icon pw-icon-hide" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.83 21.83 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </span>
                        </button>
                    </div>
                </div>
                <div class="auth-actions">
                    <button type="submit" class="btn btn-primary btn-shimmer">Login</button>
                    <a class="btn btn-outline" href="register.php">Create account</a>
                </div>
                <p style="margin-top:12px;font-size:0.9rem;"><a href="forgot_password.php">Forgot password?</a></p>
            </form>
        </div>
    </div>
</div>
<script>
// Icon toggle (login)
(function(){
  var btn = document.querySelector('.pw-icon-btn[data-target="login_password"]');
  if(!btn) return;
  btn.addEventListener('click', function(){
    var input = document.getElementById('login_password');
    if(!input) return;
    var showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.setAttribute('aria-pressed', String(!showing));
    btn.classList.toggle('visible', !showing);
  });
})();
</script>
</body>
</html>

