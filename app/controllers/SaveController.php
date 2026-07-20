<?php
/**
 * Saved posts (bookmarks). The saved_posts table already existed but had no
 * endpoint, so the Save button had nothing to call.
 *
 * Migration 001 added a unique key on (user_id, post_id), so save is
 * idempotent — saving twice is a no-op rather than a duplicate row.
 */

class SaveController
{
    /* POST posts/{id}/save */
    public static function save($params)
    {
        global $pdo;

        $me     = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];

        if (!self::postExists($postId)) {
            Response::error("Post not found", 404);
            return;
        }

        // INSERT IGNORE relies on the unique key from migration 001.
        $pdo->prepare("INSERT IGNORE INTO saved_posts (user_id, post_id) VALUES (?, ?)")
            ->execute([$me['id'], $postId]);

        ActivityLogger::log($me['id'], 'save_post', $postId, 'post');

        Response::success(['saved' => true, 'post_id' => $postId]);
    }

    /* DELETE posts/{id}/save */
    public static function unsave($params)
    {
        global $pdo;

        $me     = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];

        $pdo->prepare("DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?")
            ->execute([$me['id'], $postId]);

        ActivityLogger::log($me['id'], 'unsave_post', $postId, 'post');

        Response::success(['saved' => false, 'post_id' => $postId]);
    }

    /* GET me/saved */
    public static function index($params)
    {
        global $pdo;

        $me = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare("
            SELECT p.id, p.content, p.created_at, p.visibility,
                   u.username, u.profile_pic,
                   sp.created_at AS saved_at
              FROM saved_posts sp
              JOIN posts p ON p.id = sp.post_id
              JOIN users u ON u.id = p.user_id
             WHERE sp.user_id = ?
               AND (p.visibility = 'public' OR p.user_id = ?)
             ORDER BY sp.created_at DESC
        ");
        $stmt->execute([$me['id'], $me['id']]);

        Response::success(['posts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private static function postExists(int $postId): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT 1 FROM posts WHERE id = ?");
        $stmt->execute([$postId]);

        return (bool)$stmt->fetchColumn();
    }
}
