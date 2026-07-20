<?php
session_start();
require 'config.php';

$q = trim($_GET['q'] ?? '');

if ($q === '') {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, username 
    FROM users 
    WHERE first_name LIKE ? 
       OR last_name LIKE ? 
       OR username LIKE ?
    LIMIT 20
");
$stmt->execute(["%$q%", "%$q%", "%$q%"]);

echo json_encode([
    'success' => true,
    'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
