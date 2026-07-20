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
$query = trim((string) ($_GET['q'] ?? ''));

$queryLength = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
if ($queryLength < 2) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

$like = '%' . $query . '%';
$stmt = $pdo->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.username,
            COALESCE(NULLIF(u.profile_pic, ''), 'assets/img/default-avatar.svg') AS profile_pic,
            (SELECT f.status FROM friends f
             WHERE (f.user_id = ? AND f.friend_id = u.id)
                OR (f.friend_id = ? AND f.user_id = u.id)
             LIMIT 1) AS friendship_status
     FROM users u
     WHERE u.id <> ?
       AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)
     ORDER BY u.username ASC
     LIMIT 20"
);
$stmt->execute([$userId, $userId, $userId, $like, $like, $like]);

echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
