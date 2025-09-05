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

        // Prepare a select statement and verify credentials only
        $sql = "SELECT user_id, username, password_hash, role FROM users WHERE username = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $username);
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows === 1){
                    $stmt->bind_result($user_id, $fetched_username, $hashed_password, $role);
                    if($stmt->fetch() && password_verify($password, $hashed_password)){
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Login</h2>
        <p>Please fill in your credentials to login.</p>

        <?php
        if(!empty($_SESSION['flash_success'])){
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['flash_success']) . '</div>';
            unset($_SESSION['flash_success']);
        }
        if(!empty($login_err)){
            echo "<div class=\"alert alert-danger\">" . $login_err . "</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Login">
            </div>
            <p>Don't have an account? <a href="register.php">Sign up now</a>.</p>
        </form>
    </div>
    </main>
</body>
</html>

