<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$userId = (int) $_SESSION['user_id'];
$receiverId = (int) ($data['receiver_id'] ?? $data['friend_id'] ?? 0);

if ($receiverId <= 0 || $receiverId === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid member']);
    exit;
}

$member = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$member->execute([$receiverId]);
if (!$member->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Member not found']);
    exit;
}

$existing = $pdo->prepare(
    "SELECT id, status FROM friends
     WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
     LIMIT 1"
);
$existing->execute([$userId, $receiverId, $receiverId, $userId]);
$relationship = $existing->fetch(PDO::FETCH_ASSOC);

if ($relationship) {
    echo json_encode([
        'success' => true,
        'status' => $relationship['status'],
        'message' => $relationship['status'] === 'accepted' ? 'Already friends' : 'Request already pending',
    ]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
$stmt->execute([$userId, $receiverId]);

echo json_encode(['success' => true, 'status' => 'pending']);
