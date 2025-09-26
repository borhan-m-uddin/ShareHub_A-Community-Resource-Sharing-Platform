<?php require_once __DIR__ . '/../bootstrap.php'; $status=''; if($_SERVER['REQUEST_METHOD']==='POST') { if(!csrf_verify($_POST['csrf_token'] ?? null)) { $status='Invalid request token.'; } else { $uid=isset($_POST['uid'])?(int)$_POST['uid']:0; if($uid<=0){ $status='Missing user id.'; } elseif(!db_connected()) { $status='Database offline. Try later.'; } else { $cd=120; $now=time(); if(!isset($_SESSION['verify_resend_last'])) $_SESSION['verify_resend_last']=0; if(($now-(int)$_SESSION['verify_resend_last']) < $cd) { $wait=$cd-($now-(int)$_SESSION['verify_resend_last']); $status='Please wait '.$wait.' seconds before requesting again.'; } else { global $conn; $email=''; $username=''; $verified=0; if($stmt=$conn->prepare('SELECT email, username, email_verified FROM users WHERE user_id=? LIMIT 1')) { $stmt->bind_param('i',$uid); if($stmt->execute()) { $res=$stmt->get_result(); if($row=$res->fetch_assoc()){ $email=(string)$row['email']; $username=(string)$row['username']; $verified=(int)$row['email_verified']; } if($res) $res->free(); } $stmt->close(); } if($verified===1){ $status='Already verified.'; } elseif(!$email){ $status='Email not found.'; } else { if(verification_generate_and_send($uid,$email,$username)) { $_SESSION['verify_resend_last']=$now; $status='Verification email sent.'; } else { $status='Failed to send verification email.'; } } } } } } ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Resend Verification Email</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>"></head>
<body>
<?php render_header(); ?>
<div class="container" style="max-width:640px;margin:40px auto;">
    <div class="page-top-actions"><a class="btn btn-outline" href="<?php echo site_href('pages/login.php'); ?>">← Back to login</a></div>
    <h2>Resend Verification Email</h2>
    <?php if($status): ?><div class="alert alert-info"><?php echo e($status); ?></div><?php endif; ?>
    <form method="post">
        <?php echo csrf_field(); ?>
        <label>User ID</label>
        <input type="number" name="uid" min="1" required>
        <button class="btn" type="submit">Resend</button>
    </form>
</div>
</body>
</html>
