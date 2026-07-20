<?php
require __DIR__ . "/config/db.php";
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not logged in"]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT u.id,
           u.username,
           u.first_name,
           u.last_name,
           u.email,
           u.city,
           u.state,
           u.sport,
           u.position,
           u.bio,
           u.goals,
           u.profile_pic,
           COALESCE(b.likes, 0) AS likes,
           (SELECT AVG(NULLIF(p.rating, 0)) FROM posts p WHERE p.user_id = u.id) AS community_rating,
           (SELECT COUNT(*) FROM highlights h WHERE h.user_id = u.id) AS num_highlights,
           (SELECT COUNT(*) FROM saved_posts s WHERE s.user_id = u.id) AS num_saved_posts
    FROM users u
    LEFT JOIN achievement_badges b ON b.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
}

$user['rating'] = $user['community_rating'] !== null ? (float) $user['community_rating'] : 0.0;
$user['rating'] = round($user['rating'], 1);
unset($user['community_rating']);
$user['num_highlights'] = (int) ($user['num_highlights'] ?? 0);
$user['num_saved_posts'] = (int) ($user['num_saved_posts'] ?? 0);

echo json_encode(["success" => true, "user" => $user]);
