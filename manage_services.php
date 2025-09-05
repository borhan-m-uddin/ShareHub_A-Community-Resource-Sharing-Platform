<?php
require_once __DIR__ . '/bootstrap.php';
require_login();

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
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = "Invalid request. Please try again.";
    } else {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_service':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category = $_POST['category'];
                $location = trim($_POST['location']);
                
                if (!empty($title) && !empty($description) && !empty($location)) {
                    if (strlen($title) > 200) { $title = substr($title, 0, 200); }
                    if (strlen($description) > 2000) { $description = substr($description, 0, 2000); }
                    if (strlen($location) > 255) { $location = substr($location, 0, 255); }
                    $stmt = $conn->prepare("INSERT INTO services (giver_id, title, description, category, location, posting_date) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("issss", $_SESSION['user_id'], $title, $description, $category, $location);
                    if ($stmt->execute()) {
                        $message = "Service added successfully!";
                    } else {
                        $error = "Error adding service.";
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
                
                if (strlen($title) > 200) { $title = substr($title, 0, 200); }
                if (strlen($description) > 2000) { $description = substr($description, 0, 2000); }
                if (strlen($location) > 255) { $location = substr($location, 0, 255); }
                $stmt = $conn->prepare("UPDATE services SET title = ?, description = ?, category = ?, availability = ?, location = ? WHERE service_id = ? AND giver_id = ?");
                $stmt->bind_param("sssssii", $title, $description, $category, $availability, $location, $service_id, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $message = "Service updated successfully!";
                } else {
                    $error = "Error updating service.";
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
                    $error = "Error deleting service.";
                }
                $stmt->close();
                break;
        }
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include_once 'header.php'; ?>

    <div class="wrapper">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add New Service Panel -->
        <div class="card" style="margin-bottom:18px;">
            <div class="card-header">➕ Offer New Service</div>
            <form method="POST" class="card-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_service">
                <div class="grid grid-2">
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
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="location">Service Location *</label>
                        <input type="text" id="location" name="location" class="form-control" 
                               placeholder="e.g., Your location, Online, Client's location" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="description">Service Description *</label>
                        <textarea id="description" name="description" class="form-control" 
                                  placeholder="Describe your service, experience, and what you offer..." required></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">➕ Add Service</button>
            </form>
        </div>

        <!-- Services List -->
    <h3 style="margin: 10px 0 16px 0; color: var(--text);">⚙️ Your Services</h3>
        
        <?php if ($services_result->num_rows > 0): ?>
            <div class="grid grid-auto">
                <?php while ($service = $services_result->fetch_assoc()): ?>
                    <div class="card">
                        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <div style="font-weight:800; font-size:1.05rem;"><?php echo htmlspecialchars($service['title']); ?></div>
                                <div class="muted" style="font-size:0.9rem; margin-top:2px;"><?php echo htmlspecialchars($service['category']); ?></div>
                            </div>
                            <span class="badge badge-<?php echo $service['availability']; ?>"><?php echo ucfirst($service['availability']); ?></span>
                        </div>
                        <div class="card-body">
                            <div class="muted" style="margin-bottom:12px; line-height:1.5;">
                                <?php echo htmlspecialchars($service['description']); ?>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><span style="font-weight:700; color: var(--text);">Location:</span> <?php echo htmlspecialchars($service['location']); ?></div>
                                <div><span style="font-weight:700; color: var(--text);">Posted:</span> <?php echo date('M j, Y', strtotime($service['posting_date'])); ?></div>
                            </div>
                        </div>
                        <div class="card-body" style="border-top:1px solid var(--border); display:flex; gap:8px;">
                            <button class="btn btn-warning btn-sm" onclick="editService(<?php echo $service['service_id']; ?>)">✏️ Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['title']); ?>')">🗑️ Delete</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <b>⚙️ No Services Yet</b>
                <p style="margin-top:6px;">Start helping your community by offering your first service above!</p>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-default" style="margin-top:12px;">← Back to Dashboard</a>
    </div>

    <!-- Edit Service Modal -->
    <div id="editModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <span style="float:right; cursor:pointer;" onclick="closeEditModal()">&times;</span>
                ✏️ Edit Service
            </div>
            <form method="POST" id="editForm" class="modal-body">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_service">
                <input type="hidden" name="service_id" id="edit_service_id">
                <div class="grid grid-2">
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
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="edit_description">Service Description *</label>
                        <textarea id="edit_description" name="description" class="form-control" required></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-default" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-warning">✏️ Update Service</button>
                </div>
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
                    ${`<?php echo str_replace("`", "\\`", csrf_field()); ?>`}
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
