<?php
// Initialize the session
session_start();

// Check if the user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "config.php";

$user_id = $_SESSION["user_id"];
$reviews_given = [];
$reviews_received = [];

// Handle submitting a new review
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_review"])){
    $reviewed_user_id = $_POST["reviewed_user_id"];
    $request_id = $_POST["request_id"];
    $rating = $_POST["rating"];
    $comment = $_POST["comment"];
    
    // Check if user has already reviewed this request
    $check_sql = "SELECT review_id FROM reviews WHERE reviewer_id = ? AND request_id = ?";
    if($check_stmt = $conn->prepare($check_sql)){
        $check_stmt->bind_param("ii", $user_id, $request_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows == 0){
            $sql = "INSERT INTO reviews (reviewer_id, reviewed_user_id, request_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
            if($stmt = $conn->prepare($sql)){
                $stmt->bind_param("iiiis", $user_id, $reviewed_user_id, $request_id, $rating, $comment);
                if($stmt->execute()){
                    $success_message = "Review submitted successfully!";
                } else {
                    $error_message = "Error submitting review.";
                }
                $stmt->close();
            }
        } else {
            $error_message = "You have already reviewed this request.";
        }
        $check_stmt->close();
    }
}

// Get completed requests that can be reviewed
$reviewable_requests = [];
$sql = "SELECT r.request_id, r.request_type, r.requester_id, r.giver_id,
               CASE 
                   WHEN r.request_type = 'item' THEN i.title 
                   WHEN r.request_type = 'service' THEN s.title 
               END as resource_title,
               CASE 
                   WHEN r.requester_id = ? THEN u_giver.username
                   WHEN r.giver_id = ? THEN u_requester.username
               END as other_user,
               CASE 
                   WHEN r.requester_id = ? THEN u_giver.user_id
                   WHEN r.giver_id = ? THEN u_requester.user_id
               END as other_user_id,
               CASE 
                   WHEN r.requester_id = ? THEN 'giver'
                   WHEN r.giver_id = ? THEN 'requester'
               END as review_type
        FROM requests r
        LEFT JOIN items i ON r.request_type = 'item' AND r.item_id = i.item_id
        LEFT JOIN services s ON r.request_type = 'service' AND r.service_id = s.service_id
        LEFT JOIN users u_giver ON r.giver_id = u_giver.user_id
        LEFT JOIN users u_requester ON r.requester_id = u_requester.user_id
        WHERE r.status = 'completed' AND (r.requester_id = ? OR r.giver_id = ?)
        AND r.request_id NOT IN (SELECT request_id FROM reviews WHERE reviewer_id = ? AND request_id IS NOT NULL)
        ORDER BY r.response_date DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $reviewable_requests[] = $row;
        }
    }
    $stmt->close();
}

// Fetch reviews given by the user
$sql = "SELECT r.review_id, r.rating, r.comment, r.review_date, u.username as reviewed_user,
               req.request_id,
               CASE 
                   WHEN req.request_type = 'item' THEN i.title 
                   WHEN req.request_type = 'service' THEN s.title 
               END as resource_title
        FROM reviews r
        JOIN users u ON r.reviewed_user_id = u.user_id
        LEFT JOIN requests req ON r.request_id = req.request_id
        LEFT JOIN items i ON req.request_type = 'item' AND req.item_id = i.item_id
        LEFT JOIN services s ON req.request_type = 'service' AND req.service_id = s.service_id
        WHERE r.reviewer_id = ?
        ORDER BY r.review_date DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $user_id);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $reviews_given[] = $row;
        }
    }
    $stmt->close();
}

// Fetch reviews received by the user
$sql = "SELECT r.review_id, r.rating, r.comment, r.review_date, u.username as reviewer_user,
               req.request_id,
               CASE 
                   WHEN req.request_type = 'item' THEN i.title 
                   WHEN req.request_type = 'service' THEN s.title 
               END as resource_title
        FROM reviews r
        JOIN users u ON r.reviewer_id = u.user_id
        LEFT JOIN requests req ON r.request_id = req.request_id
        LEFT JOIN items i ON req.request_type = 'item' AND req.item_id = i.item_id
        LEFT JOIN services s ON req.request_type = 'service' AND req.service_id = s.service_id
        WHERE r.reviewed_user_id = ?
        ORDER BY r.review_date DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $user_id);
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $reviews_received[] = $row;
        }
    }
    $stmt->close();
}

// Calculate average rating received
$avg_rating = 0;
if(!empty($reviews_received)){
    $total_rating = array_sum(array_column($reviews_received, 'rating'));
    $avg_rating = round($total_rating / count($reviews_received), 1);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reviews & Ratings</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .review-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .review-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .review-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 4px solid #007cba;
        }
        .rating {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .stars {
            color: #ffc107;
            margin-right: 10px;
        }
        .form-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background-color: #007cba; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .rating-summary {
            text-align: center;
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }
        .rating-summary .avg-rating {
            font-size: 2em;
            font-weight: bold;
            color: #856404;
        }
        .rating-input {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }
        .rating-input input[type="radio"] {
            display: none;
        }
        .rating-input label {
            font-size: 1.5em;
            color: #ddd;
            cursor: pointer;
        }
        .rating-input input[type="radio"]:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: #ffc107;
        }
        @media (max-width: 768px) {
            .review-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>⭐ Reviews & Ratings</h2>
        <p>Share your experience and build trust in the community.</p>
        <p>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </p>

        <?php if(isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Rating Summary -->
        <div class="rating-summary">
            <h3>Your Community Rating</h3>
            <div class="avg-rating"><?php echo $avg_rating; ?>/5</div>
            <div class="stars">
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <span style="color: <?php echo $i <= $avg_rating ? '#ffc107' : '#ddd'; ?>;">★</span>
                <?php endfor; ?>
            </div>
            <p>Based on <?php echo count($reviews_received); ?> review(s)</p>
        </div>

        <!-- Submit Review Form -->
        <?php if (!empty($reviewable_requests)): ?>
            <div class="form-section">
                <h3>📝 Submit a Review</h3>
                <p>You have completed transactions that can be reviewed:</p>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label for="request_id">Select Completed Request:</label>
                        <select name="request_id" id="request_id" class="form-control" required onchange="updateReviewForm()">
                            <option value="">Choose a request to review...</option>
                            <?php foreach($reviewable_requests as $req): ?>
                                <option value="<?php echo $req['request_id']; ?>" 
                                        data-user-id="<?php echo $req['other_user_id']; ?>"
                                        data-user-name="<?php echo htmlspecialchars($req['other_user']); ?>"
                                        data-resource="<?php echo htmlspecialchars($req['resource_title']); ?>"
                                        data-type="<?php echo $req['review_type']; ?>">
                                    Request #<?php echo $req['request_id']; ?> - <?php echo htmlspecialchars($req['resource_title']); ?> 
                                    (<?php echo ucfirst($req['review_type']); ?>: <?php echo htmlspecialchars($req['other_user']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="reviewed_user_id" id="reviewed_user_id">

                    <div class="form-group">
                        <label>Rating:</label>
                        <div class="rating-input">
                            <input type="radio" name="rating" value="5" id="star5" required>
                            <label for="star5">★</label>
                            <input type="radio" name="rating" value="4" id="star4">
                            <label for="star4">★</label>
                            <input type="radio" name="rating" value="3" id="star3">
                            <label for="star3">★</label>
                            <input type="radio" name="rating" value="2" id="star2">
                            <label for="star2">★</label>
                            <input type="radio" name="rating" value="1" id="star1">
                            <label for="star1">★</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="comment">Review Comment:</label>
                        <textarea name="comment" id="comment" class="form-control" rows="4" 
                                  placeholder="Share your experience with this transaction..." required></textarea>
                    </div>

                    <div class="form-group">
                        <input type="submit" name="submit_review" value="📤 Submit Review" class="btn btn-primary">
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Reviews Display -->
        <div class="review-container">
            <!-- Reviews Given -->
            <div class="review-section">
                <h3>📤 Reviews You've Given (<?php echo count($reviews_given); ?>)</h3>
                <?php if (!empty($reviews_given)): ?>
                    <?php foreach($reviews_given as $review): ?>
                        <div class="review-item">
                            <div class="rating">
                                <div class="stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span style="color: <?php echo $i <= $review['rating'] ? '#ffc107' : '#ddd'; ?>;">★</span>
                                    <?php endfor; ?>
                                </div>
                                <strong><?php echo $review['rating']; ?>/5</strong>
                            </div>
                            <p><strong>To:</strong> <?php echo htmlspecialchars($review['reviewed_user']); ?></p>
                            <p><strong>For:</strong> <?php echo htmlspecialchars($review['resource_title'] ?: 'General review'); ?></p>
                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <small class="text-muted">
                                <?php echo date('M j, Y g:i A', strtotime($review['review_date'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>You haven't given any reviews yet.</p>
                <?php endif; ?>
            </div>

            <!-- Reviews Received -->
            <div class="review-section">
                <h3>📥 Reviews You've Received (<?php echo count($reviews_received); ?>)</h3>
                <?php if (!empty($reviews_received)): ?>
                    <?php foreach($reviews_received as $review): ?>
                        <div class="review-item">
                            <div class="rating">
                                <div class="stars">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <span style="color: <?php echo $i <= $review['rating'] ? '#ffc107' : '#ddd'; ?>;">★</span>
                                    <?php endfor; ?>
                                </div>
                                <strong><?php echo $review['rating']; ?>/5</strong>
                            </div>
                            <p><strong>From:</strong> <?php echo htmlspecialchars($review['reviewer_user']); ?></p>
                            <p><strong>For:</strong> <?php echo htmlspecialchars($review['resource_title'] ?: 'General review'); ?></p>
                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <small class="text-muted">
                                <?php echo date('M j, Y g:i A', strtotime($review['review_date'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>You haven't received any reviews yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function updateReviewForm() {
            const select = document.getElementById('request_id');
            const selectedOption = select.options[select.selectedIndex];
            const userIdInput = document.getElementById('reviewed_user_id');
            
            if (selectedOption.value) {
                userIdInput.value = selectedOption.getAttribute('data-user-id');
            } else {
                userIdInput.value = '';
            }
        }

        // Star rating interaction
        const stars = document.querySelectorAll('.rating-input label');
        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                const rating = 5 - index;
                document.querySelector(`input[value="${rating}"]`).checked = true;
            });
        });
    </script>
</body>
</html>
