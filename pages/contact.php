<?php require_once __DIR__ . '/../bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$success_message = $_SESSION['flash_contact_success'] ?? null; unset($_SESSION['flash_contact_success']);
$error_message = $_SESSION['flash_contact_error'] ?? null; unset($_SESSION['flash_contact_error']);
$min_submit_seconds=3; $max_per_minute=5; $max_per_hour=20; $ip=$_SERVER['REMOTE_ADDR']??'unknown'; $ua=$_SERVER['HTTP_USER_AGENT']??'';
if($_SERVER['REQUEST_METHOD']!=='POST'){ $_SESSION['contact_form_started_at']=time(); }
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='contact_submit'){
    if(!csrf_verify($_POST['csrf_token'] ?? null)) { $error_message='Invalid request. Please try again.'; }
    else {
        $honeypot=trim((string)($_POST['company']??'')); $started=(int)($_SESSION['contact_form_started_at']??(time()-$min_submit_seconds)); $elapsed=time()-$started; $now=time(); $bucket=&$_SESSION['rate_limit']['contact'][$ip]; if(!isset($bucket)||!is_array($bucket)) $bucket=[]; $bucket=array_values(array_filter($bucket, fn($ts)=>($now-(int)$ts)<=3600)); $recent_minute=array_filter($bucket, fn($ts)=>($now-(int)$ts)<=60); $too_fast=(count($recent_minute)>=$max_per_minute)||(count($bucket)>=$max_per_hour);
        if($honeypot!==''||$elapsed<$min_submit_seconds){}
        elseif($too_fast){ $bucket[]=$now; $error_message='You’re sending messages too fast. Please wait a minute and try again.'; }
        else {
            $bucket[]=$now; $name=trim((string)($_POST['name']??'')); $email=trim((string)($_POST['email']??'')); $subject=trim((string)($_POST['subject']??'')); $message=trim((string)($_POST['message']??''));
            if($name===''||$email===''||$subject===''||$message===''){ $error_message='Please fill in all fields.'; }
            elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){ $error_message='Please provide a valid email address.'; }
            else {
                if(strlen($name)>120) $name=substr($name,0,120); if(strlen($subject)>200) $subject=substr($subject,0,200); if(strlen($message)>4000) $message=substr($message,0,4000);
                $defaultContact='burhanuddin49945@gmail.com'; $to=(string)get_setting('contact_email',$defaultContact)?:$defaultContact;
                if(function_exists('db_connected') && db_connected()) { try { @$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(254) NOT NULL,subject VARCHAR(200) NOT NULL,message TEXT NOT NULL,ip VARCHAR(45) NULL,user_agent VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); if($stmtIns=$conn->prepare('INSERT INTO contact_messages (name,email,subject,message,ip,user_agent) VALUES (?,?,?,?,?,?)')){ $stmtIns->bind_param('ssssss',$name,$email,$subject,$message,$ip,$ua); $stmtIns->execute(); $stmtIns->close(); }} catch(Throwable $e){} }
                $safeName=htmlspecialchars($name,ENT_QUOTES,'UTF-8'); $safeEmail=htmlspecialchars($email,ENT_QUOTES,'UTF-8'); $safeSubject=htmlspecialchars($subject,ENT_QUOTES,'UTF-8'); $safeMsgHtml=nl2br(htmlspecialchars($message,ENT_QUOTES,'UTF-8'));
                $bodyHtml='<p><strong>New contact form submission</strong></p>'
                    .'<table style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">'
                    .'<tr><td style="padding:2px 6px;font-weight:bold;">Name:</td><td style="padding:2px 6px;">'.$safeName.'</td></tr>'
                    .'<tr><td style="padding:2px 6px;font-weight:bold;">Email:</td><td style="padding:2px 6px;">'.$safeEmail.'</td></tr>'
                    .'<tr><td style="padding:2px 6px;font-weight:bold;">Subject:</td><td style="padding:2px 6px;">'.$safeSubject.'</td></tr>'
                    .'<tr><td style="padding:2px 6px;font-weight:bold;">IP:</td><td style="padding:2px 6px;">'.htmlspecialchars($ip,ENT_QUOTES,'UTF-8').'</td></tr>'
                    .'<tr><td style="padding:2px 6px;font-weight:bold;">User-Agent:</td><td style="padding:2px 6px;">'.htmlspecialchars(substr($ua,0,240),ENT_QUOTES,'UTF-8').'</td></tr>'
                    .'</table>'
                    .'<hr style="margin:12px 0;border:none;border-top:1px solid #ddd;">'
                    .'<p style="white-space:pre-line;">'.$safeMsgHtml.'</p>';
                send_email($to,'[Contact] '.$subject,$bodyHtml,$email); $success_message='Thanks! Your message has been sent.';
            }
        }
    }
    $_SESSION['contact_form_started_at']=time(); if($success_message!==null) $_SESSION['flash_contact_success']=$success_message; if($error_message!==null) $_SESSION['flash_contact_error']=$error_message; header('Location: contact.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Contact</title><link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>"></head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <h2>Contact</h2>
    <p>You can reach out via email at <a href="mailto:<?php echo htmlspecialchars(get_setting('contact_email','burhanuddin49945@gmail.com'),ENT_QUOTES,'UTF-8'); ?>"><?php echo htmlspecialchars(get_setting('contact_email','burhanuddin49945@gmail.com'),ENT_QUOTES,'UTF-8'); ?></a> or send a message using the form below.</p>
    <?php if($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message,ENT_QUOTES,'UTF-8'); ?></div><?php elseif($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?>
    <div class="card" style="max-width:720px; margin:auto;">
        <form method="post" class="card-body">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="contact_submit">
            <div class="hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                <label for="company">Company</label><input type="text" id="company" name="company" tabindex="-1" autocomplete="off">
            </div>
            <div class="grid grid-2">
                <div class="form-group"><label for="name">Your Name</label><input type="text" id="name" name="name" class="form-control" required></div>
                <div class="form-group"><label for="email">Your Email</label><input type="email" id="email" name="email" class="form-control" required></div>
                <div class="form-group" style="grid-column:1 / -1;"><label for="subject">Subject</label><input type="text" id="subject" name="subject" class="form-control" required></div>
                <div class="form-group" style="grid-column:1 / -1;"><label for="message">Message</label><textarea id="message" name="message" class="form-control" rows="6" required></textarea></div>
            </div>
            <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
</div>
<?php render_footer(); ?>
</body>
</html>
