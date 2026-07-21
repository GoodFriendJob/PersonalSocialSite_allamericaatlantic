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
    // Presence is updated by the open Friends & Network panel. The browser
    // refreshes this endpoint every minute, keeping active members online.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_presence (
            user_id INT NOT NULL,
            last_activity DATETIME NOT NULL,
            PRIMARY KEY (user_id),
            KEY idx_user_presence_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $touch = $pdo->prepare(
        "INSERT INTO user_presence (user_id, last_activity) VALUES (?, NOW())
         ON DUPLICATE KEY UPDATE last_activity = NOW()"
    );
    $touch->execute([$userId]);

    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.first_name, u.last_name,
                COALESCE(NULLIF(u.profile_pic, ''), 'assets/img/default-avatar.svg') AS profile_pic,
                up.last_activity,
                CASE WHEN up.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END AS is_online,
                EXISTS(
                    SELECT 1 FROM friends f
                    WHERE f.status = 'accepted'
                      AND ((f.user_id = ? AND f.friend_id = u.id)
                        OR (f.friend_id = ? AND f.user_id = u.id))
                ) AS is_friend,
                EXISTS(
                    SELECT 1 FROM followers fo
                    WHERE (fo.follower_id = ? AND fo.following_id = u.id)
                       OR (fo.following_id = ? AND fo.follower_id = u.id)
                ) AS is_connection
         FROM users u
         LEFT JOIN user_presence up ON up.user_id = u.id
         WHERE u.id <> ? AND COALESCE(u.banned, 0) = 0
         ORDER BY is_online DESC, up.last_activity DESC, u.username ASC
         LIMIT 200"
    );
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);

    $network = [];
    $friends = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $member) {
        $member['id'] = (int) $member['id'];
        $member['is_online'] = (bool) $member['is_online'];
        $member['is_friend'] = (bool) $member['is_friend'];
        $member['is_connection'] = (bool) $member['is_connection'];
        $name = trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''));
        $member['display_name'] = $name !== '' ? $name : ($member['username'] ?: 'Member');
        unset($member['first_name'], $member['last_name']);

        $network[] = $member;
        if ($member['is_friend']) $friends[] = $member;
    }

    echo json_encode([
        'success' => true,
        'friends' => $friends,
        'network' => $network,
        'online_window_minutes' => 5,
    ]);
} catch (PDOException $e) {
    error_log('Friends and network list failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load friends and network']);
}
