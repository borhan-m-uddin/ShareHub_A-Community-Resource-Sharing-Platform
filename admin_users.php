<?php
session_start();
require_once "config.php";

// Check if user is logged in and is admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: index.php");
    exit;
}

$message = "";
$error = "";

// Handle user actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_user':
                $user_id = intval($_POST['user_id']);
                if ($user_id != $_SESSION['user_id']) { // Prevent admin from deleting themselves
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    if ($stmt->execute()) {
                        $message = "User deleted successfully!";
                    } else {
                        $error = "Error deleting user: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "You cannot delete your own account!";
                }
                break;

            case 'update_role':
                $user_id = intval($_POST['user_id']);
                $new_role = $_POST['new_role'];
                if ($user_id != $_SESSION['user_id']) { // Prevent admin from changing their own role
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $new_role, $user_id);
                    if ($stmt->execute()) {
                        $message = "User role updated successfully!";
                    } else {
                        $error = "Error updating user role: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "You cannot change your own role!";
                }
                break;

            case 'toggle_status':
                $user_id = intval($_POST['user_id']);
                $new_status = $_POST['new_status'];
                if ($user_id != $_SESSION['user_id']) {
                    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $new_status, $user_id);
                    if ($stmt->execute()) {
                        $message = "User status updated successfully!";
                    } else {
                        $error = "Error updating user status: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $error = "You cannot change your own status!";
                }
                break;

            case 'add_user':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = trim($_POST['password']);
                $role = $_POST['role'];
                
                if (!empty($username) && !empty($email) && !empty($password)) {
                    // Check if username or email already exists
                    $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                    $check_stmt->bind_param("ss", $username, $email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows == 0) {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, registration_date, status) VALUES (?, ?, ?, ?, NOW(), 1)");
                        $stmt->bind_param("ssss", $username, $email, $password_hash, $role);
                        if ($stmt->execute()) {
                            $message = "User added successfully!";
                        } else {
                            $error = "Error adding user: " . $conn->error;
                        }
                        $stmt->close();
                    } else {
                        $error = "Username or email already exists!";
                    }
                    $check_stmt->close();
                } else {
                    $error = "All fields are required!";
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 'i';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

$sql = "SELECT user_id, username, email, first_name, last_name, role, registration_date, last_login, status FROM users $where_clause ORDER BY registration_date DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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

        .container {
            max-width: 1400px;
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

        .controls-panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: end;
        }

        .search-filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
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
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        .table tr:hover {
            background-color: #f8f9fa;
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
        .badge-active { background: #28a745; color: white; }
        .badge-inactive { background: #6c757d; color: white; }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
            border-bottom: 2px solid #667eea;
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

        @media (max-width: 768px) {
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .search-filters {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th,
            .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>👥 User Management</h1>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search and Filter Panel -->
        <div class="controls-panel">
            <div class="controls-grid">
                <form method="GET" class="search-filters">
                    <div class="form-group">
                        <label for="search">Search Users</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Search by username, email, or name..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <label for="role">Filter by Role</label>
                        <select id="role" name="role" class="form-control">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="giver" <?php echo $role_filter === 'giver' ? 'selected' : ''; ?>>Giver</option>
                            <option value="seeker" <?php echo $role_filter === 'seeker' ? 'selected' : ''; ?>>Seeker</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Filter by Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 Search</button>
                </form>
                <button type="button" class="btn btn-success" onclick="openAddUserModal()">➕ Add New User</button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></td>
                            <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><span class="badge badge-<?php echo $user['status'] ? 'active' : 'inactive'; ?>"><?php echo $user['status'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($user['registration_date'])); ?></td>
                            <td><?php echo $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never'; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-warning btn-sm" onclick="openRoleModal(<?php echo $user['user_id']; ?>, '<?php echo $user['role']; ?>')">
                                            🔄 Role
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, <?php echo $user['status'] ? 0 : 1; ?>)">
                                            <?php echo $user['status'] ? '🚫 Deactivate' : '✅ Activate'; ?>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            🗑️ Delete
                                        </button>
                                    <?php else: ?>
                                        <span class="badge badge-admin">Current User</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <a href="admin_panel.php" class="back-link">← Back to Admin Panel</a>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeAddUserModal()">&times;</span>
                <h3>➕ Add New User</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group">
                    <label for="new_username">Username *</label>
                    <input type="text" id="new_username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new_email">Email *</label>
                    <input type="email" id="new_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Password *</label>
                    <input type="password" id="new_password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new_role">Role *</label>
                    <select id="new_role" name="role" class="form-control" required>
                        <option value="seeker">Seeker</option>
                        <option value="giver">Giver</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">➕ Add User</button>
            </form>
        </div>
    </div>

    <!-- Role Change Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeRoleModal()">&times;</span>
                <h3>🔄 Change User Role</h3>
            </div>
            <form method="POST" id="roleForm">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" id="role_user_id">
                <div class="form-group">
                    <label for="new_role_select">New Role</label>
                    <select id="new_role_select" name="new_role" class="form-control" required>
                        <option value="seeker">Seeker</option>
                        <option value="giver">Giver</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning">🔄 Update Role</button>
            </form>
        </div>
    </div>

    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        function openRoleModal(userId, currentRole) {
            document.getElementById('role_user_id').value = userId;
            document.getElementById('new_role_select').value = currentRole;
            document.getElementById('roleModal').style.display = 'block';
        }

        function closeRoleModal() {
            document.getElementById('roleModal').style.display = 'none';
        }

        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function toggleUserStatus(userId, newStatus) {
            const action = newStatus ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="new_status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addUserModal');
            const roleModal = document.getElementById('roleModal');
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == roleModal) {
                roleModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
