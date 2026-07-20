<?php
header("Content-Type: application/json");

// DB CONFIG
$host = "localhost";
$dbname = "allamericaatlantic"; // change if needed
$username = "root";
$password = "";

// CONNECT
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "DB connection failed"]);
    exit;
}

// REQUIRED FIELDS
$title   = $_POST["title"] ?? "";
$content = $_POST["content"] ?? "";
$rating  = $_POST["rating"] ?? null;

// TEMP: Hardcode user + category until login system is added
$user_id     = 1;
$category_id = 1;

if (trim($content) === "" && trim($title) === "") {
    echo json_encode(["status" => "error", "message" => "Post cannot be empty"]);
    exit;
}

// INSERT
$stmt = $pdo->prepare("
    INSERT INTO post (user_id, category_id, title, content, rating, created_at)
    VALUES (:user_id, :category_id, :title, :content, :rating, NOW())
");

$stmt->execute([
    ":user_id"     => $user_id,
    ":category_id" => $category_id,
    ":title"       => $title,
    ":content"     => $content,
    ":rating"      => $rating
]);

echo json_encode(["status" => "success"]);
