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
            $mail->Host       = config('mail.host');
            $mail->SMTPAuth   = true;
            $mail->Username   = config('mail.username');
            $mail->Password   = config('mail.password');

            // 2. Port & Encryption Matrix (Try Port 587 first)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = config('mail.port', 587);

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
            $mail->setFrom(config('mail.from_addr'), config('mail.from_name'));
            $mail->addAddress($email);                                  

            // 5. Message Content & Verification Link
            $mail->isHTML(true);                                        
            $mail->Subject = 'Verify Your Account';
            
            // Fixed Link: Points correctly to your verify.php handler script
            $verificationLink = rtrim(config('app.url'), '/') . '/verify.php?token=' . urlencode($token);

            
            $mail->Body = "<h1>Welcome!</h1><p>Please click the link below to verify your account:</p><a href='{$verificationLink}'>Verify Email</a>";
            $mail->send();

        } catch (Exception $e) {
            // The account exists at this point, so a mail failure must not be
            // reported as a failed registration — that used to leave people
            // with a working account and a 500 telling them it broke.
            error_log("PHPMailer System Error: " . $mail->ErrorInfo);

            Response::success([
                "id"           => $userId,
                "mail_sent"    => false,
                "message"      => "Account created, but the verification email could not be sent. Contact support to activate your account.",
            ]);
            return;
        }

        Response::success([
            "id"        => $userId,
            "mail_sent" => true,
            "message"   => "Account created. Check your email for the verification link.",
        ]);
    }

    /* ------------------------- LOGIN USER -------------------------- */
    public static function login($params) {
        global $pdo;

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $identifier = trim($input['email'] ?? '');
        $password   = (string)($input['password'] ?? '');

        if ($identifier === '' || $password === '') {
            Response::error("Email and password are required", 400);
            return;
        }

        // The login form accepts either an email or a username.
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Same message for "no such account" and "wrong password" so the
        // response can't be used to enumerate which emails are registered.
        if (!$user || !password_verify($password, $user['password'])) {
            Response::error("Incorrect email or password", 401);
            return;
        }

        if (!empty($user['banned'])) {
            Response::error("Your account has been banned", 403);
            return;
        }

        if (config('app.require_email_verification', true) && empty($user['email_verified'])) {
            Response::json([
                'error'      => 'Please verify your email before logging in.',
                'unverified' => true,
            ], 403);
            return;
        }

        Session::login((int)$user['id']);

        Response::success(["user" => self::publicUser($user)]);
    }

    /* ------------------------- LOGOUT -------------------------- */
    public static function logout($params) {
        Session::logout();
        Response::success(["message" => "Logged out"]);
    }

    /* ------------------------- CURRENT USER -------------------------- */
    public static function me($params) {
        global $pdo;

        $auth = AuthMiddleware::user();
        if ($auth === null) {
            Response::error("Not authenticated", 401);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$auth['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success([
            "user" => self::publicUser($user) + ['role' => $auth['role']],
        ]);
    }

    /** Strips password hashes and verification tokens before sending a user out. */
    private static function publicUser(array $user): array {
        return [
            'id'          => (int)$user['id'],
            'username'    => $user['username'],
            'first_name'  => $user['first_name'],
            'last_name'   => $user['last_name'],
            'email'       => $user['email'],
            'city'        => $user['city'],
            'state'       => $user['state'],
            'bio'         => $user['bio'],
            'profile_pic' => $user['profile_pic'],
            'sport'       => $user['sport'],
            'position'    => $user['position'],
        ];
    }
}
