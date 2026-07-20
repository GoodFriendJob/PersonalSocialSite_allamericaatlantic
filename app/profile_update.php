<?php
header("Content-Type: application/json");

// DB CONFIG
$host = "localhost";
$dbname = "allamericaatlantic";
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

// TEMP: Hardcode user_id until login system is added
$user_id = 1;

// READ FIELDS
$full_name   = $_POST["full_name"] ?? "";
$handle      = $_POST["handle"] ?? "";
$city        = $_POST["city"] ?? "";
$state       = $_POST["state"] ?? "";
$bio         = $_POST["bio"] ?? "";
$achievements = $_POST["achievements"] ?? "";

// HANDLE AVATAR UPLOAD
$avatarUrl = null;

if (!empty($_FILES["avatar"]["name"])) {
    $uploadDir = __DIR__ . "/../uploads/avatars/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $filename = "user_" . $user_id . "_" . time() . "_" . basename($_FILES["avatar"]["name"]);
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $targetPath)) {
        $avatarUrl = "/uploads/avatars/" . $filename;
    }
}

// UPDATE QUERY
$stmt = $pdo->prepare("
    UPDATE profiles
    SET full_name = :full_name,
        handle = :handle,
        city = :city,
        state = :state,
        bio = :bio,
        achievements = :achievements,
        avatar = COALESCE(:avatar, avatar)
    WHERE id = :user_id
");

$stmt->execute([
    ":full_name" => $full_name,
    ":handle" => $handle,
    ":city" => $city,
    ":state" => $state,
    ":bio" => $bio,
    ":achievements" => $achievements,
    ":avatar" => $avatarUrl,
    ":user_id" => $user_id
]);

echo json_encode(["status" => "success", "avatar" => $avatarUrl]);
