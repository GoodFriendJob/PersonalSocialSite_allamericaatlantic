<?php

class ThreadController {

    /* -------------------------
       LIST THREADS (PAGINATED)
    -------------------------- */
    public static function index() {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        list($page, $limit, $offset) = Pagination::getPageLimit();

        $stmt = $pdo->prepare(
            "SELECT t.id, t.created_at,
                    u.username AS other_user
             FROM message_threads t
             JOIN users u ON 
                (CASE 
                    WHEN t.user1_id = :id THEN t.user2_id 
                    ELSE t.user1_id 
                 END) = u.id
             WHERE t.user1_id = :id OR t.user2_id = :id
             ORDER BY t.created_at DESC
             LIMIT :limit OFFSET :offset"
        );

        $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::success([
            'page'    => $page,
            'limit'   => $limit,
            'threads' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    /* -------------------------
       CREATE THREAD
    -------------------------- */
    public static function create() {
        global $pdo;

        $user = AuthMiddleware::requireAuth();

        // Safe JSON read
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error("Invalid JSON body", 400);
            return;
        }

        $other_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;

        if ($other_id <= 0) {
            Response::error("Missing user_id", 400);
            return;
        }

        // Check if thread already exists
        $stmt = $pdo->prepare(
            "SELECT id FROM message_threads
             WHERE (user1_id = ? AND user2_id = ?)
                OR (user1_id = ? AND user2_id = ?)
             LIMIT 1"
        );
        $stmt->execute([$user['id'], $other_id, $other_id, $user['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            Response::success(['thread_id' => $existing['id']]);
            return;
        }

        // Create new thread
        $stmt = $pdo->prepare(
            "INSERT INTO message_threads (user1_id, user2_id, created_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$user['id'], $other_id]);

        Response::success(['thread_id' => (int)$pdo->lastInsertId()]);
    }

    /* -------------------------
       SHOW THREAD
    -------------------------- */
    public static function show($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $threadId = (int)$params['id'];

        $stmt = $pdo->prepare(
            "SELECT * FROM message_threads
             WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
        );
        $stmt->execute([$threadId, $user['id'], $user['id']]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$thread) {
            Response::error("Thread not found", 404);
            return;
        }

        Response::success(['thread' => $thread]);
    }
}
