<?php require_once __DIR__ . '/bootstrap.php'; 

$success_message = null;
$error_message = null;

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_submit') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        // Simple validation
        if ($name === '' || $email === '' || $subject === '' || $message === '') {
            $error_message = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please provide a valid email address.';
        } else {
            // Soft limits
            if (strlen($name) > 120) { $name = substr($name, 0, 120); }
            if (strlen($subject) > 200) { $subject = substr($subject, 0, 200); }
            if (strlen($message) > 4000) { $message = substr($message, 0, 4000); }

            // Determine destination email (defaults to your address if not set in settings)
            $to = (string)get_setting('contact_email', 'borhanudiin1902@gmail.com');
            if ($to === '') { $to = 'borhanudiin1902@gmail.com'; }

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $body = "New contact form submission\n\n" .
                    "Name: {$name}\n" .
                    "Email: {$email}\n" .
                    "Subject: {$subject}\n" .
                    "IP: {$ip}\n" .
                    "User-Agent: {$ua}\n" .
                    "-----\n" .
                    "Message:\n{$message}\n";

            // Attempt to send using helper (falls back to log if mail not configured)
            if (send_email($to, '[Contact] ' . $subject, $body, $email)) {
                $success_message = 'Thanks! Your message has been sent.';
            } else {
                // Even if sending fails, it's logged; show a generic success to avoid spam probing
                $success_message = 'Thanks! Your message has been sent.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Contact</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <h2>Contact</h2>
    <p>You can reach out via email at <a href="mailto:borhanudiin1902@gmail.com">borhanudiin1902@gmail.com</a> or send a message using the form below.</p>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php elseif ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:720px">
        <form method="post" class="card-body">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="contact_submit">
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Your Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-control" required>
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" class="form-control" rows="6" required></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
</div>
<?php render_footer(); ?>
</body>
</html>
