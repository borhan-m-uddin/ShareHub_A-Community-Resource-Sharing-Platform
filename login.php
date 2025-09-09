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

            // Prepare a select statement and verify credentials only
            $sql = "SELECT user_id, username, password_hash, role FROM users WHERE username = ?";
            if($stmt = $conn->prepare($sql)){
                $stmt->bind_param("s", $username);
                if($stmt->execute()){
                    $stmt->store_result();
                    if($stmt->num_rows === 1){
                        $stmt->bind_result($user_id, $fetched_username, $hashed_password, $role);
                        if($stmt->fetch() && password_verify($password, $hashed_password)){
                            // Prevent session fixation
                            if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["username"] = $fetched_username;
                            $_SESSION["role"] = $role;
                            header("location: dashboard.php");
                            exit;
                        } else {
                            $login_err = "Invalid username or password.";
                        }
                    } else {
                        $login_err = "Invalid username or password.";
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }
                $stmt->close();
            }
            $conn->close();
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

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" method="post">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="auth-actions">
                    <button type="submit" class="btn btn-primary btn-shimmer">Login</button>
                    <a class="btn btn-outline" href="register.php">Create account</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

