<?php
// Unified Notifications API
// Provides JSON responses for listing and mutating notification read states.
// Actions: list (GET or POST), mark_read (POST), mark_all (POST)
// Security: requires login, optional verification gate (mirroring conversations)
// CSRF required for mutating POST actions (mark_read, mark_all)

require_once __DIR__ . '/../../bootstrap.php';
require_login();
// If your app should restrict features until email verified, uncomment:
// verification_require();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = [];

if ($method === 'POST') {
    // Support both form-encoded and JSON
    $raw = file_get_contents('php://input');
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) { $input = $decoded; }
    } else {
        $input = $_POST; // legacy form submission
    }
}

$action = $input['action'] ?? ($_GET['action'] ?? ($_GET['list'] ?? '') ? 'list' : '');
if ($action === '' && $method === 'GET') { $action = 'list'; }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

function api_fail(int $code, string $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// CSRF enforcement for mutating actions
$mutating = in_array($action, ['mark_read', 'mark_all'], true);
// Accept CSRF token from JSON body, form-encoded POST, or header (handled inside csrf_verify)
$providedToken = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? null);
if ($mutating && !csrf_verify($providedToken)) {
    api_fail(400, 'Invalid CSRF token');
}

// Helper wrappers using existing notification functions (assumed from includes/notifications.php)
// Expected available functions:
// notifications_list($user_id, $limit = 20) -> array of notifications (assoc)
// notifications_mark_read($user_id, array $ids = null) -> void
// We'll provide fallbacks if not defined to avoid fatal errors.

if (!function_exists('notifications_list')) {
    function notifications_list($user_id, $limit = 20) {
        global $conn; $rows = [];
        if (!$conn instanceof mysqli) { return $rows; }
        // Use correct column names: subject (not title), include is_read for client-side badges/styling
        $sql = "SELECT id, type, subject, body, created_at, read_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        if ($st = $conn->prepare($sql)) {
            $st->bind_param('ii', $user_id, $limit);
            if ($st->execute() && ($res = $st->get_result())) { while($r=$res->fetch_assoc()) { $rows[] = $r; } $res->free(); }
            $st->close();
        }
        return $rows;
    }
}
if (!function_exists('notifications_mark_read')) {
    function notifications_mark_read($user_id, $ids = null) {
        global $conn; if (!$conn instanceof mysqli) { return; }
        $now = date('Y-m-d H:i:s');
        if (is_array($ids) && $ids) {
            // Mark specific IDs
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids)+2); // user_id twice + ids
            $params = [];
            $params[] = &$types;
            $params[] = &$user_id; // set read
            $params[] = &$user_id; // ownership check
            foreach ($ids as $i => $val) { $idv = (int)$val; $params[] = &$ids[$i]; }
            $sql = "UPDATE notifications SET is_read=1, read_at = CASE WHEN read_at IS NULL THEN ? ELSE read_at END WHERE user_id = ? AND id IN ($placeholders)";
            // Simpler approach: fallback to multiple executes if binding complexity arises
            if ($st = $conn->prepare($sql)) {
                // Build dynamic bind (first param is timestamp string, not part of earlier calculation)
                // Reset to simpler approach to avoid complexity: execute per id
                $st->close();
                foreach ($ids as $one) {
                    if ($pst = $conn->prepare("UPDATE notifications SET is_read=1, read_at = CASE WHEN read_at IS NULL THEN ? ELSE read_at END WHERE user_id=? AND id=?")) {
                        $one = (int)$one; $pst->bind_param('sii', $now, $user_id, $one); $pst->execute(); $pst->close();
                    }
                }
                return;
            }
        } else {
            if ($st = $conn->prepare("UPDATE notifications SET is_read=1, read_at = CASE WHEN read_at IS NULL THEN ? ELSE read_at END WHERE user_id=?")) {
                $st->bind_param('si', $now, $user_id); $st->execute(); $st->close();
            }
        }
    }
}

switch ($action) {
    case 'list':
        $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
        $data = notifications_list($uid, $limit);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'mark_read':
        $ids = $input['ids'] ?? [];
        if (is_string($ids)) { // allow comma separated
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        }
        if (!is_array($ids)) { api_fail(400, 'Invalid ids payload'); }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) { api_fail(400, 'No ids to mark'); }
        notifications_mark_read($uid, $ids);
        echo json_encode(['success' => true]);
        break;

    case 'mark_all':
        // Use empty array to indicate "mark all" for the shared helper signature
        notifications_mark_read($uid, []);
        echo json_encode(['success' => true]);
        break;

    default:
        api_fail(400, 'Unknown action');
}

exit;
