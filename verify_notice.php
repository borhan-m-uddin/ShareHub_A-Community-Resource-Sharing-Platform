<?php
require_once __DIR__ . '/bootstrap.php';
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Your Email</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="container" style="max-width:640px;margin:40px auto;">
	<h2>Email Verification Required</h2>
	<p>We sent a verification link to your email address. Please check your inbox (and spam folder).</p>
	<p>If you did not receive the email you can request another below.</p>
	<form method="post" action="verify_resend.php" style="margin-top:20px;">
		<?php echo csrf_field(); ?>
		<input type="hidden" name="uid" value="<?php echo htmlspecialchars((string)$uid, ENT_QUOTES, 'UTF-8'); ?>">
		<button class="btn" type="submit">Resend verification email</button>
		<a class="btn btn-outline" href="login.php">Back to login</a>
	</form>
</div>
</body>
</html>
