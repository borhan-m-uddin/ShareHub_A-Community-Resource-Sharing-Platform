<?php
require_once __DIR__ . "/bootstrap.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'request_item') {
    $item_id = intval($_POST['item_id']);
    $message_text = trim($_POST['message']);
    
    // Get item details and giver ID
    $stmt = $conn->prepare("SELECT giver_id, title FROM items WHERE item_id = ? AND availability_status = 'available'");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $item_data = $result->fetch_assoc();
        $giver_id = $item_data['giver_id'];
        
        // Check if user already has a pending request for this item
        $check_stmt = $conn->prepare("SELECT request_id FROM requests WHERE requester_id = ? AND item_id = ? AND status = 'pending'");
        $check_stmt->bind_param("ii", $_SESSION['user_id'], $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            // Create new request
            $insert_stmt = $conn->prepare("INSERT INTO requests (requester_id, giver_id, item_id, request_type, message, request_date) VALUES (?, ?, ?, 'item', ?, NOW())");
            $insert_stmt->bind_param("iiis", $_SESSION['user_id'], $giver_id, $item_id, $message_text);
            if ($insert_stmt->execute()) {
                $message = "Request sent successfully!";
            } else {
                $error = "Error sending request: " . $conn->error;
            }
            $insert_stmt->close();
        } else {
            $error = "You already have a pending request for this item!";
        }
        $check_stmt->close();
    } else {
        $error = "Item not available!";
    }
    $stmt->close();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$condition_filter = isset($_GET['condition']) ? $_GET['condition'] : '';

// Build query
$where_conditions = ["availability_status = 'available'"];
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

if (!empty($condition_filter)) {
    $where_conditions[] = "condition_status = ?";
    $params[] = $condition_filter;
    $types .= 's';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$sql = "SELECT i.*, u.username as giver_name, u.first_name, u.last_name 
        FROM items i 
        JOIN users u ON i.giver_id = u.user_id 
        $where_clause 
        ORDER BY i.posting_date DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$items_result = $stmt->get_result();

// Get categories for filter
$categories_stmt = $conn->query("SELECT DISTINCT category FROM items WHERE availability_status = 'available' ORDER BY category");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - Community Resource Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>

    <div class="wrapper">
        <h2>🛍️ Browse Available Items</h2>
        <p>Find items shared by your community</p>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Panel -->
        <div class="card">
            <div class="card-body">
            <form method="GET" class="grid" style="grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div class="form-group">
                    <label for="search">Search Items</label>
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
                <div class="form-group">
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition" class="form-control">
                        <option value="">All Conditions</option>
                        <option value="new" <?php echo $condition_filter === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="like_new" <?php echo $condition_filter === 'like_new' ? 'selected' : ''; ?>>Like New</option>
                        <option value="good" <?php echo $condition_filter === 'good' ? 'selected' : ''; ?>>Good</option>
                        <option value="fair" <?php echo $condition_filter === 'fair' ? 'selected' : ''; ?>>Fair</option>
                        <option value="poor" <?php echo $condition_filter === 'poor' ? 'selected' : ''; ?>>Poor</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">🔍 Search</button>
            </form>
            </div>
        </div>

        <!-- Items List -->
        <?php if ($items_result->num_rows > 0): ?>
            <div class="grid grid-auto">
                <?php while ($item = $items_result->fetch_assoc()): ?>
                    <div class="card">
                        <?php if (!empty($item['image_url'])): ?>
                            <img class="item-image" src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h4 style="margin:0 0 6px 0;"><?php echo htmlspecialchars($item['title']); ?></h4>
                            <div style="color:#6b7280; font-size:0.9rem; margin-bottom:8px;">
                                <?php echo htmlspecialchars($item['category']); ?> • Shared by <?php echo htmlspecialchars($item['giver_name']); ?>
                            </div>
                            <?php if (!empty($item['image_url'])): ?>
                                
                            <?php endif; ?>
                            <p style="color:#4b5563; line-height:1.5; margin:8px 0 12px 0;">
                                <?php echo htmlspecialchars($item['description']); ?>
                            </p>
                            <div class="grid" style="gap:6px;">
                                <div><strong>Condition:</strong> <span class="badge badge-<?php echo $item['condition_status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $item['condition_status'])); ?></span></div>
                                <div><strong>Pickup:</strong> <?php echo htmlspecialchars($item['pickup_location']); ?></div>
                                <div><strong>Posted:</strong> <?php echo date('M j, Y', strtotime($item['posting_date'])); ?></div>
                            </div>
                        </div>
                        <div class="card-body" style="border-top:1px solid var(--border); display:flex; justify-content:flex-end;">
                            <button class="btn btn-success" onclick="requestItem(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>')">📨 Request Item</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>🛍️ No Items Found</h3>
                <p>No items match your search criteria.</p>
                <p>Try adjusting your filters or check back later for new items!</p>
            </div>
        <?php endif; ?>

    <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary" style="margin-top:16px;">← Back to Dashboard</a>
    </div>

    <!-- Request Item Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>📨 Request Item</h3>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="request_item">
                    <input type="hidden" name="item_id" id="request_item_id">
                    <div class="form-group">
                        <label for="request_message">Message to Owner</label>
                        <textarea id="request_message" name="message" class="form-control" rows="4" 
                                  placeholder="Tell the owner why you need this item and when you can pick it up..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-default" onclick="closeRequestModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">📨 Send Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function requestItem(itemId, itemTitle) {
            document.getElementById('request_item_id').value = itemId;
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
    </main>
</body>
</html>

