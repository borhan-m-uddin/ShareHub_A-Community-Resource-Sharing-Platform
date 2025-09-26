<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();

// Only givers or admins
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || (!in_array($_SESSION['role'], ['giver', 'admin'], true))) {
    header('Location: ' . site_href('pages/dashboard.php'));
    exit;
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
        switch ($action) {
            case 'add_service':
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $category = (string)($_POST['category'] ?? 'Other');
                $location = trim((string)($_POST['location'] ?? ''));
                if (!$title || !$description || !$location) {
                    $error = 'Please fill in all required fields!';
                }
                if (!$error) {
                    if (strlen($title) > 200) $title = substr($title, 0, 200);
                    if (strlen($description) > 2000) $description = substr($description, 0, 2000);
                    if (strlen($location) > 255) $location = substr($location, 0, 255);
                    $id = service_create([
                        'giver_id' => $_SESSION['user_id'],
                        'title' => $title,
                        'description' => $description,
                        'category' => $category,
                        'availability' => 'available',
                        'location' => $location,
                    ]);
                    if ($id) {
                        $message = 'Service added successfully!';
                    } else {
                        $error = 'Error adding service.';
                    }
                }
                break;
            case 'update_service':
                $service_id = (int)($_POST['service_id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $category = (string)($_POST['category'] ?? 'Other');
                $availability = (string)($_POST['availability'] ?? 'available');
                $location = trim((string)($_POST['location'] ?? ''));
                if (strlen($title) > 200) $title = substr($title, 0, 200);
                if (strlen($description) > 2000) $description = substr($description, 0, 2000);
                if (strlen($location) > 255) $location = substr($location, 0, 255);
                if (service_update_owned($service_id, $_SESSION['user_id'], [
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'availability' => $availability,
                    'location' => $location,
                ])) {
                    $message = 'Service updated successfully!';
                } else {
                    $error = 'Error updating service.';
                }
                break;
            case 'delete_service':
                $service_id = (int)($_POST['service_id'] ?? 0);
                if (service_delete($service_id, $_SESSION['user_id'])) {
                    $message = 'Service deleted successfully!';
                } else {
                    $error = 'Error deleting service.';
                }
                break;
        }
    }
}

// Fetch services via helper
$services = services_list(['giver_id' => $_SESSION['user_id']], 100, 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - Community Resource Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>

<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <div class="page-top-actions">
            <a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-default">← Back to Dashboard</a>
        </div>
        <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

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
                        <input type="text" id="location" name="location" class="form-control" placeholder="e.g., Your location, Online, Client's location" required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="description">Service Description *</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Describe your service, experience, and what you offer..." required></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">➕ Add Service</button>
            </form>
        </div>

        <h3 style="margin: 10px 0 16px 0; color: var(--text);">⚙️ Your Services</h3>
        <?php if (!empty($services)): ?>
            <div class="grid grid-auto">
                <?php foreach ($services as $service): ?>
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
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <b>⚙️ No Services Yet</b>
                <p style="margin-top:6px;">Start helping your community by offering your first service above!</p>
            </div>
        <?php endif; ?>
    </div>

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
            fetch('<?php echo site_href('pages/api/get_service.php'); ?>?id=' + serviceId)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_service_id').value = data.service.service_id;
                        document.getElementById('edit_title').value = data.service.title;
                        document.getElementById('edit_category').value = data.service.category;
                        document.getElementById('edit_availability').value = data.service.availability;
                        document.getElementById('edit_location').value = data.service.location;
                        document.getElementById('edit_description').value = data.service.description;
                        document.getElementById('editModal').classList.add('open');
                    } else {
                        alert('Error loading service data');
                    }
                })
                .catch(() => alert('Error loading service data'));
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('open');
        }

        function deleteService(serviceId, serviceTitle) {
            if (confirm('Are you sure you want to delete "' + serviceTitle + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<?php echo str_replace('`', '\\`', csrf_field()); ?>` +
                    '<input type="hidden" name="action" value="delete_service">' +
                    '<input type="hidden" name="service_id" value="' + serviceId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        window.onclick = function(e) {
            const m = document.getElementById('editModal');
            if (e.target === m) {
                m.classList.remove('open');
            }
        }
    </script>
    <?php render_footer(); ?>
</body>

</html>