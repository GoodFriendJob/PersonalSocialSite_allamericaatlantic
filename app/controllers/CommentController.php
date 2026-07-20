<?php

class CommentController
{
    public static function index($params)
    {
        global $pdo;
        AuthMiddleware::requireAuth();
        list($page, $limit, $offset) = Pagination::getPageLimit();
        $postId = (int)$params['id'];
        $stmt = $pdo->prepare("SELECT c.id, c.comment, c.created_at, c.parent_id, c.user_id,
                    u.username, u.profile_pic,
                    (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id) AS reply_count
                FROM comments c JOIN users u ON u.id = c.user_id
                WHERE c.post_id = ? ORDER BY c.created_at ASC");
        $stmt->execute([$postId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byId = [];
        foreach ($rows as $comment) {
            $comment['id'] = (int)$comment['id'];
            $comment['user_id'] = (int)$comment['user_id'];
            $comment['parent_id'] = $comment['parent_id'] !== null ? (int)$comment['parent_id'] : null;
            $comment['reply_count'] = (int)$comment['reply_count'];
            $comment['replies'] = [];
            $byId[$comment['id']] = $comment;
        }

        $topLevel = [];
        foreach ($byId as $id => &$comment) {
            if ($comment['parent_id'] === null) {
                $topLevel[] = &$comment;
            } elseif (isset($byId[$comment['parent_id']])) {
                $byId[$comment['parent_id']]['replies'][] = &$comment;
            }
        }
        unset($comment);

        Response::success([
            'page' => $page,
            'limit' => $limit,
            'total_top_level_comments' => count($topLevel),
            'comments' => array_slice($topLevel, $offset, $limit),
        ]);
    }

    public static function addComment($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $comment = trim(is_array($data) ? ($data['comment'] ?? '') : '');
        if ($comment === '') { Response::error('Comment text required', 400); return; }
        $comment = function_exists('mb_substr') ? mb_substr($comment, 0, 1000) : substr($comment, 0, 1000);

        $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Post not found', 404); return; }

        $pdo->prepare('INSERT INTO comments (post_id, user_id, comment, parent_id, created_at)
            VALUES (?, ?, ?, NULL, NOW())')->execute([$postId, $user['id'], $comment]);
        $commentId = (int)$pdo->lastInsertId();
        ActivityLogger::log($user['id'], 'comment_post', $postId, 'post');

        if ((int)$owner !== $user['id']) {
            $message = $user['username'] . ' commented on your post';
            $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, created_at)
                VALUES (?, ?, 'comment', ?, NOW())")->execute([$owner, $user['id'], $message]);
        }
        $stmt = $pdo->prepare('SELECT profile_pic FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        Response::success(['comment' => [
            'id' => $commentId,
            'post_id' => $postId,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'profile_pic' => $stmt->fetchColumn() ?: null,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
            'parent_id' => null,
            'replies' => [],
        ]], 201);
    }

    public static function replyToComment($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['postId'];
        $parentId = (int)$params['commentId'];
        $data = json_decode(file_get_contents('php://input'), true);
        $reply = trim(is_array($data) ? ($data['comment'] ?? '') : '');
        if ($reply === '') { Response::error('Reply text required', 400); return; }

        $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ? AND post_id = ?');
        $stmt->execute([$parentId, $postId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Comment not found', 404); return; }

        $pdo->prepare('INSERT INTO comments (post_id, user_id, comment, parent_id, created_at)
            VALUES (?, ?, ?, ?, NOW())')->execute([$postId, $user['id'], $reply, $parentId]);
        $replyId = (int)$pdo->lastInsertId();
        ActivityLogger::log($user['id'], 'reply_comment', $parentId, 'comment');
        if ((int)$owner !== $user['id']) {
            $message = $user['username'] . ' replied to your comment';
            $pdo->prepare("INSERT INTO notifications (user_id, from_user_id, type, message, created_at)
                VALUES (?, ?, 'reply', ?, NOW())")->execute([$owner, $user['id'], $message]);
        }
        Response::success(['reply_id' => $replyId, 'comment' => $reply], 201);
    }

    public static function delete($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $commentId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Comment not found', 404); return; }
        if ((int)$owner !== $user['id'] && $user['role'] !== 'admin') {
            Response::error('Unauthorized', 403); return;
        }
        $pdo->prepare('DELETE FROM comments WHERE id = ? OR parent_id = ?')->execute([$commentId, $commentId]);
        ActivityLogger::log($user['id'], 'delete_comment', $commentId, 'comment');
        Response::success(['deleted' => true]);
    }

    public static function edit($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $commentId = (int)$params['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $comment = trim(is_array($data) ? ($data['comment'] ?? '') : '');
        if ($comment === '') { Response::error('Updated comment text required', 400); return; }
        $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Comment not found', 404); return; }
        if ((int)$owner !== $user['id'] && $user['role'] !== 'admin') {
            Response::error('Unauthorized', 403); return;
        }
        $pdo->prepare('UPDATE comments SET comment = ?, updated_at = NOW() WHERE id = ?')->execute([$comment, $commentId]);
        ActivityLogger::log($user['id'], 'edit_comment', $commentId, 'comment');
        Response::success(['updated' => true, 'comment_id' => $commentId, 'new_text' => $comment]);
    }
}
