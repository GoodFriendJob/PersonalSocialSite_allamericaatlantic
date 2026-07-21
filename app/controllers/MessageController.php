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

        // Opening the thread reads it: clear the unread flag on anything sent
        // to me here so the message list and any badge stay in sync.
        $pdo->prepare(
            "UPDATE messages SET is_read = 1
             WHERE thread_id = ? AND receiver_id = ? AND is_read = 0"
        )->execute([$threadId, $user['id']]);

        // Fetch messages with pagination
        $stmt = $pdo->prepare(
            "SELECT m.id, m.sender_id, m.receiver_id, m.content, m.is_read, m.created_at,
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

        Response::success([
            'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'me'       => $user['id'],
        ]);
    }

    /* -------------------------
       UNREAD SUMMARY
       Unread messages addressed to me, grouped by who sent them, so the UI
       can badge each conversation and raise a "new message" toast.
    -------------------------- */
    public static function unread($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare(
            "SELECT u.id,
                    u.username,
                    u.first_name,
                    u.last_name,
                    COALESCE(NULLIF(u.profile_pic, ''), 'assets/img/default-avatar.svg') AS profile_pic,
                    grp.unread,
                    grp.last_at,
                    (SELECT m2.content FROM messages m2
                      WHERE m2.sender_id = u.id AND m2.receiver_id = :me1 AND m2.is_read = 0
                      ORDER BY m2.created_at DESC, m2.id DESC LIMIT 1) AS last_content
             FROM (
                    SELECT sender_id, COUNT(*) AS unread, MAX(created_at) AS last_at
                    FROM messages
                    WHERE receiver_id = :me2 AND is_read = 0
                    GROUP BY sender_id
                  ) grp
             JOIN users u ON u.id = grp.sender_id
             ORDER BY grp.last_at DESC"
        );
        $stmt->execute(['me1' => $user['id'], 'me2' => $user['id']]);

        $total = 0;
        $senders = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $unread = (int)$row['unread'];
            $total += $unread;
            $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $senders[] = [
                'id'           => (int)$row['id'],
                'username'     => $row['username'],
                'display_name' => $name !== '' ? $name : ($row['username'] ?: 'Member'),
                'profile_pic'  => $row['profile_pic'],
                'unread'       => $unread,
                'last_content' => $row['last_content'],
                'last_at'      => $row['last_at'],
            ];
        }

        Response::success(['total' => $total, 'senders' => $senders]);
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

        // Keep the thread list sorted by recency and let the receiver know.
        $pdo->prepare("UPDATE message_threads SET last_message_at = NOW() WHERE id = ?")
            ->execute([$threadId]);

        $pdo->prepare(
            "INSERT INTO notifications (user_id, from_user_id, type, message)
             VALUES (?, ?, 'message', ?)"
        )->execute([$receiver, $user['id'], "{$user['username']} sent you a message"]);

        // Log activity
        ActivityLogger::log($user['id'], 'send_message', $threadId, 'thread');

        // Return the stored row so the sender can render it without a refetch.
        $stmt = $pdo->prepare(
            "SELECT id, thread_id, sender_id, receiver_id, content, is_read, created_at
             FROM messages WHERE id = ?"
        );
        $stmt->execute([$messageId]);

        Response::success([
            'message_id' => $messageId,
            'message'    => $stmt->fetch(PDO::FETCH_ASSOC),
        ]);
    }
}
