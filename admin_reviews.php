<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: login.php");
    exit;
}

require_once "config.php";

$all_reviews = [];

// Fetch all reviews from the system
$sql = "SELECT r.review_id, r.rating, r.comment, r.review_date,
               u_reviewer.username as reviewer_username, u_reviewer.email as reviewer_email,
               u_reviewed.username as reviewed_username, u_reviewed.email as reviewed_email,
               r.request_id,
               req.request_type,
               CASE 
                   WHEN req.request_type = 'item' THEN i.title 
                   WHEN req.request_type = 'service' THEN s.title 
               END as resource_title
        FROM reviews r
        JOIN users u_reviewer ON r.reviewer_id = u_reviewer.user_id
        JOIN users u_reviewed ON r.reviewed_user_id = u_reviewed.user_id
        LEFT JOIN requests req ON r.request_id = req.request_id
        LEFT JOIN items i ON req.request_type = 'item' AND req.item_id = i.item_id
        LEFT JOIN services s ON req.request_type = 'service' AND req.service_id = s.service_id
        ORDER BY r.review_date DESC";

if($stmt = $conn->prepare($sql)){
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()){
            $all_reviews[] = $row;
        }
        $result->free();
    } else{
        echo "Oops! Something went wrong. Please try again later.";
    }
    $stmt->close();
}

// Handle review deletion
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_review_id"])){
    $review_id = $_POST["delete_review_id"];
    
    $delete_sql = "DELETE FROM reviews WHERE review_id = ?";
    if($stmt = $conn->prepare($delete_sql)){
        $stmt->bind_param("i", $review_id);
        if($stmt->execute()){
            header("location: admin_reviews.php");
            exit;
        }
        $stmt->close();
    }
}

// Calculate statistics
$stats = [
    'total' => count($all_reviews),
    'average_rating' => 0,
    'rating_counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
];

if (!empty($all_reviews)) {
    $total_rating = array_sum(array_column($all_reviews, 'rating'));
    $stats['average_rating'] = round($total_rating / count($all_reviews), 2);
    
    foreach ($all_reviews as $review) {
        $stats['rating_counts'][$review['rating']]++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage All Reviews - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reviews-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .reviews-table th, .reviews-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .reviews-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .rating {
            color: #ffc107;
            font-weight: bold;
        }
        .rating-1 { color: #dc3545; }
        .rating-2 { color: #fd7e14; }
        .rating-3 { color: #ffc107; }
        .rating-4 { color: #20c997; }
        .rating-5 { color: #28a745; }
        .action-buttons form { display: inline-block; margin-right: 5px; }
        .action-buttons .btn { padding: 4px 8px; font-size: 0.8em; }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        .stat-card h4 { margin: 0 0 5px 0; color: #333; }
        .stat-card .number { font-size: 1.5em; font-weight: bold; color: #007cba; }
        .rating-distribution {
            display: flex;
            align-items: center;
            margin: 3px 0;
        }
        .rating-bar {
            flex: 1;
            height: 15px;
            background: #e9ecef;
            margin: 0 10px;
            border-radius: 8px;
            overflow: hidden;
        }
        .rating-fill {
            height: 100%;
            background: #ffc107;
        }
        .comment-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>⭐ Admin - Manage All Reviews</h2>
        <p>Administrative overview of all system reviews and ratings.</p>
        <p>
            <a href="admin_panel.php" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </p>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h4>Total Reviews</h4>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Average Rating</h4>
                <div class="number" style="color: #ffc107;"><?php echo $stats['average_rating']; ?>/5</div>
            </div>
            <div class="stat-card">
                <h4>Rating Distribution</h4>
                <?php for($i = 5; $i >= 1; $i--): ?>
                    <div class="rating-distribution">
                        <span><?php echo $i; ?>★</span>
                        <div class="rating-bar">
                            <div class="rating-fill" style="width: <?php echo $stats['total'] > 0 ? ($stats['rating_counts'][$i] / $stats['total']) * 100 : 0; ?>%;"></div>
                        </div>
                        <span><?php echo $stats['rating_counts'][$i]; ?></span>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="stat-card">
                <h4>Quality Index</h4>
                <div class="number" style="color: <?php echo $stats['average_rating'] >= 4 ? '#28a745' : ($stats['average_rating'] >= 3 ? '#ffc107' : '#dc3545'); ?>;">
                    <?php 
                    if ($stats['average_rating'] >= 4) echo "Excellent";
                    elseif ($stats['average_rating'] >= 3) echo "Good";
                    elseif ($stats['average_rating'] >= 2) echo "Fair";
                    else echo "Poor";
                    ?>
                </div>
            </div>
        </div>

        <?php if (!empty($all_reviews)): ?>
            <table class="reviews-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rating</th>
                        <th>Reviewer</th>
                        <th>Reviewed User</th>
                        <th>Resource</th>
                        <th>Comment</th>
                        <th>Review Date</th>
                        <th>Admin Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_reviews as $review): ?>
                        <tr>
                            <td><?php echo $review["review_id"]; ?></td>
                            <td class="rating rating-<?php echo $review['rating']; ?>">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <span style="color: <?php echo $i <= $review['rating'] ? '#ffc107' : '#ddd'; ?>;">★</span>
                                <?php endfor; ?>
                                (<?php echo $review['rating']; ?>/5)
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($review["reviewer_username"]); ?></strong><br>
                                <small><?php echo htmlspecialchars($review["reviewer_email"]); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($review["reviewed_username"]); ?></strong><br>
                                <small><?php echo htmlspecialchars($review["reviewed_email"]); ?></small>
                            </td>
                            <td>
                                <?php if ($review["resource_title"]): ?>
                                    <strong><?php echo htmlspecialchars($review["resource_title"]); ?></strong><br>
                                    <small>Request #<?php echo $review["request_id"]; ?> (<?php echo ucfirst($review["request_type"]); ?>)</small>
                                <?php else: ?>
                                    <em>General Review</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="comment-preview" title="<?php echo htmlspecialchars($review['comment']); ?>">
                                    <?php echo htmlspecialchars($review['comment']); ?>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($review["review_date"])); ?></td>
                            <td class="action-buttons">
                                <button onclick="showFullComment(<?php echo $review['review_id']; ?>)" class="btn btn-info">👁️ View</button>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" 
                                      onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
                                    <input type="hidden" name="delete_review_id" value="<?php echo $review["review_id"]; ?>">
                                    <input type="submit" class="btn btn-danger" value="🗑️ Delete">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <strong>No reviews found</strong><br>
                There are no reviews in the system yet.
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for viewing full comments -->
    <div id="commentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px; border-radius:8px; max-width:500px; max-height:400px; overflow-y:auto;">
            <h3>Full Review Comment</h3>
            <div id="commentContent"></div>
            <button onclick="document.getElementById('commentModal').style.display='none'" class="btn btn-secondary" style="margin-top:15px;">Close</button>
        </div>
    </div>

    <script>
        const reviews = <?php echo json_encode($all_reviews); ?>;
        
        function showFullComment(reviewId) {
            const review = reviews.find(r => r.review_id == reviewId);
            if (review) {
                document.getElementById('commentContent').innerHTML = '<strong>Rating:</strong> ' + review.rating + '/5<br><br>' +
                    '<strong>Reviewer:</strong> ' + review.reviewer_username + '<br>' +
                    '<strong>Reviewed User:</strong> ' + review.reviewed_username + '<br>' +
                    '<strong>Date:</strong> ' + new Date(review.review_date).toLocaleDateString() + '<br><br>' +
                    '<strong>Comment:</strong><br>' + review.comment.replace(/\n/g, '<br>');
                document.getElementById('commentModal').style.display = 'block';
            }
        }
    </script>
</body>
</html>
