<?php
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: application/json');
require_login();

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id <= 0) { echo json_encode(['success'=>false,'message'=>'No item ID provided']); exit; }

$stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ? AND (giver_id = ? OR ? = 'admin')");
$stmt->bind_param('iis', $item_id, $_SESSION['user_id'], $_SESSION['role']);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 1) {
    echo json_encode(['success'=>true,'item'=>$res->fetch_assoc()]);
} else {
    echo json_encode(['success'=>false,'message'=>'Item not found']);
}
$stmt->close();
