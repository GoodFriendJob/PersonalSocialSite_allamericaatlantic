<?php
header("Content-Type: application/json");

$host = "localhost";
$dbname = "allamericaatlantic";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->query("
    SELECT id, user_id, category_id, title, content, rating, created_at
    FROM post
    ORDER BY created_at DESC
    LIMIT 50
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
