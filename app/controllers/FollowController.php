<?php

class FollowController {

    /* -------------------------
       FOLLOW USER
    -------------------------- */
    public static function follow($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $targetId = (int)$params['id']; // user being followed

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO followers (user_id, follower_id, created_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$targetId, $user['id']]);

        // Log activity
        ActivityLogger::log($user['id'], 'follow_user', $targetId, 'user');

        Response::success(['success' => true]);
    }

    /* -------------------------
       UNFOLLOW USER
    -------------------------- */
    public static function unfollow($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $targetId = (int)$params['id'];

        $stmt = $pdo->prepare(
            "DELETE FROM followers WHERE user_id = ? AND follower_id = ?"
        );
        $stmt->execute([$targetId, $user['id']]);

        // Log activity
        ActivityLogger::log($user['id'], 'unfollow_user', $targetId, 'user');

        Response::success(['success' => true]);
    }
}
