<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$reviews_given = [];
$reviews_received = [];

// Submit review
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_review'])){
    if(!csrf_verify($_POST['csrf_token'] ?? null)){
        $error_message='Invalid request. Please try again.';
    } else {
        $reviewed_user_id=(int)($_POST['reviewed_user_id']??0);
        $request_id=(int)($_POST['request_id']??0);
        $rating=(int)($_POST['rating']??0); if($rating<1||$rating>5) $rating=5;
        $comment=trim((string)($_POST['comment']??'')); if(strlen($comment)>2000) $comment=substr($comment,0,2000);
        if($reviewed_user_id>0 && $request_id>0 && $comment!==''){
            $check_sql="SELECT review_id FROM reviews WHERE reviewer_id=? AND request_id=?";
            if($st=$conn->prepare($check_sql)){ $st->bind_param('ii',$user_id,$request_id); $st->execute(); $res=$st->get_result(); if($res->num_rows===0){
                $sql="INSERT INTO reviews (reviewer_id,reviewed_user_id,request_id,rating,comment) VALUES (?,?,?,?,?)";
                if($ins=$conn->prepare($sql)){ $ins->bind_param('iiiis',$user_id,$reviewed_user_id,$request_id,$rating,$comment); if($ins->execute()){ $success_message='Review submitted successfully!'; } else { $error_message='Error submitting review.'; } $ins->close(); }
            } else { $error_message='You have already reviewed this request.'; } $st->close(); }
        } else { $error_message='Please complete all required fields.'; }
    }
}

// Reviewable requests
$reviewable_requests=[];
$sql="SELECT r.request_id,r.request_type,r.requester_id,r.giver_id,
        CASE WHEN r.request_type='item' THEN i.title WHEN r.request_type='service' THEN s.title END AS resource_title,
        CASE WHEN r.requester_id=? THEN u_giver.username WHEN r.giver_id=? THEN u_requester.username END AS other_user,
        CASE WHEN r.requester_id=? THEN u_giver.user_id WHEN r.giver_id=? THEN u_requester.user_id END AS other_user_id,
        CASE WHEN r.requester_id=? THEN 'giver' WHEN r.giver_id=? THEN 'requester' END AS review_type
      FROM requests r
      LEFT JOIN items i ON r.request_type='item' AND r.item_id=i.item_id
      LEFT JOIN services s ON r.request_type='service' AND r.service_id=s.service_id
      LEFT JOIN users u_giver ON r.giver_id=u_giver.user_id
      LEFT JOIN users u_requester ON r.requester_id=u_requester.user_id
      WHERE r.status='completed' AND (r.requester_id=? OR r.giver_id=?)
        AND r.request_id NOT IN (SELECT request_id FROM reviews WHERE reviewer_id=? AND request_id IS NOT NULL)
      ORDER BY r.response_date DESC";
if($st=$conn->prepare($sql)){
    $st->bind_param('iiiiiiiii',$user_id,$user_id,$user_id,$user_id,$user_id,$user_id,$user_id,$user_id,$user_id);
    if($st->execute()){ $res=$st->get_result(); while($row=$res->fetch_assoc()) $reviewable_requests[]=$row; }
    $st->close();
}

// Reviews given
$sql="SELECT r.review_id,r.rating,r.comment,r.review_date,u.username AS reviewed_user,req.request_id,
        CASE WHEN req.request_type='item' THEN i.title WHEN req.request_type='service' THEN s.title END AS resource_title
      FROM reviews r
      JOIN users u ON r.reviewed_user_id=u.user_id
      LEFT JOIN requests req ON r.request_id=req.request_id
      LEFT JOIN items i ON req.request_type='item' AND req.item_id=i.item_id
      LEFT JOIN services s ON req.request_type='service' AND req.service_id=s.service_id
      WHERE r.reviewer_id=? ORDER BY r.review_date DESC";
if($st=$conn->prepare($sql)){ $st->bind_param('i',$user_id); if($st->execute()){ $res=$st->get_result(); while($row=$res->fetch_assoc()) $reviews_given[]=$row; } $st->close(); }

// Reviews received
$sql="SELECT r.review_id,r.rating,r.comment,r.review_date,u.username AS reviewer_user,req.request_id,
        CASE WHEN req.request_type='item' THEN i.title WHEN req.request_type='service' THEN s.title END AS resource_title
      FROM reviews r
      JOIN users u ON r.reviewer_id=u.user_id
      LEFT JOIN requests req ON r.request_id=req.request_id
      LEFT JOIN items i ON req.request_type='item' AND req.item_id=i.item_id
      LEFT JOIN services s ON req.request_type='service' AND req.service_id=s.service_id
      WHERE r.reviewed_user_id=? ORDER BY r.review_date DESC";
if($st=$conn->prepare($sql)){ $st->bind_param('i',$user_id); if($st->execute()){ $res=$st->get_result(); while($row=$res->fetch_assoc()) $reviews_received[]=$row; } $st->close(); }

$avg_rating=0; if($reviews_received){ $total_rating=array_sum(array_column($reviews_received,'rating')); $avg_rating=round($total_rating/count($reviews_received),1); }
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Reviews & Ratings</title>
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head><body>
<?php render_header(); ?>
<div class="wrapper">
    <div class="page-top-actions"><a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-default">← Back to Dashboard</a></div>
    <h2>⭐ Reviews & Ratings</h2>
    <p>Share your experience and build trust in the community.</p>
    <?php if(isset($success_message)): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if(isset($error_message)): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>
    <div class="card" style="margin-bottom:16px;"><div class="card-body" style="text-align:center;">
        <h3>Your Community Rating</h3>
        <div class="text-warning-700" style="font-size:2em;font-weight:800;margin:6px 0; "><?php echo $avg_rating; ?>/5</div>
        <div class="stars">
            <?php for($i=1;$i<=5;$i++): ?>
                <?php if($i<=floor($avg_rating)): ?><span>★</span><?php else: ?><span class="star-empty">★</span><?php endif; ?>
            <?php endfor; ?>
        </div>
        <p>Based on <?php echo count($reviews_received); ?> review(s)</p>
    </div></div>
    <?php if($reviewable_requests): ?>
    <div class="card" style="margin-bottom:16px;"><div class="card-body">
        <h3>📝 Submit a Review</h3>
        <p class="muted">You have completed transactions that can be reviewed:</p>
        <form action="<?php echo site_href('reviews.php'); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group"><label for="request_id">Select Completed Request:</label>
                <select name="request_id" id="request_id" class="form-control" required onchange="updateReviewForm()">
                    <option value="">Choose a request to review...</option>
                    <?php foreach($reviewable_requests as $req): ?>
                        <option value="<?php echo $req['request_id']; ?>" data-user-id="<?php echo $req['other_user_id']; ?>" data-user-name="<?php echo htmlspecialchars($req['other_user']); ?>" data-resource="<?php echo htmlspecialchars($req['resource_title']); ?>" data-type="<?php echo $req['review_type']; ?>">
                            Request #<?php echo $req['request_id']; ?> - <?php echo htmlspecialchars($req['resource_title']); ?> (<?php echo ucfirst($req['review_type']); ?>: <?php echo htmlspecialchars($req['other_user']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="reviewed_user_id" id="reviewed_user_id">
            <div class="form-group"><label>Rating:</label>
                <div class="rating-stars" role="radiogroup" aria-label="Rating">
                    <?php for($r=5;$r>=1;$r--): ?>
                        <input type="radio" name="rating" value="<?php echo $r; ?>" id="star<?php echo $r; ?>" <?php echo $r===5?'required':''; ?>>
                        <label for="star<?php echo $r; ?>" title="<?php echo $r; ?> star<?php echo $r>1?'s':''; ?>" aria-label="<?php echo $r; ?> star<?php echo $r>1?'s':''; ?>">★</label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="form-group"><label for="comment">Review Comment:</label>
                <textarea name="comment" id="comment" class="form-control" rows="4" placeholder="Share your experience with this transaction..." required></textarea>
            </div>
            <div class="form-group"><input type="submit" name="submit_review" value="📤 Submit Review" class="btn btn-primary"></div>
        </form>
    </div></div>
    <?php endif; ?>
    <div class="grid grid-2">
        <div class="card"><div class="card-body">
            <h3>📤 Reviews You've Given (<?php echo count($reviews_given); ?>)</h3>
            <?php if($reviews_given): ?><div class="list">
                <?php foreach($reviews_given as $review): ?>
                    <div class="list-item">
                        <div class="rating"><div class="stars">
                            <?php for($i=1;$i<=5;$i++): ?>
                                <?php if($i <= (int)$review['rating']): ?><span>★</span><?php else: ?><span class="star-empty">★</span><?php endif; ?>
                            <?php endfor; ?>
                        </div><strong><?php echo $review['rating']; ?>/5</strong></div>
                        <p><strong>To:</strong> <?php echo htmlspecialchars($review['reviewed_user']); ?></p>
                        <p><strong>For:</strong> <?php echo htmlspecialchars($review['resource_title'] ?: 'General review'); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        <small class="muted"><?php echo date('M j, Y g:i A', strtotime($review['review_date'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div><?php else: ?><p>You haven't given any reviews yet.</p><?php endif; ?>
        </div></div>
        <div class="card"><div class="card-body">
            <h3>📥 Reviews You've Received (<?php echo count($reviews_received); ?>)</h3>
            <?php if($reviews_received): ?><div class="list">
                <?php foreach($reviews_received as $review): ?>
                    <div class="list-item">
                        <div class="rating"><div class="stars">
                            <?php for($i=1;$i<=5;$i++): ?>
                                <?php if($i <= (int)$review['rating']): ?><span>★</span><?php else: ?><span class="star-empty">★</span><?php endif; ?>
                            <?php endfor; ?>
                        </div><strong><?php echo $review['rating']; ?>/5</strong></div>
                        <p><strong>From:</strong> <?php echo htmlspecialchars($review['reviewer_user']); ?></p>
                        <p><strong>For:</strong> <?php echo htmlspecialchars($review['resource_title'] ?: 'General review'); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        <small class="muted"><?php echo date('M j, Y g:i A', strtotime($review['review_date'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div><?php else: ?><p>You haven't received any reviews yet.</p><?php endif; ?>
        </div></div>
    </div>
</div>
<script>
function updateReviewForm(){
  const sel=document.getElementById('request_id');
  const opt=sel.options[sel.selectedIndex];
  const tgt=document.getElementById('reviewed_user_id');
  tgt.value=opt && opt.value? opt.getAttribute('data-user-id') : '';
}
document.querySelectorAll('.rating-stars label').forEach(l=>{
  l.addEventListener('click',()=>{ const forId=l.getAttribute('for'); const input=document.getElementById(forId); if(input) input.checked=true; });
});
</script>
<?php render_footer(); ?>
</body></html>