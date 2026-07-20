<?php
session_start();
require "db.php";

$email = $_POST["email"];
$password = $_POST["password"];

$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user["password_hash"])) {
    die("Invalid login");
}

$_SESSION["user_id"] = $user["id"];

header("Location: app.html");
