<?php 
session_start();
header("Content-Type: application/json"); 

// 1. Force the server to print errors directly to the browser network log
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. ROOT FILENAME PATHING: Reaches straight forward into your app subfolder
require_once __DIR__ . "/app/config/db.php"; 

// 3. CAPTURE DATA: Converts your incoming JavaScript JSON data back into PHP variables
$raw_data = file_get_contents("php://input");
$input = json_decode($raw_data, true);

$email = $input["email"] ?? ''; 
$password = $input["password"] ?? ''; 

if (empty($email) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Please fill in all fields."]);
    exit;
}

try {
    // 4. RUN DATABASE LOOKUP: Checks for matching email or username profiles
    $stmt = $pdo->prepare("SELECT id, password, email_verified FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email, $email]); 
    $user = $stmt->fetch(PDO::FETCH_ASSOC); 
    
    if (!$user) { 
        echo json_encode(["success" => false, "message" => "Account not found."]); 
        exit; 
    } 
    
    // 5. VERIFY EMAIL STATUS
    if ($user["email_verified"] == 0) { 
        echo json_encode(["success" => false, "message" => "Please verify your email before logging in."]); 
        exit; 
    } 
    
    // 6. VALIDATE ENCRYPTED PASSWORD
    if (!password_verify($password, $user["password"])) { 
        echo json_encode(["success" => false, "message" => "Incorrect password."]); 
        exit; 
    } 
    
    // 7. SUCCESS: Set session identity variables
    $_SESSION["user_id"] = $user["id"]; 
    echo json_encode(["success" => true, "message" => "Login successful"]); 
    exit;

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database link failure: " . $e->getMessage()]);
    exit;
}
?>
