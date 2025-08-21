<?php
session_start();
require_once "config.php";

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (isset($_GET['id'])) {
    $item_id = intval($_GET['id']);
    
    // Get item data - ensure user owns the item or is admin
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ? AND (giver_id = ? OR ? = 'admin')");
    $stmt->bind_param("iis", $item_id, $_SESSION['user_id'], $_SESSION['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $item = $result->fetch_assoc();
        echo json_encode(['success' => true, 'item' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No item ID provided']);
}
?>
