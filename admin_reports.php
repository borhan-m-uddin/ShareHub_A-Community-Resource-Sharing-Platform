<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: login.php");
    exit;
}

require_once "config.php";

// Get comprehensive system statistics
$stats = [];

// User statistics
$sql = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'giver' THEN 1 ELSE 0 END) as giver_count,
    SUM(CASE WHEN role = 'seeker' THEN 1 ELSE 0 END) as seeker_count,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN DATE(registration_date) = CURDATE() THEN 1 ELSE 0 END) as new_today,
    SUM(CASE WHEN DATE(registration_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_this_week,
    SUM(CASE WHEN DATE(registration_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month
FROM users";
$result = $conn->query($sql);
$stats['users'] = $result->fetch_assoc();

// Items statistics
$sql = "SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN availability_status = 'available' THEN 1 ELSE 0 END) as available_items,
    SUM(CASE WHEN availability_status = 'pending' THEN 1 ELSE 0 END) as pending_items,
    SUM(CASE WHEN availability_status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_items,
    SUM(CASE WHEN DATE(posting_date) = CURDATE() THEN 1 ELSE 0 END) as posted_today,
    SUM(CASE WHEN DATE(posting_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as posted_this_week
FROM items";
$result = $conn->query($sql);
$stats['items'] = $result->fetch_assoc();

// Services statistics
$sql = "SELECT 
    COUNT(*) as total_services,
    SUM(CASE WHEN availability = 'available' THEN 1 ELSE 0 END) as available_services,
    SUM(CASE WHEN availability = 'busy' THEN 1 ELSE 0 END) as busy_services,
    SUM(CASE WHEN availability = 'unavailable' THEN 1 ELSE 0 END) as unavailable_services,
    SUM(CASE WHEN DATE(posting_date) = CURDATE() THEN 1 ELSE 0 END) as posted_today,
    SUM(CASE WHEN DATE(posting_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as posted_this_week
FROM services";
$result = $conn->query($sql);
$stats['services'] = $result->fetch_assoc();

// Requests statistics
$sql = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
    SUM(CASE WHEN DATE(request_date) = CURDATE() THEN 1 ELSE 0 END) as requests_today,
    SUM(CASE WHEN DATE(request_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as requests_this_week
FROM requests";
$result = $conn->query($sql);
$stats['requests'] = $result->fetch_assoc();

// Reviews statistics
$sql = "SELECT 
    COUNT(*) as total_reviews,
    AVG(rating) as average_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
    SUM(CASE WHEN DATE(review_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as reviews_this_week
FROM reviews";
$result = $conn->query($sql);
$stats['reviews'] = $result->fetch_assoc();

// Messages statistics
$sql = "SELECT 
    COUNT(*) as total_messages,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_messages,
    SUM(CASE WHEN DATE(sent_date) = CURDATE() THEN 1 ELSE 0 END) as messages_today,
    SUM(CASE WHEN DATE(sent_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as messages_this_week
FROM messages";
$result = $conn->query($sql);
$stats['messages'] = $result->fetch_assoc();

// Category statistics for items
$sql = "SELECT category, COUNT(*) as count FROM items WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY count DESC LIMIT 10";
$result = $conn->query($sql);
$item_categories = [];
while($row = $result->fetch_assoc()) {
    $item_categories[] = $row;
}

// Category statistics for services
$sql = "SELECT category, COUNT(*) as count FROM services WHERE category IS NOT NULL AND category != '' GROUP BY category ORDER BY count DESC LIMIT 10";
$result = $conn->query($sql);
$service_categories = [];
while($row = $result->fetch_assoc()) {
    $service_categories[] = $row;
}

// Most active users
$sql = "SELECT u.username, u.role, 
    (SELECT COUNT(*) FROM items WHERE giver_id = u.user_id) + 
    (SELECT COUNT(*) FROM services WHERE giver_id = u.user_id) as resources_shared,
    (SELECT COUNT(*) FROM requests WHERE requester_id = u.user_id) as requests_made,
    (SELECT COUNT(*) FROM reviews WHERE reviewer_id = u.user_id) as reviews_given
FROM users u 
WHERE u.role != 'admin'
ORDER BY resources_shared + requests_made + reviews_given DESC 
LIMIT 10";
$result = $conn->query($sql);
$active_users = [];
while($row = $result->fetch_assoc()) {
    $active_users[] = $row;
}

// Recent activity
$sql = "SELECT 'user_registration' as type, u.username as details, u.registration_date as date_time 
        FROM users u 
        WHERE DATE(u.registration_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'item_posted' as type, CONCAT(i.title, ' by ', u.username) as details, i.posting_date as date_time 
        FROM items i 
        JOIN users u ON i.giver_id = u.user_id 
        WHERE DATE(i.posting_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'service_posted' as type, CONCAT(s.title, ' by ', u.username) as details, s.posting_date as date_time 
        FROM services s 
        JOIN users u ON s.giver_id = u.user_id 
        WHERE DATE(s.posting_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'request_made' as type, CONCAT('Request by ', u.username) as details, r.request_date as date_time 
        FROM requests r 
        JOIN users u ON r.requester_id = u.user_id 
        WHERE DATE(r.request_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY date_time DESC 
        LIMIT 20";
$result = $conn->query($sql);
$recent_activity = [];
while($row = $result->fetch_assoc()) {
    $recent_activity[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Reports - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .report-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        .report-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .stat-row:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-weight: 500;
            color: #666;
        }
        .stat-value {
            font-weight: bold;
            color: #007cba;
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            margin: 5px 0;
            overflow: hidden;
        }
        .progress-fill {
            background: linear-gradient(90deg, #007cba, #28a745);
            height: 100%;
            transition: width 0.3s ease;
        }
        .rating-distribution {
            display: flex;
            align-items: center;
            margin: 5px 0;
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
        .activity-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #007cba;
        }
        .activity-type {
            font-size: 0.8em;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
        }
        .activity-details {
            color: #333;
            margin: 3px 0;
        }
        .activity-time {
            font-size: 0.8em;
            color: #999;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-card {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
        }
        .summary-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>📊 System Reports & Analytics</h2>
        <p>Comprehensive overview of platform activity and statistics.</p>
        <p>
            <a href="admin_panel.php" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </p>

        <!-- Summary Statistics -->
        <div class="summary-stats">
            <div class="summary-card">
                <div class="summary-number"><?php echo $stats['users']['total_users']; ?></div>
                <div class="summary-label">Total Users</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $stats['items']['total_items']; ?></div>
                <div class="summary-label">Total Items</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $stats['services']['total_services']; ?></div>
                <div class="summary-label">Total Services</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $stats['requests']['total_requests']; ?></div>
                <div class="summary-label">Total Requests</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo $stats['reviews']['total_reviews']; ?></div>
                <div class="summary-label">Total Reviews</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?php echo round($stats['reviews']['average_rating'], 1); ?></div>
                <div class="summary-label">Avg Rating</div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="reports-grid">
            <!-- User Statistics -->
            <div class="report-card">
                <h3>👥 User Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Users:</span>
                    <span class="stat-value"><?php echo $stats['users']['total_users']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Active Users:</span>
                    <span class="stat-value"><?php echo $stats['users']['active_users']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Admins:</span>
                    <span class="stat-value"><?php echo $stats['users']['admin_count']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Givers:</span>
                    <span class="stat-value"><?php echo $stats['users']['giver_count']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Seekers:</span>
                    <span class="stat-value"><?php echo $stats['users']['seeker_count']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">New Today:</span>
                    <span class="stat-value"><?php echo $stats['users']['new_today']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">New This Week:</span>
                    <span class="stat-value"><?php echo $stats['users']['new_this_week']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">New This Month:</span>
                    <span class="stat-value"><?php echo $stats['users']['new_this_month']; ?></span>
                </div>
            </div>

            <!-- Items Statistics -->
            <div class="report-card">
                <h3>📦 Items Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Items:</span>
                    <span class="stat-value"><?php echo $stats['items']['total_items']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Available:</span>
                    <span class="stat-value"><?php echo $stats['items']['available_items']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Pending:</span>
                    <span class="stat-value"><?php echo $stats['items']['pending_items']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Unavailable:</span>
                    <span class="stat-value"><?php echo $stats['items']['unavailable_items']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Posted Today:</span>
                    <span class="stat-value"><?php echo $stats['items']['posted_today']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Posted This Week:</span>
                    <span class="stat-value"><?php echo $stats['items']['posted_this_week']; ?></span>
                </div>
                
                <!-- Availability Progress Bar -->
                <?php if($stats['items']['total_items'] > 0): ?>
                    <div style="margin-top: 15px;">
                        <small>Availability Distribution:</small>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($stats['items']['available_items'] / $stats['items']['total_items']) * 100; ?>%;"></div>
                        </div>
                        <small><?php echo round(($stats['items']['available_items'] / $stats['items']['total_items']) * 100); ?>% Available</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Services Statistics -->
            <div class="report-card">
                <h3>⚙️ Services Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Services:</span>
                    <span class="stat-value"><?php echo $stats['services']['total_services']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Available:</span>
                    <span class="stat-value"><?php echo $stats['services']['available_services']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Busy:</span>
                    <span class="stat-value"><?php echo $stats['services']['busy_services']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Unavailable:</span>
                    <span class="stat-value"><?php echo $stats['services']['unavailable_services']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Posted Today:</span>
                    <span class="stat-value"><?php echo $stats['services']['posted_today']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Posted This Week:</span>
                    <span class="stat-value"><?php echo $stats['services']['posted_this_week']; ?></span>
                </div>
            </div>

            <!-- Requests Statistics -->
            <div class="report-card">
                <h3>📋 Requests Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Requests:</span>
                    <span class="stat-value"><?php echo $stats['requests']['total_requests']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Pending:</span>
                    <span class="stat-value"><?php echo $stats['requests']['pending_requests']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Approved:</span>
                    <span class="stat-value"><?php echo $stats['requests']['approved_requests']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Rejected:</span>
                    <span class="stat-value"><?php echo $stats['requests']['rejected_requests']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Completed:</span>
                    <span class="stat-value"><?php echo $stats['requests']['completed_requests']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Today:</span>
                    <span class="stat-value"><?php echo $stats['requests']['requests_today']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">This Week:</span>
                    <span class="stat-value"><?php echo $stats['requests']['requests_this_week']; ?></span>
                </div>
            </div>

            <!-- Reviews Statistics -->
            <div class="report-card">
                <h3>⭐ Reviews & Ratings</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Reviews:</span>
                    <span class="stat-value"><?php echo $stats['reviews']['total_reviews']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Average Rating:</span>
                    <span class="stat-value"><?php echo round($stats['reviews']['average_rating'], 2); ?>/5</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">This Week:</span>
                    <span class="stat-value"><?php echo $stats['reviews']['reviews_this_week']; ?></span>
                </div>
                
                <?php if($stats['reviews']['total_reviews'] > 0): ?>
                    <!-- Rating Distribution -->
                    <?php for($i = 5; $i >= 1; $i--): ?>
                        <div class="rating-distribution">
                            <span><?php echo $i; ?>★</span>
                            <div class="rating-bar">
                                <div class="rating-fill" style="width: <?php echo ($stats['reviews'][$i == 1 ? 'one_star' : ($i == 2 ? 'two_star' : ($i == 3 ? 'three_star' : ($i == 4 ? 'four_star' : 'five_star')))] / $stats['reviews']['total_reviews']) * 100; ?>%;"></div>
                            </div>
                            <span><?php echo $stats['reviews'][$i == 1 ? 'one_star' : ($i == 2 ? 'two_star' : ($i == 3 ? 'three_star' : ($i == 4 ? 'four_star' : 'five_star')))]; ?></span>
                        </div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>

            <!-- Messages Statistics -->
            <div class="report-card">
                <h3>💬 Messages Statistics</h3>
                <div class="stat-row">
                    <span class="stat-label">Total Messages:</span>
                    <span class="stat-value"><?php echo $stats['messages']['total_messages']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Unread Messages:</span>
                    <span class="stat-value"><?php echo $stats['messages']['unread_messages']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Today:</span>
                    <span class="stat-value"><?php echo $stats['messages']['messages_today']; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">This Week:</span>
                    <span class="stat-value"><?php echo $stats['messages']['messages_this_week']; ?></span>
                </div>
            </div>
        </div>

        <!-- Categories and Active Users -->
        <div class="reports-grid">
            <!-- Item Categories -->
            <?php if (!empty($item_categories)): ?>
                <div class="report-card">
                    <h3>📦 Popular Item Categories</h3>
                    <?php foreach($item_categories as $category): ?>
                        <div class="stat-row">
                            <span class="stat-label"><?php echo htmlspecialchars($category['category']); ?>:</span>
                            <span class="stat-value"><?php echo $category['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Service Categories -->
            <?php if (!empty($service_categories)): ?>
                <div class="report-card">
                    <h3>⚙️ Popular Service Categories</h3>
                    <?php foreach($service_categories as $category): ?>
                        <div class="stat-row">
                            <span class="stat-label"><?php echo htmlspecialchars($category['category']); ?>:</span>
                            <span class="stat-value"><?php echo $category['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Most Active Users -->
            <?php if (!empty($active_users)): ?>
                <div class="report-card">
                    <h3>🏆 Most Active Users</h3>
                    <?php foreach(array_slice($active_users, 0, 8) as $user): ?>
                        <div class="stat-row">
                            <span class="stat-label">
                                <?php echo htmlspecialchars($user['username']); ?> 
                                <small>(<?php echo $user['role']; ?>)</small>
                            </span>
                            <span class="stat-value">
                                <?php echo $user['resources_shared'] + $user['requests_made'] + $user['reviews_given']; ?> activities
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="report-card">
                <h3>🕒 Recent Activity (Last 7 Days)</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($recent_activity)): ?>
                        <?php foreach(array_slice($recent_activity, 0, 15) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-type"><?php echo str_replace('_', ' ', $activity['type']); ?></div>
                                <div class="activity-details"><?php echo htmlspecialchars($activity['details']); ?></div>
                                <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['date_time'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No recent activity recorded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
