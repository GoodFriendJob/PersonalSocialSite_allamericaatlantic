<?php

require_once __DIR__ . '/JWT.php';

class AuthMiddleware {

    public static function requireAuth() {
        global $pdo;

        // Read Authorization header
        $headers = getallheaders();

        $auth =
            ($headers['Authorization'] ?? null) ??
            ($headers['authorization'] ?? null) ??
            ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

        if (!$auth) {
            Response::error("Missing token", 401);
            exit;
        }

        // Strip Bearer prefix
        if (stripos($auth, 'Bearer ') === 0) {
            $auth = substr($auth, 7);
        }

        // DO NOT substr again — this was the bug
        $token = $auth;

        // Decode JWT
        $decoded = JWT::decode($token);
        if (!$decoded || !is_array($decoded)) {
            Response::error("Invalid token", 401);
            exit;
        }

        // Fetch user from DB
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$decoded['id']]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dbUser) {
            Response::error("User not found", 404);
            exit;
        }

        // Ban check
        if (!empty($dbUser['banned'])) {
            Response::error("Account banned", 403);
            exit;
        }

        // Return merged user data
        return [
            "id"       => (int)$decoded['id'],
            "username" => $decoded['username'],
            "role"     => $decoded['role'] ?? 'user',
            "banned"   => $dbUser['banned'] ?? 0
        ];
    }
}
