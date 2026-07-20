<?php
require __DIR__ . "/config/db.php";
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$first_name = trim($_POST["first_name"] ?? "");
$last_name  = trim($_POST["last_name"] ?? "");
$username   = ltrim(trim($_POST["username"] ?? ""), "@");
$city       = trim($_POST["city"] ?? "");
$state      = trim($_POST["state"] ?? "");
$sport      = trim($_POST["sport"] ?? "");
$position   = trim($_POST["position"] ?? "");
$bio        = trim($_POST["bio"] ?? "");
$goals      = trim($_POST["goals"] ?? "");
$remove_pic = ($_POST["remove_picture"] ?? "0") === "1";

if ($first_name === "") {
    echo json_encode(["success" => false, "message" => "First name is required."]);
    exit;
}

if ($username === "") {
    echo json_encode(["success" => false, "message" => "Username is required."]);
    exit;
}

if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
    echo json_encode(["success" => false, "message" => "Username must be 3-50 characters using letters, numbers, dot, dash, or underscore."]);
    exit;
}

$dup = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
$dup->execute([$username, $user_id]);
if ($dup->fetch()) {
    echo json_encode(["success" => false, "message" => "That username is already taken."]);
    exit;
}

$currentStmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
$currentStmt->execute([$user_id]);
$currentProfilePic = (string) ($currentStmt->fetchColumn() ?: "");

$newProfilePic = $currentProfilePic;
$removeCurrentPic = false;

if ($remove_pic) {
    $newProfilePic = null;
    $removeCurrentPic = true;
}

if (!empty($_FILES["profile_picture"]) && (int) $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE) {
    if ((int) $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "message" => "Profile picture upload failed."]);
        exit;
    }

    $tmp = $_FILES["profile_picture"]["tmp_name"];
    $size = (int) ($_FILES["profile_picture"]["size"] ?? 0);
    if ($size > 5 * 1024 * 1024) {
        echo json_encode(["success" => false, "message" => "Profile picture must be 5MB or smaller."]);
        exit;
    }

    $mime = mime_content_type($tmp) ?: "";
    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/gif" => "gif",
        "image/webp" => "webp",
    ];
    if (!isset($allowed[$mime])) {
        echo json_encode(["success" => false, "message" => "Only JPG, PNG, GIF, and WEBP images are allowed."]);
        exit;
    }

    $uploadDir = dirname(__DIR__) . "/uploads/profile_pics";
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        echo json_encode(["success" => false, "message" => "Failed to prepare upload folder."]);
        exit;
    }

    $filename = "profile_" . $user_id . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $allowed[$mime];
    $target = $uploadDir . "/" . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        echo json_encode(["success" => false, "message" => "Failed to save profile picture."]);
        exit;
    }

    $newProfilePic = "uploads/profile_pics/" . $filename;
    $removeCurrentPic = true;
}

$stmt = $pdo->prepare("
    UPDATE users
    SET first_name = ?,
        last_name = ?,
        username = ?,
        city = ?,
        state = ?,
        sport = ?,
        position = ?,
        bio = ?,
        goals = ?,
        profile_pic = ?
    WHERE id = ?
");

try {
    $ok = $stmt->execute([
        $first_name,
        $last_name,
        $username,
        $city,
        $state,
        $sport,
        $position,
        $bio,
        $goals,
        $newProfilePic,
        $user_id,
    ]);
} catch (PDOException $e) {
    if ((int) $e->getCode() === 23000) {
        echo json_encode(["success" => false, "message" => "That username is already taken."]);
        exit;
    }
    throw $e;
}

if ($ok && $removeCurrentPic && strpos($currentProfilePic, "uploads/profile_pics/") === 0) {
    $old = dirname(__DIR__) . "/" . $currentProfilePic;
    if (is_file($old)) {
        @unlink($old);
    }
}

echo json_encode([
    "success" => (bool) $ok,
    "message" => $ok ? "Profile updated." : "Update failed.",
]);
