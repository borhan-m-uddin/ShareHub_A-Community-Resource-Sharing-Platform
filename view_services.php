<?php
require_once __DIR__ . '/bootstrap.php';

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle service request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'request_service') {
    // CSRF check
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = "Invalid request. Please try again.";
    } else {
        $service_id = max(0, intval($_POST['service_id'] ?? 0));
        $message_text = trim((string)($_POST['message'] ?? ''));
        if (strlen($message_text) > 1000) { $message_text = substr($message_text, 0, 1000); }
        
        // Get service details and giver ID
        $stmt = $conn->prepare("SELECT giver_id, title FROM services WHERE service_id = ? AND availability = 'available'");
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $service_data = $result->fetch_assoc();
            $giver_id = (int)$service_data['giver_id'];
            
            // Check if user already has a pending request for this service
            $check_stmt = $conn->prepare("SELECT request_id FROM requests WHERE requester_id = ? AND service_id = ? AND status = 'pending'");
            $uid = (int)($_SESSION['user_id'] ?? 0);
            $check_stmt->bind_param("ii", $uid, $service_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                // Create new request
                $insert_stmt = $conn->prepare("INSERT INTO requests (requester_id, giver_id, service_id, request_type, message, request_date) VALUES (?, ?, ?, 'service', ?, NOW())");
                $insert_stmt->bind_param("iiis", $uid, $giver_id, $service_id, $message_text);
                if ($insert_stmt->execute()) {
                    $message = "Service request sent successfully!";
                } else {
                    $error = "Error sending request.";
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

if (db_connected()) {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $services_result = $stmt->get_result();
} else {
    $services_result = new class { public $num_rows = 0; public function fetch_assoc(){ return null; } };
}

// Get categories for filter
$categories_stmt = null;
if (db_connected()) {
    if ($catStmt = $conn->prepare("SELECT DISTINCT category FROM services WHERE availability = 'available' ORDER BY category")) {
        $catStmt->execute();
        $categories_stmt = $catStmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services - Community Resource Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>

    <div class="wrapper">
        <h2>⚙️ Browse Available Services</h2>
        <p>Find skills and help from your community</p>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Panel -->
        <div class="card">
            <div class="card-body">
            <form method="GET" class="grid" style="grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: end;">
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
                        <?php if ($categories_stmt): while ($cat = $categories_stmt->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($cat['category'], ENT_QUOTES, 'UTF-8'); ?>" 
                                    <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">🔍 Search</button>
            </form>
            </div>
        </div>

        <!-- Services List -->
        <?php if ($services_result->num_rows > 0): ?>
            <div class="grid grid-auto">
                <?php while ($service = $services_result->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-body">
                            <h4 style="margin:0 0 6px 0;"><?php echo htmlspecialchars($service['title']); ?></h4>
                                <h4 style="margin:0 0 6px 0;"><?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <div class="muted" style="font-size:0.9rem; margin-bottom:8px;">
                                    <?php echo htmlspecialchars($service['category'], ENT_QUOTES, 'UTF-8'); ?> • Offered by <?php echo htmlspecialchars($service['giver_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <p class="muted" style="line-height:1.5; margin:8px 0 12px 0;">
                                    <?php echo htmlspecialchars($service['description'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <div class="grid" style="gap:6px;">
                                <div><strong>Status:</strong> <span class="badge badge-available">Available</span></div>
                                <div><strong>Location:</strong> <?php echo htmlspecialchars($service['location']); ?></div>
                                    <div><strong>Location:</strong> <?php echo htmlspecialchars($service['location'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div><strong>Posted:</strong> <?php echo date('M j, Y', strtotime($service['posting_date'])); ?></div>
                            </div>
                        </div>
                        <div class="card-body" style="border-top:1px solid var(--border); display:flex; justify-content:flex-end;">
                            <button class="btn btn-success" onclick="requestService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['title']); ?>')">📞 Request Service</button>
                                <button class="btn btn-success" onclick="requestService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8'); ?>')">📞 Request Service</button>
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

    <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary" style="margin-top:16px;">← Back to Dashboard</a>
    </div>

    <!-- Request Service Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>📞 Request Service</h3>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="request_service">
                    <input type="hidden" name="service_id" id="request_service_id">
                    <div class="form-group">
                        <label for="request_message">Message to Service Provider</label>
                        <textarea id="request_message" name="message" class="form-control" rows="4" 
                                  placeholder="Describe what you need help with, when you need it, and any other relevant details..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-default" onclick="closeRequestModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">📞 Send Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function requestService(serviceId, serviceTitle) {
            document.getElementById('request_service_id').value = serviceId;
            document.getElementById('requestModal').classList.add('open');
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.remove('open');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        }
    </script>
    </main>
</body>
</html>

