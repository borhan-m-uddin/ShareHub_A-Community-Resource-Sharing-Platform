<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (isset($_GET['id'])) {
    $service_id = intval($_GET['id']);
    
    // Get service data - ensure user owns the service or is admin
    $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = ? AND (giver_id = ? OR ? = 'admin')");
    $stmt->bind_param("iis", $service_id, $_SESSION['user_id'], $_SESSION['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $service = $result->fetch_assoc();
        echo json_encode(['success' => true, 'service' => $service]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No service ID provided']);
}
?>
