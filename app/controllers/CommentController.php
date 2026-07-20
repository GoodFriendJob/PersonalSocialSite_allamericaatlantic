<?php

class CommentController {

 /* -------------------------
   LIST COMMENTS FOR A POST (NESTED + PAGINATION)
-------------------------- */
public static function index($params) {
    global $pdo;

    $postId = (int)$params['id'];

    // Get pagination info
    list($page, $limit, $offset) = Pagination::getPageLimit();

    // Fetch ALL comments for this post
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.comment,
            c.created_at,
            c.parent_id,
            c.user_id,
            u.username,
            (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id) AS reply_count
        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.post_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$postId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build associative array keyed by comment ID
    $byId = [];
    foreach ($rows as $c) {
        $c['replies'] = [];
        $byId[$c['id']] = $c;
    }

    // Build nested structure
    $topLevel = [];
    foreach ($byId as $id => &$comment) {
        if ($comment['parent_id'] === null) {
            $topLevel[] = &$comment;
        } else {
            if (isset($byId[$comment['parent_id']])) {
                $byId[$comment['parent_id']]['replies'][] = &$comment;
            }
        }
    }

    // Total top-level comments
    $totalTopLevel = count($topLevel);

    // Apply pagination ONLY to top-level comments
    $paginated = array_slice($topLevel, $offset, $limit);

    Response::success([
        'page' => $page,
        'limit' => $limit,
        'total_top_level_comments' => $totalTopLevel,
        'comments' => $paginated
    ]);
}



    /* -------------------------
       ADD COMMENT TO POST
    -------------------------- */
    public static function addComment($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $userId = $user['id'];
        $username = $user['username'];
        $postId = (int)$params['id'];

        $data = json_decode(file_get_contents("php://input"), true);
        $commentText = trim($data['comment'] ?? '');

        if ($commentText === '') {
            Response::error("Comment text required");
        }

        // Insert comment
        $stmt = $pdo->prepare("
            INSERT INTO comments (post_id, user_id, comment, parent_id, created_at)
            VALUES (?, ?, ?, NULL, NOW())
        ");
        $stmt->execute([$postId, $userId, $commentText]);

        $commentId = $pdo->lastInsertId();

        // Log activity
        ActivityLogger::log($userId, 'comment_post', $postId, 'post');

        // Notify post owner
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $postOwner = $stmt->fetchColumn();

        if ($postOwner && $postOwner != $userId) {
            $message = $username . " commented on your post";

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, from_user_id, type, message, created_at)
                VALUES (?, ?, 'comment', ?, NOW())
            ");
            $stmt->execute([$postOwner, $userId, $message]);
        }

        Response::success([
            "success" => true,
            "id" => $commentId,
            "post_id" => $postId,
            "user_id" => $userId,
            "comment" => $commentText
        ]);
    }

    /* -------------------------
       REPLY TO A COMMENT
    -------------------------- */
    public static function replyToComment($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $userId = $user['id'];
        $username = $user['username'];

        $postId = (int)$params['postId'];
        $commentId = (int)$params['commentId'];

        $data = json_decode(file_get_contents("php://input"), true);
        $replyText = trim($data['comment'] ?? '');

        if ($replyText === '') {
            Response::error("Reply text required");
        }

        // Check original comment exists
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $commentOwner = $stmt->fetchColumn();

        if (!$commentOwner) {
            Response::error("Comment not found");
        }

        // Insert reply
        $stmt = $pdo->prepare("
            INSERT INTO comments (post_id, user_id, comment, parent_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$postId, $userId, $replyText, $commentId]);

        $replyId = $pdo->lastInsertId();

        // Log activity
        ActivityLogger::log($userId, 'reply_comment', $commentId, 'comment');

        // Notify comment owner
        if ($commentOwner != $userId) {
            $message = $username . " replied to your comment";

            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, from_user_id, type, message, created_at)
                VALUES (?, ?, 'reply', ?, NOW())
            ");
            $stmt->execute([$commentOwner, $userId, $message]);
        }

        Response::success([
            "success" => true,
            "reply_id" => $replyId,
            "comment" => $replyText
        ]);
    }

    /* -------------------------
       DELETE COMMENT
    -------------------------- */
    public static function delete($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $commentId = (int)$params['id'];

        // Check ownership
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            Response::error("Comment not found", 404);
        }

        if ($row['user_id'] != $user['id'] && $user['role'] !== 'admin') {
            Response::error("Unauthorized", 403);
        }

        // Delete comment
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);

        ActivityLogger::log($user['id'], "delete_comment", $commentId, "comment");

        Response::success(["deleted" => true]);
    }
	
	  /* -------------------------
         EDIT COMMENT OR REPLY
        -------------------------- */
public static function edit($params) {
    global $pdo;

    $user = AuthMiddleware::requireAuth();
    $userId = $user['id'];
    $commentId = (int)$params['id'];

    // Get new text
    $data = json_decode(file_get_contents("php://input"), true);
    $newText = trim($data['comment'] ?? '');

    if ($newText === '') {
        Response::error("Updated comment text required");
    }

    // Check ownership
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        Response::error("Comment not found", 404);
    }

    if ($row['user_id'] != $userId && $user['role'] !== 'admin') {
        Response::error("Unauthorized", 403);
    }

    // Update comment
    $stmt = $pdo->prepare("
        UPDATE comments
        SET comment = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newText, $commentId]);

    ActivityLogger::log($userId, "edit_comment", $commentId, "comment");

    Response::success([
        "success" => true,
        "updated" => true,
        "comment_id" => $commentId,
        "new_text" => $newText
    ]);
  }

}
