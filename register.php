<?php include_once 'bootstrap.php';

$username = $password = $confirm_password = $email = "";
$role = 'seeker';
$username_err = $password_err = $confirm_password_err = $email_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $username_err = $username_err ?: 'Invalid request. Please refresh and try again.';
    } else {
        // Parse role early so we can use it when checking email uniqueness per role
        $raw_role_init = isset($_POST['role']) ? trim((string)$_POST['role']) : '';
        $sanitized_role_init = preg_replace('/[^a-zA-Z_-]/', '', $raw_role_init);
        if ($sanitized_role_init !== '' && in_array($sanitized_role_init, ['seeker', 'giver'], true)) {
            $role = $sanitized_role_init;
        } else {
            $role = 'seeker';
        }

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $trimmedUser = trim($_POST["username"]);
        if (!preg_match('/^[A-Za-z0-9]+$/', $trimmedUser)) {
            $username_err = "Username must contain only letters and numbers.";
        }
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE username = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);

            // Set parameters
            $param_username = $trimmedUser;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = $trimmedUser;
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        $raw_email = trim($_POST["email"]);
        if (!filter_var($raw_email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        } else {
            // Prepare a select statement for uniqueness check (per role)
            $sql = "SELECT user_id FROM users WHERE email = ? AND role = ?";
            if ($stmt = $conn->prepare($sql)) {
                // Bind variables to the prepared statement as parameters
                $stmt->bind_param("ss", $param_email, $param_role_for_email);

                // Set parameters
                $param_email = $raw_email;
                $param_role_for_email = $role;

                // Attempt to execute the prepared statement
                if ($stmt->execute()) {
                    $stmt->store_result();

                    if ($stmt->num_rows == 1) {
                        $email_err = "This email is already registered for this role.";
                    } else {
                        $email = $raw_email;
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }

                // Close statement
                $stmt->close();
            }
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must have at least 8 characters.";
    } elseif (!preg_match('/\\d/', $_POST['password'])) {
        $password_err = "Password must contain at least one number.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Determine requested role (seeker or giver) with sanitization
    // Avoid deprecated FILTER_SANITIZE_STRING — read raw and whitelist validator
    $raw_role = isset($_POST['role']) ? trim((string)$_POST['role']) : '';
    // Keep only letters, hyphen and underscore to be safe
    $sanitized_role = preg_replace('/[^a-zA-Z_-]/', '', $raw_role);
    if ($sanitized_role !== '' && in_array($sanitized_role, ['seeker', 'giver'], true)) {
        $role = $sanitized_role;
    } else {
        $role = 'seeker';
    }

    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssss", $param_username, $param_password, $param_email, $param_role);

            // Set parameters
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_email = $email;
            $param_role = $role; // Use selected role from the form (seeker or giver)

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                header("location: login.php");
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Register</h2>
        <p>Please fill this form to create an account.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" pattern="[A-Za-z0-9]+" title="Letters and numbers only" required>
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label>Password</label>
                <input type="password" name="password" class="form-control" value="<?php echo htmlspecialchars($password); ?>" minlength="8" pattern="(?=.*\\d).{8,}" title="At least 8 characters with at least one number" required>
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" value="<?php echo htmlspecialchars($confirm_password); ?>" minlength="8" pattern="(?=.*\\d).{8,}" title="At least 8 characters with at least one number" required>
                <span class="help-block"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <label>Register as</label>
                <div>
                    <label><input type="radio" name="role" value="seeker" <?php echo ($role === 'seeker') ? 'checked' : ''; ?>> Seeker</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="role" value="giver" <?php echo ($role === 'giver') ? 'checked' : ''; ?>> Giver</label>
                </div>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit">
                <input type="reset" class="btn btn-default" value="Reset">
            </div>
            <p>Already have an account? <a href="login.php">Login here</a>.</p>
        </form>
    </div>
    </main>
</body>
</html>

