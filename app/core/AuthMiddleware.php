<?php

require_once __DIR__ . '/Session.php';

class AuthMiddleware
{
    /**
     * Resolves the logged-in user or returns null. Use for endpoints that
     * behave differently for guests (e.g. public post listings).
     *
     * Returns ['id' => int, 'username' => string, 'role' => string].
     */
    public static function user(): ?array
    {
        global $pdo;

        // Read-only: re-opening the session here would re-acquire the file
        // lock the front controller just released and serialize every
        // authenticated request again.
        Session::startReadOnly();

        $userId = Session::userId();
        if ($userId === null) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.banned, COALESCE(ar.role, 'user') AS role
            FROM users u
            LEFT JOIN admin_roles ar ON ar.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Session points at a deleted user — clear it rather than 500.
        if (!$user) {
            Session::logout();
            return null;
        }

        return [
            'id'       => (int)$user['id'],
            'username' => $user['username'],
            'role'     => $user['role'],
            'banned'   => (int)$user['banned'],
        ];
    }

    /**
     * Same return shape as the old JWT version, so the existing call sites
     * across the controllers keep working unchanged.
     */
    public static function requireAuth(): array
    {
        $user = self::user();

        if ($user === null) {
            Response::error('Authentication required', 401);
            exit;
        }

        if (!empty($user['banned'])) {
            Response::error('Account banned', 403);
            exit;
        }

        return $user;
    }
}
