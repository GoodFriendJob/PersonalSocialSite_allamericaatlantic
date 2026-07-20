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

        // Verify message exists
        $stmt = $pdo->prepare("SELECT id FROM messages WHERE id = ?");
        $stmt->execute([$msgId]);

        if (!$stmt->fetch()) {
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

        AuthMiddleware::requireAuth();
        $msgId = (int)$params['id'];

        $stmt = $pdo->prepare(
            "SELECT r.reaction, u.username
             FROM message_reactions r
             JOIN users u ON u.id = r.user_id
             WHERE r.message_id = ?"
        );
        $stmt->execute([$msgId]);

        Response::success(['reactions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
