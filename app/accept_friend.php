<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = $_POST['friend_id'] ?? null;

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

echo json_encode(['success' => true]);
