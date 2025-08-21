<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle service request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'request_service') {
    $service_id = intval($_POST['service_id']);
    $message_text = trim($_POST['message']);
    
    // Get service details and giver ID
    $stmt = $conn->prepare("SELECT giver_id, title FROM services WHERE service_id = ? AND availability = 'available'");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $service_data = $result->fetch_assoc();
        $giver_id = $service_data['giver_id'];
        
        // Check if user already has a pending request for this service
        $check_stmt = $conn->prepare("SELECT request_id FROM requests WHERE requester_id = ? AND service_id = ? AND status = 'pending'");
        $check_stmt->bind_param("ii", $_SESSION['user_id'], $service_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            // Create new request
            $insert_stmt = $conn->prepare("INSERT INTO requests (requester_id, giver_id, service_id, request_type, message, request_date) VALUES (?, ?, ?, 'service', ?, NOW())");
            $insert_stmt->bind_param("iiis", $_SESSION['user_id'], $giver_id, $service_id, $message_text);
            if ($insert_stmt->execute()) {
                $message = "Service request sent successfully!";
            } else {
                $error = "Error sending request: " . $conn->error;
            }
            $insert_stmt->close();
        } else {
            $error = "You already have a pending request for this service!";
        }
        $check_stmt->close();
    } else {
        $error = "Service not available!";
    }
    $stmt->close();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build query
$where_conditions = ["availability = 'available'"];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$sql = "SELECT s.*, u.username as giver_name, u.first_name, u.last_name 
        FROM services s 
        JOIN users u ON s.giver_id = u.user_id 
        $where_clause 
        ORDER BY s.posting_date DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$services_result = $stmt->get_result();

// Get categories for filter
$categories_stmt = $conn->query("SELECT DISTINCT category FROM services WHERE availability = 'available' ORDER BY category");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services - Community Resource Platform</title>
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

        .header {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .search-panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .search-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #6f42c1;
            color: white;
        }

        .btn-primary:hover {
            background: #5a32a3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .service-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
        }

        .service-header {
            background: #6f42c1;
            color: white;
            padding: 15px;
        }

        .service-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .service-category {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .service-giver {
            opacity: 0.8;
            font-size: 0.85em;
            margin-top: 5px;
        }

        .service-body {
            padding: 20px;
        }

        .service-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .service-details {
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: bold;
            color: #333;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .badge-available { background: #28a745; color: white; }

        .service-actions {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #6f42c1;
        }

        .modal-header h3 {
            color: #333;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #333;
        }

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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 15px;
            color: #333;
        }

        @media (max-width: 768px) {
            .search-grid {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Browse Available Services</h1>
        <p style="text-align: center; opacity: 0.9;">Find skills and help from your community</p>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Panel -->
        <div class="search-panel">
            <form method="GET" class="search-grid">
                <div class="form-group">
                    <label for="search">Search Services</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           placeholder="Search by title or description..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php while ($cat = $categories_stmt->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">🔍 Search</button>
            </form>
        </div>

        <!-- Services List -->
        <?php if ($services_result->num_rows > 0): ?>
            <div class="services-grid">
                <?php while ($service = $services_result->fetch_assoc()): ?>
                    <div class="service-card">
                        <div class="service-header">
                            <div class="service-title"><?php echo htmlspecialchars($service['title']); ?></div>
                            <div class="service-category"><?php echo htmlspecialchars($service['category']); ?></div>
                            <div class="service-giver">
                                Offered by: <?php echo htmlspecialchars($service['giver_name']); ?>
                            </div>
                        </div>
                        <div class="service-body">
                            <div class="service-description">
                                <?php echo htmlspecialchars($service['description']); ?>
                            </div>
                            <div class="service-details">
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span class="badge badge-available">Available</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Location:</span>
                                    <span><?php echo htmlspecialchars($service['location']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Posted:</span>
                                    <span><?php echo date('M j, Y', strtotime($service['posting_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="service-actions">
                            <button class="btn btn-success" onclick="requestService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['title']); ?>')">
                                📞 Request Service
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>⚙️ No Services Found</h3>
                <p>No services match your search criteria.</p>
                <p>Try adjusting your filters or check back later for new services!</p>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <!-- Request Service Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeRequestModal()">&times;</span>
                <h3>📞 Request Service</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="request_service">
                <input type="hidden" name="service_id" id="request_service_id">
                <div class="form-group">
                    <label for="request_message">Message to Service Provider</label>
                    <textarea id="request_message" name="message" class="form-control" rows="4" 
                              placeholder="Describe what you need help with, when you need it, and any other relevant details..."></textarea>
                </div>
                <button type="submit" class="btn btn-success">📞 Send Request</button>
            </form>
        </div>
    </div>

    <script>
        function requestService(serviceId, serviceTitle) {
            document.getElementById('request_service_id').value = serviceId;
            document.getElementById('requestModal').style.display = 'block';
        }

        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>

