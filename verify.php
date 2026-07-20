<?php 
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL); 

// FIX 1: Removed leading slash so it looks inside the current public_html directory
require __DIR__ . "/app/config/db.php"; 

// FIX 2: Ensure $pdo variable is accessible globally from db.php
global $pdo; 

$token = isset($_GET['token']) ? $_GET['token'] : null; 
?> 
<!DOCTYPE html> 
<html> 
<head> 
    <title>Email Verification</title> 
    <style> 
        body { font-family: Arial, sans-serif; background: #f2f2f2; padding: 40px; text-align: center; } 
        .box { background: white; padding: 30px; max-width: 500px; margin: auto; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.15); } 
        h2 { color: #333; } 
        .success { color: green; font-size: 18px; margin-top: 10px; } 
        .error { color: red; font-size: 18px; margin-top: 10px; } 
        a { display: inline-block; margin-top: 20px; text-decoration: none; background: #003366; color: white; padding: 10px 20px; border-radius: 6px; } 
    </style> 
</head> 
<body> 
<div class="box"> 
    <?php 
    if (!$token) { 
        echo "<h2>Invalid Request</h2>"; 
        echo "<p class='error'>No verification token provided.</p>"; 
        echo "</div></body></html>";
        exit; 
    } 

    try {
        // Look up the user matching the token
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = :token"); 
        $stmt->bindParam(':token', $token); 
        $stmt->execute(); 

        if ($stmt->rowCount() === 1) { 
            // Update email verification status and clear out token securely
            $update = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE verification_token = :token"); 
            $update->bindParam(':token', $token); 
            $update->execute(); 

            echo "<h2>Email Verified!</h2>"; 
            echo "<p class='success'>Your email has been successfully verified.</p>"; 
            echo "<a href='login.html'>Go to Login</a>"; 
        } else { 
            echo "<h2>Verification Failed</h2>"; 
            echo "<p class='error'>This verification link is invalid or has already been used.</p>"; 
            echo "<a href='register.html'>Back to Registration</a>";
        } 
    } catch (PDOException $e) {
        // Catch database errors cleanly if connection drops
        echo "<h2>System Error</h2>";
        echo "<p class='error'>A database error occurred. Please try again later.</p>";
        error_log("Verification Crash: " . $e->getMessage());
    }
    ?> 
</div> 
</body> 
</html>
