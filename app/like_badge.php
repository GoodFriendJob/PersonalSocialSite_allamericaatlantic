<?php
require __DIR__ . "/../app/config/db.php";
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    INSERT INTO achievement_badges (user_id, likes)
    VALUES (?, 1)
    ON DUPLICATE KEY UPDATE likes = likes + 1
");
$stmt->execute([$user_id]);

$stmt = $pdo->prepare("SELECT likes FROM achievement_badges WHERE user_id = ?");
$stmt->execute([$user_id]);
$likes = (int)$stmt->fetchColumn();

echo json_encode(["success" => true, "likes" => $likes]);
