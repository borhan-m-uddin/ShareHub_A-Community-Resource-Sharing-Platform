<?php
session_start();
require_once "config.php";

// Check if user is logged in and is admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: index.php");
    exit;
}

// Get platform statistics
$stats = [];

// User statistics
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while($row = $result->fetch_assoc()) {
    $stats['users_by_role'][$row['role']] = $row['count'];
}

// Items statistics
$result = $conn->query("SELECT COUNT(*) as total FROM items");
$stats['total_items'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT availability_status, COUNT(*) as count FROM items GROUP BY availability_status");
while($row = $result->fetch_assoc()) {
    $stats['items_by_status'][$row['availability_status']] = $row['count'];
}

// Services statistics
$result = $conn->query("SELECT COUNT(*) as total FROM services");
$stats['total_services'] = $result->fetch_assoc()['total'];

// Requests statistics
$result = $conn->query("SELECT COUNT(*) as total FROM requests");
$stats['total_requests'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status");
while($row = $result->fetch_assoc()) {
    $stats['requests_by_status'][$row['status']] = $row['count'];
}

// Reviews statistics
$result = $conn->query("SELECT COUNT(*) as total FROM reviews");
$stats['total_reviews'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT AVG(rating) as avg_rating FROM reviews");
$stats['average_rating'] = round($result->fetch_assoc()['avg_rating'], 2);

// Recent activities
$recent_users = $conn->query("SELECT user_id, username, email, role, registration_date FROM users ORDER BY registration_date DESC LIMIT 5");
$recent_items = $conn->query("SELECT i.item_id, i.title, u.username as giver, i.posting_date FROM items i JOIN users u ON i.giver_id = u.user_id ORDER BY i.posting_date DESC LIMIT 5");
$recent_requests = $conn->query("SELECT r.request_id, r.status, u1.username as requester, u2.username as giver, r.request_date FROM requests r JOIN users u1 ON r.requester_id = u1.user_id LEFT JOIN users u2 ON r.giver_id = u2.user_id ORDER BY r.request_date DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Community Resource Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }

        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-header h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .admin-header p {
            text-align: center;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-nav {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .nav-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .nav-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 1.1em;
        }

        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .recent-activity h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .activity-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-details {
            flex: 1;
        }

        .activity-date {
            color: #666;
            font-size: 0.9em;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .badge-admin { background: #dc3545; color: white; }
        .badge-giver { background: #28a745; color: white; }
        .badge-seeker { background: #007bff; color: white; }
        .badge-available { background: #28a745; color: white; }
        .badge-unavailable { background: #6c757d; color: white; }
        .badge-pending { background: #ffc107; color: #333; }
        .badge-approved { background: #28a745; color: white; }
        .badge-rejected { background: #dc3545; color: white; }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .back-link:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🛠️ Admin Control Panel</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?> | Community Resource Platform Management</p>
    </div>

    <div class="container">
        <!-- Navigation Panel -->
        <div class="admin-nav">
            <div class="nav-grid">
                <a href="admin_users.php" class="nav-item">👥 Manage Users</a>
                <a href="admin_items.php" class="nav-item">📦 Manage Items</a>
                <a href="admin_services.php" class="nav-item">⚙️ Manage Services</a>
                <a href="admin_requests.php" class="nav-item">📋 Manage Requests</a>
                <a href="admin_reviews.php" class="nav-item">⭐ Manage Reviews</a>
                <a href="admin_messages.php" class="nav-item">💬 Monitor Messages</a>
                <a href="admin_reports.php" class="nav-item">📊 Reports & Analytics</a>
                <a href="admin_settings.php" class="nav-item">⚙️ Platform Settings</a>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <div style="margin-top: 10px;">
                    <?php if(isset($stats['users_by_role'])): ?>
                        <?php foreach($stats['users_by_role'] as $role => $count): ?>
                            <span class="badge badge-<?php echo $role; ?>"><?php echo ucfirst($role); ?>: <?php echo $count; ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                <div class="stat-label">Total Items</div>
                <div style="margin-top: 10px;">
                    <?php if(isset($stats['items_by_status'])): ?>
                        <?php foreach($stats['items_by_status'] as $status => $count): ?>
                            <span class="badge badge-<?php echo $status; ?>"><?php echo ucfirst($status); ?>: <?php echo $count; ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_services']; ?></div>
                <div class="stat-label">Total Services</div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                <div class="stat-label">Total Requests</div>
                <div style="margin-top: 10px;">
                    <?php if(isset($stats['requests_by_status'])): ?>
                        <?php foreach($stats['requests_by_status'] as $status => $count): ?>
                            <span class="badge badge-<?php echo $status; ?>"><?php echo ucfirst($status); ?>: <?php echo $count; ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_reviews']; ?></div>
                <div class="stat-label">Total Reviews</div>
                <div style="margin-top: 10px;">
                    <span class="badge badge-available">Avg Rating: <?php echo $stats['average_rating']; ?>⭐</span>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3>👥 Recent User Registrations</h3>
            <?php while($user = $recent_users->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-details">
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                        <br>
                        <small><?php echo htmlspecialchars($user['email']); ?></small>
                    </div>
                    <div class="activity-date"><?php echo date('M j, Y', strtotime($user['registration_date'])); ?></div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="recent-activity">
            <h3>📦 Recent Items Posted</h3>
            <?php while($item = $recent_items->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-details">
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                        <br>
                        <small>by <?php echo htmlspecialchars($item['giver']); ?></small>
                    </div>
                    <div class="activity-date"><?php echo date('M j, Y', strtotime($item['posting_date'])); ?></div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="recent-activity">
            <h3>📋 Recent Requests</h3>
            <?php while($request = $recent_requests->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-details">
                        <strong>Request #<?php echo $request['request_id']; ?></strong>
                        <span class="badge badge-<?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span>
                        <br>
                        <small>From: <?php echo htmlspecialchars($request['requester']); ?> 
                        <?php if($request['giver']): ?>to <?php echo htmlspecialchars($request['giver']); ?><?php endif; ?></small>
                    </div>
                    <div class="activity-date"><?php echo date('M j, Y', strtotime($request['request_date'])); ?></div>
                </div>
            <?php endwhile; ?>
        </div>

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>
</body>
</html>
