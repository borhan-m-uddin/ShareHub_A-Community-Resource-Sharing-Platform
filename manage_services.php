<?php
session_start();
require_once "config.php";

// Check if user is logged in and is giver or admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || 
   ($_SESSION["role"] !== "giver" && $_SESSION["role"] !== "admin")){
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle service actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category = $_POST['category'];
                $location = trim($_POST['location']);
                
                if (!empty($title) && !empty($description) && !empty($location)) {
                    $stmt = $conn->prepare("INSERT INTO services (giver_id, title, description, category, location, posting_date) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("issss", $_SESSION['user_id'], $title, $description, $category, $location);
                    if ($stmt->execute()) {
                        $message = "Service added successfully!";
                    } else {
                        $error = "Error adding service: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Please fill in all required fields!";
                }
                break;

            case 'update_service':
                $service_id = intval($_POST['service_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category = $_POST['category'];
                $availability = $_POST['availability'];
                $location = trim($_POST['location']);
                
                $stmt = $conn->prepare("UPDATE services SET title = ?, description = ?, category = ?, availability = ?, location = ? WHERE service_id = ? AND giver_id = ?");
                $stmt->bind_param("sssssii", $title, $description, $category, $availability, $location, $service_id, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $message = "Service updated successfully!";
                } else {
                    $error = "Error updating service: " . $conn->error;
                }
                $stmt->close();
                break;

            case 'delete_service':
                $service_id = intval($_POST['service_id']);
                $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ? AND giver_id = ?");
                $stmt->bind_param("ii", $service_id, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $message = "Service deleted successfully!";
                } else {
                    $error = "Error deleting service: " . $conn->error;
                }
                $stmt->close();
                break;
        }
    }
}

// Get user's services
$stmt = $conn->prepare("SELECT * FROM services WHERE giver_id = ? ORDER BY posting_date DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$services_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Community Resource Platform</title>
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

        .add-service-panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
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
        .badge-busy { background: #ffc107; color: #333; }
        .badge-unavailable { background: #6c757d; color: white; }

        .service-actions {
            display: flex;
            gap: 10px;
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
            margin: 2% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .service-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚙️ Manage Your Services</h1>
        <p style="text-align: center; opacity: 0.9;">Offer your skills to the community</p>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add New Service Panel -->
        <div class="add-service-panel">
            <h3 style="margin-bottom: 20px; color: #333;">➕ Offer New Service</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_service">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title">Service Title *</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="Technology">Technology</option>
                            <option value="Education">Education</option>
                            <option value="Health">Health & Wellness</option>
                            <option value="Home Services">Home Services</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Creative">Creative Services</option>
                            <option value="Business">Business Services</option>
                            <option value="Personal Care">Personal Care</option>
                            <option value="Pet Care">Pet Care</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="location">Service Location *</label>
                        <input type="text" id="location" name="location" class="form-control" 
                               placeholder="e.g., Your location, Online, Client's location" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Service Description *</label>
                        <textarea id="description" name="description" class="form-control" 
                                  placeholder="Describe your service, experience, and what you offer..." required></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">➕ Add Service</button>
            </form>
        </div>

        <!-- Services List -->
        <h3 style="margin-bottom: 20px; color: #333;">⚙️ Your Services</h3>
        
        <?php if ($services_result->num_rows > 0): ?>
            <div class="services-grid">
                <?php while ($service = $services_result->fetch_assoc()): ?>
                    <div class="service-card">
                        <div class="service-header">
                            <div class="service-title"><?php echo htmlspecialchars($service['title']); ?></div>
                            <div class="service-category"><?php echo htmlspecialchars($service['category']); ?></div>
                        </div>
                        <div class="service-body">
                            <div class="service-description">
                                <?php echo htmlspecialchars($service['description']); ?>
                            </div>
                            <div class="service-details">
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span class="badge badge-<?php echo $service['availability']; ?>">
                                        <?php echo ucfirst($service['availability']); ?>
                                    </span>
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
                            <button class="btn btn-warning btn-sm" onclick="editService(<?php echo $service['service_id']; ?>)">
                                ✏️ Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['title']); ?>')">
                                🗑️ Delete
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>⚙️ No Services Yet</h3>
                <p>Start helping your community by offering your first service above!</p>
                <p>Services you offer will appear here and be visible to people who need help.</p>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
    </div>

    <!-- Edit Service Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h3>✏️ Edit Service</h3>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_service">
                <input type="hidden" name="service_id" id="edit_service_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_title">Service Title *</label>
                        <input type="text" id="edit_title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_category">Category</label>
                        <select id="edit_category" name="category" class="form-control">
                            <option value="Technology">Technology</option>
                            <option value="Education">Education</option>
                            <option value="Health">Health & Wellness</option>
                            <option value="Home Services">Home Services</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Creative">Creative Services</option>
                            <option value="Business">Business Services</option>
                            <option value="Personal Care">Personal Care</option>
                            <option value="Pet Care">Pet Care</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_availability">Availability</label>
                        <select id="edit_availability" name="availability" class="form-control">
                            <option value="available">Available</option>
                            <option value="busy">Busy</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_location">Service Location *</label>
                        <input type="text" id="edit_location" name="location" class="form-control" required>
                    </div>
                    <div class="form-group full-width">
                        <label for="edit_description">Service Description *</label>
                        <textarea id="edit_description" name="description" class="form-control" required></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning">✏️ Update Service</button>
            </form>
        </div>
    </div>

    <script>
        function editService(serviceId) {
            // Get service data from the server
            fetch(`get_service.php?id=${serviceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_service_id').value = data.service.service_id;
                        document.getElementById('edit_title').value = data.service.title;
                        document.getElementById('edit_category').value = data.service.category;
                        document.getElementById('edit_availability').value = data.service.availability;
                        document.getElementById('edit_location').value = data.service.location;
                        document.getElementById('edit_description').value = data.service.description;
                        document.getElementById('editModal').style.display = 'block';
                    } else {
                        alert('Error loading service data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading service data');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteService(serviceId, serviceTitle) {
            if (confirm(`Are you sure you want to delete "${serviceTitle}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" name="service_id" value="${serviceId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
