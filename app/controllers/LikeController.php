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

        Response::success(['liked' => true, 'likes' => self::countLikes($postId)]);
    }

    /**
     * DELETE posts/{id}/like
     *
     * The route for this existed but the method did not, so every unlike
     * failed with "class LikeController does not have a method unlike".
     */
    public static function unlike($params) {
        global $pdo;

        $user   = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];

        $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $user['id']]);

        if ($stmt->rowCount() > 0) {
            ActivityLogger::log($user['id'], 'unlike_post', $postId, 'post');

            // Drop the matching notification so it does not linger after the
            // like that caused it is gone.
            $pdo->prepare("
                DELETE FROM notifications
                 WHERE from_user_id = ? AND type = 'like'
                   AND user_id = (SELECT user_id FROM posts WHERE id = ?)
            ")->execute([$user['id'], $postId]);
        }

        Response::success(['liked' => false, 'likes' => self::countLikes($postId)]);
    }

    private static function countLikes(int $postId): int {
        global $pdo;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
        $stmt->execute([$postId]);

        return (int)$stmt->fetchColumn();
    }
}
