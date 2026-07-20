<?php

class RatingController
{
    private static $tableReady = null;

    public static function ensureTable()
    {
        global $pdo;
        if (self::$tableReady !== null) return self::$tableReady;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS community_ratings (
                target_type VARCHAR(20) NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (target_type, target_id, user_id),
                KEY idx_community_ratings_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            self::$tableReady = true;
        } catch (PDOException $error) {
            error_log('Community rating table setup failed: ' . $error->getMessage());
            self::$tableReady = false;
        }
        return self::$tableReady;
    }

    public static function ratePost($params) { self::rate('post', 'posts', $params); }
    public static function rateStory($params) { self::rate('story', 'stories', $params); }
    public static function rateProfile($params) { self::rate('profile', 'users', $params); }

    private static function rate($targetType, $table, $params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        if (!self::ensureTable()) {
            Response::error('Community ratings are not available yet', 503);
            return;
        }
        $targetId = (int)$params['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $rating = is_array($data) ? (int)($data['rating'] ?? 0) : 0;
        if ($rating < 1 || $rating > 5) {
            Response::error('Choose a rating from 1 to 5 stars', 400);
            return;
        }
        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ?");
        $stmt->execute([$targetId]);
        if (!$stmt->fetchColumn()) {
            Response::error(ucfirst($targetType) . ' not found', 404);
            return;
        }
        $pdo->prepare("INSERT INTO community_ratings
                (target_type, target_id, user_id, rating, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()")
            ->execute([$targetType, $targetId, $user['id'], $rating]);
        ActivityLogger::log($user['id'], 'rate_' . $targetType, $targetId, $targetType);
        Response::success(self::stats($targetType, $targetId, $user['id']));
    }

    public static function stats($targetType, $targetId, $userId = null)
    {
        global $pdo;
        if (!self::ensureTable()) {
            return ['average_rating' => 0.0, 'rating_count' => 0, 'my_rating' => null];
        }
        $stmt = $pdo->prepare('SELECT AVG(rating) AS average_rating, COUNT(*) AS rating_count
            FROM community_ratings WHERE target_type = ? AND target_id = ?');
        $stmt->execute([$targetType, (int)$targetId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $myRating = null;
        if ($userId) {
            $stmt = $pdo->prepare('SELECT rating FROM community_ratings
                WHERE target_type = ? AND target_id = ? AND user_id = ?');
            $stmt->execute([$targetType, (int)$targetId, (int)$userId]);
            $value = $stmt->fetchColumn();
            $myRating = $value !== false ? (int)$value : null;
        }
        return [
            'average_rating' => round((float)($stats['average_rating'] ?? 0), 1),
            'rating_count' => (int)($stats['rating_count'] ?? 0),
            'my_rating' => $myRating,
        ];
    }
}
