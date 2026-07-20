<?php
/**
 * Post sharing. Backed by the post_shares table added in migration 001.
 *
 * A share is an event rather than a toggle — the same post may be shared more
 * than once, optionally with a comment — so there is no unique constraint and
 * no "unshare". Removing a specific share is done by its own id.
 */

class ShareController
{
    /* POST posts/{id}/share */
    public static function share($params)
    {
        global $pdo;

        $me     = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];

        $stmt = $pdo->prepare("SELECT user_id, visibility FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            Response::error("Post not found", 404);
            return;
        }

        // A private post is only visible to its owner, so nobody else can share it.
        if ($post['visibility'] === 'private' && (int)$post['user_id'] !== $me['id']) {
            Response::error("This post is private", 403);
            return;
        }

        $input   = json_decode(file_get_contents('php://input'), true);
        $comment = trim($input['comment'] ?? $_POST['comment'] ?? '');
        $comment = $comment === '' ? null : mb_substr($comment, 0, 255);

        $pdo->prepare("INSERT INTO post_shares (post_id, user_id, comment) VALUES (?, ?, ?)")
            ->execute([$postId, $me['id'], $comment]);

        $shareId = (int)$pdo->lastInsertId();

        // Tell the author, unless they shared their own post.
        if ((int)$post['user_id'] !== $me['id']) {
            $pdo->prepare("
                INSERT INTO notifications (user_id, from_user_id, type, message)
                VALUES (?, ?, 'share', ?)
            ")->execute([$post['user_id'], $me['id'], "{$me['username']} shared your post"]);
        }

        ActivityLogger::log($me['id'], 'share_post', $postId, 'post');

        $count = $pdo->prepare("SELECT COUNT(*) FROM post_shares WHERE post_id = ?");
        $count->execute([$postId]);

        Response::success([
            'share_id'    => $shareId,
            'post_id'     => $postId,
            'share_count' => (int)$count->fetchColumn(),
        ]);
    }

    /* GET posts/{id}/shares */
    public static function index($params)
    {
        global $pdo;

        $postId = (int)$params['id'];

        $stmt = $pdo->prepare("
            SELECT s.id, s.comment, s.created_at, u.id AS user_id, u.username, u.profile_pic
              FROM post_shares s
              JOIN users u ON u.id = s.user_id
             WHERE s.post_id = ?
             ORDER BY s.created_at DESC
        ");
        $stmt->execute([$postId]);

        Response::success(['shares' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
