<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id, u.username,
                COALESCE(NULLIF(u.profile_pic, ''), 'assets/img/default-avatar.svg') AS profile_pic
         FROM friends f
         JOIN users u ON (
             (f.user_id = ? AND u.id = f.friend_id)
             OR (f.friend_id = ? AND u.id = f.user_id)
         )
         WHERE f.status = 'accepted' AND u.id <> ?
         ORDER BY u.username ASC"
    );
    $stmt->execute([$userId, $userId, $userId]);

    $friends = array_map(static function (array $friend): array {
        $friend['id'] = (int) $friend['id'];
        // The current database has no last_activity column or heartbeat yet.
        $friend['is_online'] = false;
        return $friend;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'friends' => $friends]);
} catch (PDOException $e) {
    error_log('Friend list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load friends']);
}
