<?php
include_once 'bootstrap.php';

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
        } elseif (!preg_match('/\d/', $_POST['password'])) {
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
                    $newId = $stmt->insert_id ? (int)$stmt->insert_id : 0;
                    if ($newId === 0) {
                        if ($q = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1')) {
                            $q->bind_param('s', $param_username);
                            if ($q->execute()) {
                                $r = $q->get_result();
                                if ($row = $r->fetch_assoc()) { $newId = (int)$row['user_id']; }
                                if ($r) $r->free();
                            }
                            $q->close();
                        }
                    }
                    // Ensure new user marked unverified (if not default) and create token
                    if ($newId > 0) {
                        // Optionally set email_verified=0 explicitly if schema default uncertain
                        @$conn->query("UPDATE users SET email_verified=0 WHERE user_id=" . (int)$newId);
                        verification_generate_and_send($newId, $param_email, $param_username);
                    }
                    header('Location: verify_notice.php?uid=' . urlencode((string)$newId));
                    exit();
                } else {
                    echo "Something went wrong. Please try again later.";
                }

                // Close statement
                $stmt->close();
            }
        }

    // Removed manual $conn->close(); avoid triggering false offline banner.
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
<body class="auth-page">
<?php render_header(); ?>
<div class="auth-container">
    <div class="card card-centered">
        <div class="card-body">
            <h2 class="auth-title">Create your account</h2>
            <p class="text-muted">Join ShareHub as a seeker or giver</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>
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
                <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?> password-toggle-wrapper">
                    <label>Password</label>
                    <div class="password-field icon-variant">
                        <input id="reg_password" type="password" name="password" class="form-control" value="<?php echo htmlspecialchars($password); ?>" minlength="8" pattern="(?=.*\d).{8,}" title="At least 8 characters with at least one number" required>
                        <button type="button" class="pw-icon-btn" data-target="reg_password" aria-label="Show password" aria-pressed="false">
                            <span class="pw-icon pw-icon-show" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                            <span class="pw-icon pw-icon-hide" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.83 21.83 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </span>
                        </button>
                    </div>
                    <span class="help-block"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?> password-toggle-wrapper">
                    <label>Confirm Password</label>
                    <div class="password-field icon-variant">
                        <input id="reg_confirm_password" type="password" name="confirm_password" class="form-control" value="<?php echo htmlspecialchars($confirm_password); ?>" minlength="8" pattern="(?=.*\d).{8,}" title="At least 8 characters with at least one number" required>
                        <button type="button" class="pw-icon-btn" data-target="reg_confirm_password" aria-label="Show confirm password" aria-pressed="false">
                            <span class="pw-icon pw-icon-show" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                            <span class="pw-icon pw-icon-hide" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-6.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.83 21.83 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </span>
                        </button>
                    </div>
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
                <div class="auth-actions">
                    <button type="submit" class="btn btn-primary btn-shimmer">Create account</button>
                    <a class="btn btn-outline" href="login.php">I have an account</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Replace previous checkbox logic with icon toggle
(function(){
  document.querySelectorAll('.pw-icon-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var targetId = btn.getAttribute('data-target');
      var input = document.getElementById(targetId);
      if(!input) return;
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.setAttribute('aria-pressed', String(!showing));
      btn.classList.toggle('visible', !showing);
    });
  });
})();
</script>
</body>
</html>

