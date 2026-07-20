<?php

class MessageController {

    /* -------------------------
       LIST MESSAGES IN THREAD
    -------------------------- */
    public static function index($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $threadId = (int)$params['id'];

        list($page, $limit, $offset) = Pagination::getPageLimit();

        // Verify user belongs to thread
        $stmt = $pdo->prepare(
            "SELECT id FROM message_threads
             WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
        );
        $stmt->execute([$threadId, $user['id'], $user['id']]);

        if (!$stmt->fetch()) {
            Response::error("Unauthorized thread access", 403);
            return;
        }

        // Fetch messages with pagination
        $stmt = $pdo->prepare(
            "SELECT m.id, m.sender_id, m.receiver_id, m.content, m.created_at,
                    u.username AS sender
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC
             LIMIT ? OFFSET ?"
        );

        $stmt->bindValue(1, $threadId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::success(['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /* -------------------------
       SEND MESSAGE
    -------------------------- */
    public static function create($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $threadId = (int)$params['id'];

        // Safe JSON read
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error("Invalid JSON body", 400);
            return;
        }

        $content = trim($data['content'] ?? '');
        if ($content === '') {
            Response::error("Missing content", 400);
            return;
        }

        // Verify thread and get receiver
        $stmt = $pdo->prepare(
            "SELECT user1_id, user2_id FROM message_threads
             WHERE id = ? AND (user1_id = ? OR user2_id = ?)"
        );
        $stmt->execute([$threadId, $user['id'], $user['id']]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$thread) {
            Response::error("Thread not found", 404);
            return;
        }

        $receiver = ($thread['user1_id'] == $user['id'])
            ? $thread['user2_id']
            : $thread['user1_id'];

        // Insert message
        $stmt = $pdo->prepare(
            "INSERT INTO messages (thread_id, sender_id, receiver_id, content, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$threadId, $user['id'], $receiver, $content]);

        $messageId = (int)$pdo->lastInsertId();

        // Log activity
        ActivityLogger::log($user['id'], 'send_message', $threadId, 'thread');

        Response::success(['message_id' => $messageId]);
    }
}
