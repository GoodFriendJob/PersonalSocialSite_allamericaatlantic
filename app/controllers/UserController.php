<?php

class UserController {

    public static function show($params) {
        global $pdo;

        AuthMiddleware::requireAuth(); // enforce auth

        $id = (int)$params['id'];

        // Fetch user
        $stmt = $pdo->prepare(
            "SELECT id, username, email, created_at
             FROM users
             WHERE id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error("User not found", 404);
            return;
        }

        // Fetch profile
        $stmt = $pdo->prepare(
            "SELECT bio, avatar_url
             FROM profiles
             WHERE user_id = ?"
        );
        $stmt->execute([$id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        $user['profile'] = $profile ?: null;

        Response::success(['user' => $user]);
    }
}
