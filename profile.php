<?php require_once __DIR__ . '/bootstrap.php';
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$username = $email = $first_name = $last_name = $phone = $address = "";
$username_err = $email_err = $first_name_err = $last_name_err = $phone_err = $address_err = "";

// Fetch user data
$sql = "SELECT username, email, first_name, last_name, phone, address FROM users WHERE user_id = ?";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    if($stmt->execute()){
        $stmt->store_result();
        if($stmt->num_rows == 1){
            $stmt->bind_result($username, $email, $first_name, $last_name, $phone, $address);
            $stmt->fetch();
        } else{
            echo "Error: User data not found.";
            exit;
        }
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    $stmt->close();
}

// Process form submission for updating profile
if($_SERVER["REQUEST_METHOD"] == "POST"){

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        echo "Invalid request. Please refresh and try again.";
        exit;
    }

    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter a username.";
    } else{
        if (!preg_match('/^[A-Za-z0-9]+$/', $_POST['username'])) {
            $username_err = "Username must contain only letters and numbers.";
        }
        // Check if username is already taken by another user
        $sql = "SELECT user_id FROM users WHERE username = ? AND user_id != ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("si", $param_username, $_SESSION["user_id"]);
            $param_username = trim($_POST["username"]);
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows == 1){
                    $username_err = "This username is already taken.";
                } else{
                    $username = trim($_POST["username"]);
                }
            }
            $stmt->close();
        }
    }

    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email.";
    } else{
        if (!filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email address.";
        }
        // Check if email is already registered by another user
        $sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("si", $param_email, $_SESSION["user_id"]);
            $param_email = trim($_POST["email"]);
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows == 1){
                    $email_err = "This email is already registered.";
                } else{
                    $email = trim($_POST["email"]);
                }
            }
            $stmt->close();
        }
    }

    // Validate first name
    if(empty(trim($_POST["first_name"]))){
        $first_name_err = "Please enter your first name.";
    } else{
        $first_name = trim($_POST["first_name"]);
    }

    // Validate last name
    if(empty(trim($_POST["last_name"]))){
        $last_name_err = "Please enter your last name.";
    } else{
        $last_name = trim($_POST["last_name"]);
    }

    // Validate phone (optional)
    $phone = trim($_POST["phone"]);
    if ($phone !== '' && mb_strlen($phone) > 30) { $phone_err = "Phone must be 30 characters or less."; }

    // Validate address (optional)
    $address = trim($_POST["address"]);
    if ($address !== '' && mb_strlen($address) > 500) { $address_err = "Address must be 500 characters or less."; }

    // Check input errors before updating in database
    if(empty($username_err) && empty($email_err) && empty($first_name_err) && empty($last_name_err)){
        $sql = "UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, address = ? WHERE user_id = ?";
        if($stmt = $conn->prepare($sql)){
            // Prepare parameter variables and ensure we bind variables (not expressions)
            $param_username = $username;
            $param_email = $email;
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_phone = $phone;
            $param_address = $address;
            $param_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : 0;

            $stmt->bind_param("ssssssi", $param_username, $param_email, $param_first_name, $param_last_name, $param_phone, $param_address, $param_id);

            if($stmt->execute()){
                $_SESSION["username"] = $username; // Update session username
                header("location: profile.php"); // Redirect to refresh page
                exit;
            } else{
                echo "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Profile</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Manage Your Profile</h2>
        <p>Update your account information below.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username ?? ''); ?>">
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($first_name_err)) ? 'has-error' : ''; ?>">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                <span class="help-block"><?php echo $first_name_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($last_name_err)) ? 'has-error' : ''; ?>">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                <span class="help-block"><?php echo $last_name_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($phone_err)) ? 'has-error' : ''; ?>">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="e.g., (555) 123-4567">
                <span class="help-block"><?php echo $phone_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($address_err)) ? 'has-error' : ''; ?>">
                <label>Address</label>
                <textarea name="address" class="form-control" placeholder="Your address"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                <span class="help-block"><?php echo $address_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Update Profile">
                <a href="dashboard.php" class="btn btn-default">Cancel</a>
            </div>
        </form>
        <p><a href="reset_password.php">Reset Your Password</a></p>
    </div>
    </main>
</body>
</html>

