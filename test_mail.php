<?php
require_once __DIR__ . '/bootstrap.php';

$to = getenv('TEST_EMAIL') ?: 'you@example.com';
$subject = 'ShareHub PHPMailer Test';
$body = "This is a test email sent at " . date('c') . "\nEnvironment: PHP " . PHP_VERSION . "\n";
echo 'LEN='.strlen(get_setting('smtp_pass'))."\n";
$ok = send_email($to, $subject, $body);

echo $ok ? "Sent to $to\n" : "Failed (see storage/mail.log)\n";
