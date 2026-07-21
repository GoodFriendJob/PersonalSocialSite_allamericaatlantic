<?php

class FollowController {

    /* -------------------------
       FOLLOW USER
    -------------------------- */
    public static function follow($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $targetId = (int)$params['id']; // user being followed

        if ($targetId <= 0 || $targetId === $user['id']) {
            Response::error('Invalid network member', 400);
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO followers (follower_id, following_id, created_at)
             SELECT ?, ?, NOW()
             WHERE NOT EXISTS (
                 SELECT 1 FROM followers WHERE follower_id = ? AND following_id = ?
             )"
        );
        $stmt->execute([$user['id'], $targetId, $user['id'], $targetId]);

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
            "DELETE FROM followers WHERE follower_id = ? AND following_id = ?"
        );
        $stmt->execute([$user['id'], $targetId]);

        // Log activity
        ActivityLogger::log($user['id'], 'unfollow_user', $targetId, 'user');

        Response::success(['success' => true]);
    }
}
