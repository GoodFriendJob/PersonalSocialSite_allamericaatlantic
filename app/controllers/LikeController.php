<?php

class LikeController
{
    public static function like($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $postOwner = $stmt->fetchColumn();
        if (!$postOwner) { Response::error('Post not found', 404); return; }

        $stmt = $pdo->prepare('INSERT IGNORE INTO post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$postId, $user['id']]);
        if ($stmt->rowCount() > 0) {
            ActivityLogger::log($user['id'], 'like_post', $postId, 'post');
            if ((int)$postOwner !== $user['id']) {
                $message = $user['username'] . ' liked your post';
                $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, created_at)
                    VALUES (?, ?, 'like', ?, NOW())")->execute([$postOwner, $user['id'], $message]);
            }
        }
        Response::success(['liked' => true, 'like_count' => self::countLikes($postId)]);
    }

    public static function unlike($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];
        $pdo->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, $user['id']]);
        Response::success(['liked' => false, 'like_count' => self::countLikes($postId)]);
    }

    private static function countLikes($postId)
    {
        global $pdo;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
        $stmt->execute([$postId]);
        return (int)$stmt->fetchColumn();
    }
}
