<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/AuthMiddleware.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ActivityLogger.php';

class LikeController {

    public static function like($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $userId = $user['id'];
        $username = $user['username'];
        $postId = (int)$params['id'];

        // Insert like
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO post_likes (post_id, user_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$postId, $userId]);

        // Log activity
        ActivityLogger::log($userId, 'like_post', $postId, 'post');

        // Get post owner
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $postOwner = $stmt->fetchColumn();

        if ($postOwner && $postOwner != $userId) {
            $message = $username . " liked your post";

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, from_user_id, type, message, created_at)
                VALUES (?, ?, 'like', ?, NOW())
            ");
            $stmt->execute([$postOwner, $userId, $message]);
        }

        Response::success(['success' => true]);
    }
}
