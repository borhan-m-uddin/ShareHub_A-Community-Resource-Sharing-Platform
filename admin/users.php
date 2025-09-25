<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

// Handle actions: change role, toggle active, delete user
$errors = [];
$notices = [];

function post($key, $default = null)
{
    return $_POST[$key] ?? $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = post('action');
        $user_id = (int) post('user_id', 0);
        if ($user_id <= 0) {
            $errors[] = 'Invalid user id.';
        }

        if (empty($errors)) {
            // Prevent self destructive changes
            $self_id = $_SESSION['user_id'] ?? 0;
            if ($action === 'delete' && $user_id === $self_id) {
                $errors[] = 'You cannot delete your own account.';
            } elseif ($action === 'role') {
                $new_role = post('new_role');
                $allowed_roles = ['admin', 'giver', 'seeker'];
                if (!in_array($new_role, $allowed_roles, true)) {
                    $errors[] = 'Invalid role selected.';
                } else {
                    // Don't let the last admin demote self easily; but we'll allow if there are other admins
                    if ($user_id === $self_id && $new_role !== 'admin') {
                        // Count other admins
                        $row = ['c' => 0];
                        if ($st = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'admin' AND user_id <> ?")) {
                            $st->bind_param('i', $self_id);
                            if ($st->execute()) {
                                $r = $st->get_result();
                                if ($r) {
                                    $row = $r->fetch_assoc() ?: ['c' => 0];
                                    $r->free();
                                }
                            }
                            $st->close();
                        }
                        if ((int)$row['c'] === 0) {
                            $errors[] = 'You are the only admin. Create another admin before changing your role.';
                        }
                    }
                    if (empty($errors)) {
                        if ($stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?")) {
                            $stmt->bind_param('si', $new_role, $user_id);
                            $stmt->execute();
                            $stmt->close();
                            $notices[] = 'Role updated.';
                        }
                    }
                }
            } elseif ($action === 'toggle_active') {
                // Toggle status 1/0
                $res = $conn->prepare("UPDATE users SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE user_id = ?");
                if ($res) {
                    $res->bind_param('i', $user_id);
                    $res->execute();
                    $res->close();
                    $notices[] = 'User status toggled.';
                }
            } elseif ($action === 'delete') {
                // Disallow deleting admins to be safe
                $role = null;
                if ($stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?")) {
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $stmt->bind_result($role);
                    $stmt->fetch();
                    $stmt->close();
                }
                if ($role === 'admin') {
                    $errors[] = 'Refusing to delete an admin account.';
                }
                if (empty($errors)) {
                    // Delete dependent data in a safe order
                    // Messages
                    if ($stmt = $conn->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")) {
                        $stmt->bind_param('ii', $user_id, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    // Reviews (as reviewer or reviewed)
                    if ($stmt = $conn->prepare("DELETE FROM reviews WHERE reviewer_id = ? OR reviewed_user_id = ?")) {
                        $stmt->bind_param('ii', $user_id, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    // Requests directly tied to user
                    if ($stmt = $conn->prepare("DELETE FROM requests WHERE requester_id = ? OR giver_id = ?")) {
                        $stmt->bind_param('ii', $user_id, $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    // Requests for items posted by user, then delete those items
                    if ($stmt = $conn->prepare("SELECT item_id FROM items WHERE giver_id = ?")) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        while ($row = $res->fetch_assoc()) {
                            $iid = (int)$row['item_id'];
                            if ($d = $conn->prepare("DELETE FROM requests WHERE item_id = ?")) {
                                $d->bind_param('i', $iid);
                                $d->execute();
                                $d->close();
                            }
                        }
                        $stmt->close();
                    }
                    if ($stmt = $conn->prepare("DELETE FROM items WHERE giver_id = ?")) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    // Requests for services posted by user, then delete those services
                    if ($stmt = $conn->prepare("SELECT service_id FROM services WHERE giver_id = ?")) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        while ($row = $res->fetch_assoc()) {
                            $sid = (int)$row['service_id'];
                            if ($d = $conn->prepare("DELETE FROM requests WHERE service_id = ?")) {
                                $d->bind_param('i', $sid);
                                $d->execute();
                                $d->close();
                            }
                        }
                        $stmt->close();
                    }
                    if ($stmt = $conn->prepare("DELETE FROM services WHERE giver_id = ?")) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    // Finally delete user
                    if ($stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?")) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->close();
                        $notices[] = 'User deleted.';
                    }
                }
            }
        }
    }
}

// Filters & pagination
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$role = isset($_GET['role']) && in_array($_GET['role'], ['admin', 'giver', 'seeker'], true) ? $_GET['role'] : '';
$statusF = isset($_GET['status']) && in_array($_GET['status'], ['1', '0'], true) ? $_GET['status'] : '';
$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$clauses = [];
$params = [];
$types = '';
if ($q !== '') {
    if (ctype_digit($q)) {
        $clauses[] = 'user_id=?';
        $params[] = (int)$q;
        $types .= 'i';
    }
    $clauses[] = '(username LIKE ? OR email LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $types .= 's';
    $params[] = $like;
    $types .= 's';
}
if ($role !== '') {
    $clauses[] = 'role=?';
    $params[] = $role;
    $types .= 's';
}
if ($statusF !== '') {
    $clauses[] = 'status=?';
    $params[] = (int)$statusF;
    $types .= 'i';
}
if ($from !== '') {
    $clauses[] = 'DATE(registration_date) >= ?';
    $params[] = $from;
    $types .= 's';
}
if ($to !== '') {
    $clauses[] = 'DATE(registration_date) <= ?';
    $params[] = $to;
    $types .= 's';
}
$where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

// Load users list with pagination
$users = [];
$sql = "SELECT user_id, username, email, role, status, registration_date FROM users $where ORDER BY registration_date DESC LIMIT $perPage OFFSET $offset";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
        $res->free();
    }
    $stmt->close();
}
// Count total
$total = 0;
$sqlc = "SELECT COUNT(*) c FROM users $where";
if ($st = $conn->prepare($sqlc)) {
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    if ($st->execute()) {
        $r = $st->get_result();
        $row = $r->fetch_assoc();
        $total = (int)($row['c'] ?? 0);
        $r->free();
    }
    $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin - Users</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.85em;
            background: var(--card);
        }
    </style>
</head>

<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <div class="page-top-actions">
            <a href="<?php echo site_href('admin/panel.php'); ?>" class="btn btn-primary">← Back to Admin Panel</a>
            <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-secondary">Dashboard</a>
        </div>
        <h2>👥 Admin - Manage Users</h2>

        <form method="get" class="filter-bar" style="margin-bottom:12px;">
            <input class="form-control" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by username/email/ID" />
            <select class="form-control" name="role">
                <option value="">All roles</option>
                <?php foreach (ROLES as $r) {
                    $sel = $role === $r ? 'selected' : '';
                    echo "<option value=\"$r\" $sel>" . ucfirst($r) . "</option>";
                } ?>
            </select>
            <select class="form-control" name="status">
                <option value="">All statuses</option>
                <option value="1" <?php echo $statusF === '1' ? 'selected' : ''; ?>>Active</option>
                <option value="0" <?php echo $statusF === '0' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" />
            <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" />
            <button class="btn btn-default" type="submit">Filter</button>
        </form>

        <div class="muted" style="margin-bottom:8px;">Showing <?php echo count($users); ?> of <?php echo (int)$total; ?><?php if ($total > $perPage): ?> | Page <?php echo $page; ?><?php endif; ?></div>

        <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
        <?php foreach ($notices as $n): ?><div class="alert alert-success"><?php echo htmlspecialchars($n); ?></div><?php endforeach; ?>

        <?php if (!empty($users)): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Admin Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo $u['user_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="role">
                                        <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                        <select name="new_role" class="form-control" onchange="this.form.submit()">
                                            <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="giver" <?php echo $u['role'] === 'giver' ? 'selected' : ''; ?>>Giver</option>
                                            <option value="seeker" <?php echo $u['role'] === 'seeker' ? 'selected' : ''; ?>>Seeker</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $u['status'] ? 'success' : 'danger'; ?>"><?php echo $u['status'] ? 'Active' : 'Inactive'; ?></span>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline-block; margin-left:6px;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                        <button class="btn btn-outline btn-sm" type="submit"><?php echo $u['status'] ? 'Deactivate' : 'Activate'; ?></button>
                                    </form>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($u['registration_date'])); ?></td>
                                <td>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Delete this user and all associated data?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">🗑️ Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($page, $perPage, count($users), $total); ?>
        <?php else: ?>
            <div class="empty-state">
                <h3>No users found</h3>
            </div>
        <?php endif; ?>
    </div>
    <?php render_footer(); ?>