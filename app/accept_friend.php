<?php
session_start();
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$user_id = (int) $_SESSION['user_id'];
$friend_id = (int) ($data['friend_id'] ?? 0);

if (!$friend_id) {
    echo json_encode(['success' => false, 'message' => 'Missing friend_id']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE friends 
    SET status = 'accepted' 
    WHERE user_id = ? AND friend_id = ?
");
$stmt->execute([$friend_id, $user_id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pending request not found']);
    exit;
}

echo json_encode(['success' => true, 'status' => 'accepted']);
