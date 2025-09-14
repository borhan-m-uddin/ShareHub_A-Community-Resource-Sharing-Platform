<?php require_once __DIR__ . '/bootstrap.php';
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

$username = $email = $first_name = $last_name = $phone = $address = "";
$username_err = $email_err = $first_name_err = $last_name_err = $phone_err = $address_err = "";
// Constraints aligned with typical DB schemas
$MAX_USERNAME = 32; $MIN_USERNAME = 3;
$MAX_EMAIL = 254;
$MAX_NAME = 50; $MIN_NAME = 2;
$MAX_PHONE = 30; $MIN_PHONE = 7;
$MAX_ADDRESS = 500; $MIN_ADDRESS = 0; // address optional

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

    // Validate username (required, 3-32, letters/numbers/_/.)
    $candidate = trim((string)($_POST['username'] ?? ''));
    if ($candidate === '') {
        $username_err = "Please enter a username.";
    } elseif (mb_strlen($candidate) < $MIN_USERNAME || mb_strlen($candidate) > $MAX_USERNAME) {
        $username_err = "Username must be {$MIN_USERNAME}-{$MAX_USERNAME} characters.";
    } elseif (!preg_match('/^[A-Za-z0-9_.]+$/', $candidate)) {
        $username_err = "Username may include letters, numbers, _ and . only.";
    } else {
        // Case-insensitive uniqueness
        $sql = "SELECT user_id FROM users WHERE LOWER(username) = LOWER(?) AND user_id != ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $candidate, $_SESSION["user_id"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows >= 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = $candidate;
                }
            }
            $stmt->close();
        }
    }

    // Validate email (required, format, max length, uniqueness case-insensitive)
    $emailCand = strtolower(trim((string)($_POST['email'] ?? '')));
    if ($emailCand === '') {
        $email_err = "Please enter an email.";
    } elseif (mb_strlen($emailCand) > $MAX_EMAIL || !filter_var($emailCand, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        $sql = "SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) AND user_id != ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $emailCand, $_SESSION["user_id"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows >= 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = $emailCand;
                }
            }
            $stmt->close();
        }
    }

    // Validate first name (required, 2-50 letters, spaces, '-', ")
    $fnCand = trim((string)($_POST['first_name'] ?? ''));
    if ($fnCand === '') {
        $first_name_err = "Please enter your first name.";
    } elseif (mb_strlen($fnCand) < $MIN_NAME || mb_strlen($fnCand) > $MAX_NAME || !preg_match('/^[\p{L} \-\']+$/u', $fnCand)) {
        $first_name_err = "First name must be {$MIN_NAME}-{$MAX_NAME} letters (spaces, - and ' allowed).";
    } else {
        // Collapse multiple spaces
        $first_name = preg_replace('/\s{2,}/', ' ', $fnCand);
    }

    // Validate last name (required, 2-50 letters)
    $lnCand = trim((string)($_POST['last_name'] ?? ''));
    if ($lnCand === '') {
        $last_name_err = "Please enter your last name.";
    } elseif (mb_strlen($lnCand) < $MIN_NAME || mb_strlen($lnCand) > $MAX_NAME || !preg_match('/^[\p{L} \-\']+$/u', $lnCand)) {
        $last_name_err = "Last name must be {$MIN_NAME}-{$MAX_NAME} letters (spaces, - and ' allowed).";
    } else {
        $last_name = preg_replace('/\s{2,}/', ' ', $lnCand);
    }

    // Validate phone (optional; allow +, digits, spaces, () and -)
    $phoneCand = trim((string)($_POST['phone'] ?? ''));
    if ($phoneCand !== '') {
        if (mb_strlen($phoneCand) > $MAX_PHONE || !preg_match('/^\+?[0-9\s().\-]{7,}$/', $phoneCand)) {
            $phone_err = "Please enter a valid phone number.";
        } else {
            $phone = $phoneCand;
        }
    } else { $phone = ''; }

    // Validate address (optional)
    $addrCand = trim((string)($_POST['address'] ?? ''));
    if ($addrCand !== '' && (mb_strlen($addrCand) < $MIN_ADDRESS || mb_strlen($addrCand) > $MAX_ADDRESS)) {
        $address_err = "Address must be {$MIN_ADDRESS}-{$MAX_ADDRESS} characters.";
    } else {
        $address = $addrCand;
    }

    // Check input errors before updating in database
    if(empty($username_err) && empty($email_err) && empty($first_name_err) && empty($last_name_err) && empty($phone_err) && empty($address_err)){
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
        <?php if($username_err || $email_err || $first_name_err || $last_name_err || $phone_err || $address_err): ?>
            <div class="alert alert-danger">Please fix the errors below.</div>
        <?php endif; ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required minlength="<?php echo $MIN_USERNAME; ?>" maxlength="<?php echo $MAX_USERNAME; ?>" pattern="[A-Za-z0-9_.]+" value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="help-block"><?php echo htmlspecialchars($username_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required maxlength="<?php echo $MAX_EMAIL; ?>" value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="help-block"><?php echo htmlspecialchars($email_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($first_name_err)) ? 'has-error' : ''; ?>">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" required minlength="<?php echo $MIN_NAME; ?>" maxlength="<?php echo $MAX_NAME; ?>" value="<?php echo htmlspecialchars($first_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="help-block"><?php echo htmlspecialchars($first_name_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($last_name_err)) ? 'has-error' : ''; ?>">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required minlength="<?php echo $MIN_NAME; ?>" maxlength="<?php echo $MAX_NAME; ?>" value="<?php echo htmlspecialchars($last_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="help-block"><?php echo htmlspecialchars($last_name_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($phone_err)) ? 'has-error' : ''; ?>">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" maxlength="<?php echo $MAX_PHONE; ?>" pattern="^\+?[0-9\s().\-]{7,}$" value="<?php echo htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., +1 555 123 4567">
                <span class="help-block"><?php echo htmlspecialchars($phone_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="form-group <?php echo (!empty($address_err)) ? 'has-error' : ''; ?>">
                <label>Address</label>
                <textarea name="address" class="form-control" maxlength="<?php echo $MAX_ADDRESS; ?>" placeholder="Your address"><?php echo htmlspecialchars($address ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <span class="help-block"><?php echo htmlspecialchars($address_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Update Profile">
                <a href="dashboard.php" class="btn btn-default">Cancel</a>
            </div>
        </form>
    <p><a href="forgot_password.php">Reset Your Password</a></p>
    </div>
    </main>
</body>
</html>

