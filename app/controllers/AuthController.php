<?php
// 1. Load the PHPMailer files at the very top of the controller
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController {

    /* ------------------------- REGISTER NEW USER -------------------------- */
    public static function register($params) {
        global $pdo;

        // Read JSON body safely
        $data = $params;
        if (!is_array($data)) {
            Response::error("Invalid JSON body", 400);
            return;
        }

        // Collect fields
        $first_name = trim($data['first_name'] ?? '');
        $last_name  = trim($data['last_name'] ?? '');
        $username   = trim($data['username'] ?? '');
        $email      = trim($data['email'] ?? '');
        $password   = trim($data['password'] ?? '');
        $confirm    = trim($data['confirm_password'] ?? ''); // JavaScript uses confirm_password
        $city       = trim($data['city'] ?? '');
        $state      = trim($data['state'] ?? '');

        // Validate required fields
        if (!$first_name || !$last_name || !$username || !$email || !$password || !$confirm || !$city || !$state) {
            Response::error("All fields are required", 400);
            return;
        }

        if ($password !== $confirm) {
            Response::error("Passwords do not match", 400);
            return;
        }

        // Check username
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            Response::error("Username already taken", 400);
            return;
        }

        // Check email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::error("Email already registered", 400);
            return;
        }

        // Hash password
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Generate verification token
        $token = bin2hex(random_bytes(32));

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, username, email, password, city, state, verification_token, email_verified, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([ $first_name, $last_name, $username, $email, $hash, $city, $state, $token ]);
        $userId = (int)$pdo->lastInsertId();

        // Create empty profile
        $stmt = $pdo->prepare("
            INSERT INTO profiles (user_id, bio, avatar_url, created_at)
            VALUES (?, '', '', NOW())
        ");
        $stmt->execute([$userId]);

        /* ------------------------------------------------------------------
         * PHPMailer Configuration Setup (Inside Function)
         * ------------------------------------------------------------------ */
        $mail = new PHPMailer(true);
        try {
            // 1. Core Server Settings
            $mail->SMTPDebug  = 0;                                      // Must stay 0 to prevent JSON/CORB errors
            $mail->isSMTP();                                            
            $mail->Host       = 'netsol-smtp-oxcs.hostingplatform.com'; 
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'admin@allamericaatlantic.com';         
            $mail->Password   = 'DianaCharles8626$';            // Replace with your actual password
            
            // 2. Port & Encryption Matrix (Try Port 587 first)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = 587;                                    

            // 3. Shared Server Timeout Configuration
            $mail->Timeout    = 25;                                     
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,                       
                    'verify_peer_name'  => false,                       
                    'allow_self_signed' => true
                )
            );

            // 4. Header Mapping
            $mail->setFrom('admin@allamericaatlantic.com', 'All America Atlantic');
            $mail->addAddress($email);                                  

            // 5. Message Content & Verification Link
            $mail->isHTML(true);                                        
            $mail->Subject = 'Verify Your Account';
            
            // Fixed Link: Points correctly to your verify.php handler script
           $verificationLink = "https://allamericaatlantic.com/verify.php?token=" . $token;

            
            $mail->Body = "<h1>Welcome!</h1><p>Please click the link below to verify your account:</p><a href='{$verificationLink}'>Verify Email</a>";
            $mail->send();

        } catch (Exception $e) {
            // Log details silently if SMTP crashes
            error_log("PHPMailer System Error: " . $mail->ErrorInfo);
            Response::error("Account registered, but verification mail dispatch failed.", 500);
            return;
        }

        // 6. SUCCESS RESPONSE (Fires ONLY after email sends or error handles)
        Response::success([
            "success" => true,
            "id" => $userId
        ]);
    }

    /* ------------------------- LOGIN USER -------------------------- */
    public static function login($params) {
        global $pdo;

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $email    = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::error("Email and password are required", 400);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            Response::error("Invalid credentials", 400);
            return;
        }

        if (!empty($user['banned'])) {
            Response::error("Your account has been banned", 403);
            return;
        }

        Response::success([
            "success" => true,
            "id" => $user['id']
        ]);
    }
}
