<?php

require_once __DIR__ . '/JWT.php';

class AuthMiddleware
{
    public static function requireAuth()
    {
        global $pdo;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth =
            ($headers['Authorization'] ?? null) ??
            ($headers['authorization'] ?? null) ??
            ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

        $identity = null;

        if ($auth) {
            if (stripos($auth, 'Bearer ') === 0) {
                $auth = substr($auth, 7);
            }
            $decoded = JWT::decode($auth);
            if (!$decoded || !is_array($decoded) || empty($decoded['id'])) {
                Response::error('Invalid token', 401);
                exit;
            }
            $identity = $decoded;
        } elseif (!empty($_SESSION['user_id'])) {
            $identity = ['id' => (int)$_SESSION['user_id']];
        } else {
            Response::error('Authentication required', 401);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int)$identity['id']]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dbUser) {
            Response::error('User not found', 404);
            exit;
        }
        if (!empty($dbUser['banned'])) {
            Response::error('Account banned', 403);
            exit;
        }

        return [
            'id' => (int)$dbUser['id'],
            'username' => $dbUser['username'],
            'role' => $dbUser['role'] ?? ($identity['role'] ?? 'user'),
            'banned' => $dbUser['banned'] ?? 0,
        ];
    }
}
