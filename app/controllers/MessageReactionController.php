<?php

class MessageReactionController {

    /* -------------------------
       ADD OR UPDATE REACTION
    -------------------------- */
    public static function react($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $msgId = (int)$params['id'];

        // Safe JSON read
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error("Invalid JSON body", 400);
            return;
        }

        $reaction = trim($data['reaction'] ?? '');
        if ($reaction === '') {
            Response::error("Missing reaction", 400);
            return;
        }
        // The reaction column is varchar(10); keep a single emoji well inside it.
        if ((function_exists('mb_strlen') ? mb_strlen($reaction) : strlen($reaction)) > 4) {
            Response::error("Reaction is too long", 400);
            return;
        }

        // You can only react to a message in a thread you belong to. Without
        // this any authenticated user could react to any message by id.
        if (!self::canAccessMessage($pdo, $msgId, $user['id'])) {
            Response::error("Message not found", 404);
            return;
        }

        // Insert or update reaction
        $stmt = $pdo->prepare(
            "INSERT INTO message_reactions (message_id, user_id, reaction)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)"
        );
        $stmt->execute([$msgId, $user['id'], $reaction]);

        // Log activity
        ActivityLogger::log($user['id'], 'react_message', $msgId, 'message');

        Response::success(['reaction' => $reaction]);
    }

    /* -------------------------
       REMOVE REACTION
    -------------------------- */
    public static function remove($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $msgId = (int)$params['id'];

        $stmt = $pdo->prepare(
            "DELETE FROM message_reactions
             WHERE message_id = ? AND user_id = ?"
        );
        $stmt->execute([$msgId, $user['id']]);

        // Log activity
        ActivityLogger::log($user['id'], 'remove_reaction', $msgId, 'message');

        Response::success(['removed' => true]);
    }

    /* -------------------------
       GET REACTIONS FOR MESSAGE
    -------------------------- */
    public static function list($params) {
        global $pdo;

        $user  = AuthMiddleware::requireAuth();
        $msgId = (int)$params['id'];

        if (!self::canAccessMessage($pdo, $msgId, $user['id'])) {
            Response::error("Message not found", 404);
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT r.reaction, u.username
             FROM message_reactions r
             JOIN users u ON u.id = r.user_id
             WHERE r.message_id = ?"
        );
        $stmt->execute([$msgId]);

        Response::success(['reactions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /**
     * True when $userId is a participant of the thread the message belongs to.
     * Reactions are only meaningful inside a conversation you are part of.
     */
    private static function canAccessMessage(PDO $pdo, int $msgId, int $userId): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM messages m
             JOIN message_threads t ON t.id = m.thread_id
             WHERE m.id = ? AND (t.user1_id = ? OR t.user2_id = ?)
             LIMIT 1"
        );
        $stmt->execute([$msgId, $userId, $userId]);

        return (bool)$stmt->fetchColumn();
    }
}
