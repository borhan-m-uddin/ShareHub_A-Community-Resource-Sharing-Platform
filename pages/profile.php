<?php require_once __DIR__ . '/../bootstrap.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ' . site_href('login.php'));
    exit;
}

$username = $email = $first_name = $last_name = $phone = $address = '';
$username_err = $email_err = $first_name_err = $last_name_err = $phone_err = $address_err = '';
$MAX_USERNAME = 32;
$MIN_USERNAME = 3;
$MAX_EMAIL = 254;
$MAX_NAME = 50;
$MIN_NAME = 2;
$MAX_PHONE = 30;
$MIN_PHONE = 7;
$MAX_ADDRESS = 500;
$MIN_ADDRESS = 0;

// Fetch user data
if (function_exists('db_connected') && db_connected()) {
    $sql = 'SELECT username,email,first_name,last_name,phone,address FROM users WHERE user_id = ? LIMIT 1';
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $_SESSION['user_id']);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($username, $email, $first_name, $last_name, $phone, $address);
                $stmt->fetch();
            }
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        echo 'Invalid request. Please refresh and try again.';
        exit;
    }
    $candidate = trim((string)($_POST['username'] ?? ''));
    if ($candidate === '') {
        $username_err = 'Please enter a username.';
    } elseif (mb_strlen($candidate) < $MIN_USERNAME || mb_strlen($candidate) > $MAX_USERNAME) {
        $username_err = "Username must be {$MIN_USERNAME}-{$MAX_USERNAME} characters.";
    } elseif (!preg_match('/^[A-Za-z0-9_.]+$/', $candidate)) {
        $username_err = 'Username may include letters, numbers, _ and . only.';
    } else {
        if (function_exists('db_connected') && db_connected()) {
            $sql = 'SELECT user_id FROM users WHERE LOWER(username)=LOWER(?) AND user_id != ?';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('si', $candidate, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows >= 1) {
                        $username_err = 'This username is already taken.';
                    } else {
                        $username = $candidate;
                    }
                }
                $stmt->close();
            }
        }
    }
    $emailCand = strtolower(trim((string)($_POST['email'] ?? '')));
    if ($emailCand === '') {
        $email_err = 'Please enter an email.';
    } elseif (mb_strlen($emailCand) > $MAX_EMAIL || !filter_var($emailCand, FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Please enter a valid email address.';
    } else {
        if (function_exists('db_connected') && db_connected()) {
            $sql = 'SELECT user_id FROM users WHERE LOWER(email)=LOWER(?) AND user_id != ?';
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('si', $emailCand, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows >= 1) {
                        $email_err = 'This email is already registered.';
                    } else {
                        $email = $emailCand;
                    }
                }
                $stmt->close();
            }
        }
    }
    $fnCand = trim((string)($_POST['first_name'] ?? ''));
    if ($fnCand === '') {
        $first_name_err = 'Please enter your first name.';
    } elseif (mb_strlen($fnCand) < $MIN_NAME || mb_strlen($fnCand) > $MAX_NAME || !preg_match('/^[\p{L} \-\']+$/u', $fnCand)) {
        $first_name_err = "First name must be {$MIN_NAME}-{$MAX_NAME} letters (spaces, - and ' allowed).";
    } else {
        $first_name = preg_replace('/\s{2,}/', ' ', $fnCand);
    }
    $lnCand = trim((string)($_POST['last_name'] ?? ''));
    if ($lnCand === '') {
        $last_name_err = 'Please enter your last name.';
    } elseif (mb_strlen($lnCand) < $MIN_NAME || mb_strlen($lnCand) > $MAX_NAME || !preg_match('/^[\p{L} \-\']+$/u', $lnCand)) {
        $last_name_err = "Last name must be {$MIN_NAME}-{$MAX_NAME} letters (spaces, - and ' allowed).";
    } else {
        $last_name = preg_replace('/\s{2,}/', ' ', $lnCand);
    }
    $phoneCand = trim((string)($_POST['phone'] ?? ''));
    if ($phoneCand !== '') {
        if (mb_strlen($phoneCand) > $MAX_PHONE || !preg_match('/^\+?[0-9\s().\-]{7,}$/', $phoneCand)) {
            $phone_err = 'Please enter a valid phone number.';
        } else {
            $phone = $phoneCand;
        }
    } else {
        $phone = '';
    }
    $addrCand = trim((string)($_POST['address'] ?? ''));
    if ($addrCand !== '' && (mb_strlen($addrCand) < $MIN_ADDRESS || mb_strlen($addrCand) > $MAX_ADDRESS)) {
        $address_err = "Address must be {$MIN_ADDRESS}-{$MAX_ADDRESS} characters.";
    } else {
        $address = $addrCand;
    }
    if (!$username_err && !$email_err && !$first_name_err && !$last_name_err && !$phone_err && !$address_err) {
        if (function_exists('db_connected') && db_connected()) {
            if ($stmt = $conn->prepare('UPDATE users SET username=?, email=?, first_name=?, last_name=?, phone=?, address=? WHERE user_id=?')) {
                $param_id = $_SESSION['user_id'];
                // 6 strings + 1 integer => 'ssssssi'
                $stmt->bind_param('ssssssi', $username, $email, $first_name, $last_name, $phone, $address, $param_id);
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    if (function_exists('flash_set')) { flash_set('success', 'Your profile has been updated.'); }
                    header('Location: ' . site_href('pages/profile.php'));
                    exit;
                }
                $stmt->close();
            }
        }
    }
}
// Read any success message after redirect
$flash_success = function_exists('flash_get') ? flash_get('success') : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body><?php render_header(); ?>
    <div class="wrapper">
        <h2>Manage Your Profile</h2>
        <p>Update your account information below.</p>
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success"><?php echo e($flash_success); ?></div>
        <?php endif; ?>
        <?php if ($username_err || $email_err || $first_name_err || $last_name_err || $phone_err || $address_err): ?><div class="alert alert-danger">Please fix the errors below.</div><?php endif; ?>
        <form action="" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group <?php echo $username_err ? 'has-error' : ''; ?>">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required minlength="<?php echo $MIN_USERNAME; ?>" maxlength="<?php echo $MAX_USERNAME; ?>" pattern="[A-Za-z0-9_.]+" value="<?php echo e($username); ?>">
                <span class="help-block"><?php echo e($username_err); ?></span>
            </div>
            <div class="form-group <?php echo $email_err ? 'has-error' : ''; ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required maxlength="<?php echo $MAX_EMAIL; ?>" value="<?php echo e($email); ?>">
                <span class="help-block"><?php echo e($email_err); ?></span>
            </div>
            <div class="form-group <?php echo $first_name_err ? 'has-error' : ''; ?>">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" required minlength="<?php echo $MIN_NAME; ?>" maxlength="<?php echo $MAX_NAME; ?>" value="<?php echo e($first_name); ?>">
                <span class="help-block"><?php echo e($first_name_err); ?></span>
            </div>
            <div class="form-group <?php echo $last_name_err ? 'has-error' : ''; ?>">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required minlength="<?php echo $MIN_NAME; ?>" maxlength="<?php echo $MAX_NAME; ?>" value="<?php echo e($last_name); ?>">
                <span class="help-block"><?php echo e($last_name_err); ?></span>
            </div>
            <div class="form-group <?php echo $phone_err ? 'has-error' : ''; ?>">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" maxlength="<?php echo $MAX_PHONE; ?>" pattern="^\+?[0-9\s().\-]{7,}$" value="<?php echo e($phone); ?>" placeholder="e.g., +1 555 123 4567">
                <span class="help-block"><?php echo e($phone_err); ?></span>
            </div>
            <div class="form-group <?php echo $address_err ? 'has-error' : ''; ?>">
                <label>Address</label>
                <textarea name="address" class="form-control" maxlength="<?php echo $MAX_ADDRESS; ?>" placeholder="Your address"><?php echo e($address); ?></textarea>
                <span class="help-block"><?php echo e($address_err); ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Update Profile">
                <a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
    <p><a href="<?php echo site_href('pages/forgot_password.php'); ?>">Reset Your Password</a></p>
    </div>
    <?php render_footer(); ?>
</body>

</html>