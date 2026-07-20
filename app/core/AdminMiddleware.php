<?php

class AdminMiddleware {

    public static function requireRole(array $roles = ['admin']) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare("SELECT role FROM admin_roles WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $role = $stmt->fetchColumn();

        if (!$role || !in_array($role, $roles, true)) {
            Response::error("Insufficient permissions", 403);
            exit;
        }

        return $user;
    }

    public static function requireAdmin() {
        return self::requireRole(['admin']);
    }

    public static function requireAdminOrModerator() {
        return self::requireRole(['admin', 'moderator']);
    }
}
