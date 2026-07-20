<?php

class NotificationController {

    public static function index($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();

        list($page, $limit, $offset) = Pagination::getPageLimit();

        $stmt = $pdo->prepare(
            "SELECT id, type, message, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );

        $stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::success(['notifications' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
	
	
	// as read 
	public static function markRead($params) {
    global $pdo;

    $user = AuthMiddleware::requireAuth();
    $userId = $user['id'];
    $notificationId = (int)$params['id'];

    // Ensure notification belongs to user
    $stmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);

    if (!$stmt->fetch()) {
        Response::error("Notification not found", 404);
    }

    // Mark as read
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);

    Response::success([
        "success" => true,
        "notification_id" => $notificationId,
        "is_read" => 1
    ]);
  }
  
  // mark all s read
  public static function markAllRead() {
    global $pdo;

    $user = AuthMiddleware::requireAuth();
    $userId = $user['id'];

    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);

    Response::success([
        "success" => true,
        "all_marked_read" => true
    ]);
  }


}
