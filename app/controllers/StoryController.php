<?php

class StoryController
{
    private static $socialTablesReady = null;

    private static function ensureSocialTables()
    {
        global $pdo;

        if (self::$socialTablesReady !== null) {
            return self::$socialTablesReady;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS story_likes (
                story_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (story_id, user_id),
                KEY idx_story_likes_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS story_comments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                story_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                comment VARCHAR(1000) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_story_comments_story (story_id, created_at),
                KEY idx_story_comments_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            self::$socialTablesReady = true;
        } catch (PDOException $e) {
            error_log('Story social table setup failed: ' . $e->getMessage());
            self::$socialTablesReady = false;
        }

        return self::$socialTablesReady;
    }

    private static function requireSocialTables()
    {
        if (!self::ensureSocialTables()) {
            Response::error('Story likes and comments are not available yet', 503);
            return false;
        }
        return true;
    }

    private static function publicMediaUrl($url)
    {
        if (!$url) return null;
        return strpos($url, '/uploads/stories/') === 0 ? '/app/public' . $url : $url;
    }

    private static function mediaFilePath($url)
    {
        if (!$url) return null;
        if (strpos($url, '/app/public/') === 0) {
            return __DIR__ . '/../public/' . substr($url, strlen('/app/public/'));
        }
        if (strpos($url, '/uploads/') === 0) {
            return __DIR__ . '/../public' . $url;
        }
        return null;
    }

    private static function createThumbnail($sourcePath, $destPath, $maxWidth = 600)
    {
        if (!function_exists('imagecreatetruecolor')) return false;
        $info = @getimagesize($sourcePath);
        if (!$info || empty($info[0]) || empty($info[1])) return false;

        list($width, $height) = $info;
        $newWidth = min((int)$maxWidth, (int)$width);
        $newHeight = max(1, (int)round($height * ($newWidth / $width)));

        switch ($info['mime']) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
            case 'image/png': $src = @imagecreatefrompng($sourcePath); break;
            case 'image/gif': $src = @imagecreatefromgif($sourcePath); break;
            default: return false;
        }
        if (!$src) return false;

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $saved = imagejpeg($thumb, $destPath, 82);
        imagedestroy($src);
        imagedestroy($thumb);
        return $saved;
    }

    private static function videoDuration($path)
    {
        if (!function_exists('exec')) return null;
        try {
            $output = [];
            $status = 1;
            $command = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path);
            exec($command, $output, $status);
            if ($status === 0 && isset($output[0]) && is_numeric(trim($output[0]))) {
                return (float)trim($output[0]);
            }
        } catch (Throwable $e) {
            error_log('Story video duration check unavailable: ' . $e->getMessage());
        }
        return null;
    }

    private static function activeStory($storyId)
    {
        global $pdo;
        $stmt = $pdo->prepare('SELECT id, user_id FROM stories WHERE id = ? AND expires_at > NOW()');
        $stmt->execute([(int)$storyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function create($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();

        if (empty($_FILES['media']) || (int)$_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Choose a photo or video to upload', 400);
            return;
        }

        $file = $_FILES['media'];
        if ((int)$file['size'] <= 0 || (int)$file['size'] > 25 * 1024 * 1024) {
            Response::error('Media must be smaller than 25 MB', 400);
            return;
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
        } else {
            $mime = mime_content_type($file['tmp_name']);
        }
        $imageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        $videoTypes = [
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
            'video/3gpp' => '3gp', 'video/3gpp2' => '3g2', 'video/x-m4v' => 'm4v',
        ];
        $isImage = isset($imageTypes[$mime]);
        $isVideo = isset($videoTypes[$mime]);
        if (!$isImage && !$isVideo) {
            Response::error('That photo or video format is not supported', 400);
            return;
        }

        $caption = trim($_POST['caption'] ?? '');
        $caption = function_exists('mb_substr') ? mb_substr($caption, 0, 500) : substr($caption, 0, 500);
        $uploadDir = __DIR__ . '/../public/uploads/stories';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            Response::error('Could not prepare the story upload folder', 500);
            return;
        }

        $extension = $isImage ? $imageTypes[$mime] : $videoTypes[$mime];
        $filename = 'story_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            Response::error('Failed to save the uploaded story', 500);
            return;
        }

        if ($isVideo) {
            $reported = isset($_POST['duration']) && is_numeric($_POST['duration']) ? (float)$_POST['duration'] : null;
            $verified = self::videoDuration($path);
            $duration = $verified !== null ? $verified : $reported;
            if ($duration !== null && $duration > 15.25) {
                @unlink($path);
                Response::error('Story videos must be 15 seconds or shorter', 400);
                return;
            }
        }

        $mediaUrl = '/app/public/uploads/stories/' . $filename;
        $thumbnailUrl = null;
        $thumbPath = null;

        if ($isImage) {
            $thumbName = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
            $thumbPath = $uploadDir . '/' . $thumbName;
            if (self::createThumbnail($path, $thumbPath)) {
                $thumbnailUrl = '/app/public/uploads/stories/' . $thumbName;
            }
        } elseif (!empty($_POST['thumbnail']) && preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)$/', $_POST['thumbnail'], $matches)) {
            $thumbnailData = base64_decode($matches[1], true);
            if ($thumbnailData !== false && strlen($thumbnailData) <= 2 * 1024 * 1024) {
                $thumbName = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
                $thumbPath = $uploadDir . '/' . $thumbName;
                if (file_put_contents($thumbPath, $thumbnailData) !== false) {
                    $thumbnailUrl = '/app/public/uploads/stories/' . $thumbName;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO stories
                (user_id, media_url, media_type, thumbnail_url, caption, created_at, expires_at)
                VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$user['id'], $mediaUrl, $isImage ? 'image' : 'video', $thumbnailUrl, $caption ?: null]);
            $storyId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            @unlink($path);
            if ($thumbPath) @unlink($thumbPath);
            error_log('Story insert failed: ' . $e->getMessage());
            Response::error('Could not save the story record', 500);
            return;
        }

        ActivityLogger::log($user['id'], 'create_story', $storyId, 'story');
        Response::success([
            'id' => $storyId, 'media_url' => $mediaUrl, 'thumbnail_url' => $thumbnailUrl,
            'media_type' => $isImage ? 'image' : 'video', 'caption' => $caption,
        ], 201);
    }

    public static function feed($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $socialReady = self::ensureSocialTables();
        $socialSelect = $socialReady
            ? ", (SELECT COUNT(*) FROM story_likes sl WHERE sl.story_id = s.id) AS like_count,
                 (SELECT COUNT(*) FROM story_comments sc WHERE sc.story_id = s.id) AS comment_count,
                 EXISTS(SELECT 1 FROM story_likes mine WHERE mine.story_id = s.id AND mine.user_id = ?) AS liked_by_me"
            : ', 0 AS like_count, 0 AS comment_count, 0 AS liked_by_me';
        $ratingReady = RatingController::ensureTable();
        $ratingSelect = $ratingReady
            ? ", (SELECT AVG(cr.rating) FROM community_ratings cr WHERE cr.target_type = 'story' AND cr.target_id = s.id) AS average_rating,
                 (SELECT COUNT(*) FROM community_ratings crc WHERE crc.target_type = 'story' AND crc.target_id = s.id) AS rating_count,
                 (SELECT crm.rating FROM community_ratings crm WHERE crm.target_type = 'story' AND crm.target_id = s.id AND crm.user_id = ?) AS my_rating"
            : ', 0 AS average_rating, 0 AS rating_count, NULL AS my_rating';

        $stmt = $pdo->prepare("SELECT s.id, s.user_id, u.username, u.profile_pic,
                    s.media_url, s.media_type, s.thumbnail_url, s.caption, s.created_at, s.expires_at
                    {$socialSelect}
                    {$ratingSelect}
                FROM stories s JOIN users u ON u.id = s.user_id
                WHERE s.expires_at > NOW()
                ORDER BY CASE
                    WHEN s.user_id = ? THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM friends f
                        WHERE f.status = 'accepted'
                          AND ((f.user_id = ? AND f.friend_id = s.user_id)
                            OR (f.friend_id = ? AND f.user_id = s.user_id))
                    ) THEN 1
                    ELSE 2
                END, s.created_at DESC");
        $params = [];
        if ($socialReady) $params[] = $user['id'];
        if ($ratingReady) $params[] = $user['id'];
        $params[] = $user['id'];
        $params[] = $user['id'];
        $params[] = $user['id'];
        $stmt->execute($params);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $seenStmt = $pdo->prepare('SELECT COUNT(*) FROM story_views WHERE story_id = ? AND viewer_id = ?');
        $grouped = [];

        foreach ($stories as $story) {
            $seenStmt->execute([$story['id'], $user['id']]);
            $story['seen'] = (bool)$seenStmt->fetchColumn();
            $story['liked_by_me'] = (bool)$story['liked_by_me'];
            $story['like_count'] = (int)$story['like_count'];
            $story['comment_count'] = (int)$story['comment_count'];
            $story['average_rating'] = round((float)($story['average_rating'] ?? 0), 1);
            $story['rating_count'] = (int)($story['rating_count'] ?? 0);
            $story['my_rating'] = $story['my_rating'] !== null ? (int)$story['my_rating'] : null;
            $story['media_url'] = self::publicMediaUrl($story['media_url']);
            $story['thumbnail_url'] = self::publicMediaUrl($story['thumbnail_url']);
            $uid = (int)$story['user_id'];
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user_id' => $uid, 'username' => $story['username'],
                    'profile_pic' => $story['profile_pic'], 'stories' => [],
                ];
            }
            $grouped[$uid]['stories'][] = $story;
        }
        Response::success(['stories' => array_values($grouped), 'social_ready' => $socialReady]);
    }

    public static function myStories($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $stmt = $pdo->prepare('SELECT id, media_url, media_type, thumbnail_url, caption, created_at, expires_at
            FROM stories WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC');
        $stmt->execute([$user['id']]);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($stories as &$story) {
            $story['media_url'] = self::publicMediaUrl($story['media_url']);
            $story['thumbnail_url'] = self::publicMediaUrl($story['thumbnail_url']);
        }
        unset($story);
        Response::success(['stories' => $stories]);
    }

    public static function like($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        if (!self::requireSocialTables()) return;
        $storyId = (int)$params['id'];
        if (!self::activeStory($storyId)) {
            Response::error('Story not found or expired', 404); return;
        }
        $pdo->prepare('INSERT IGNORE INTO story_likes (story_id, user_id, created_at) VALUES (?, ?, NOW())')
            ->execute([$storyId, $user['id']]);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM story_likes WHERE story_id = ?');
        $stmt->execute([$storyId]);
        Response::success(['liked' => true, 'like_count' => (int)$stmt->fetchColumn()]);
    }

    public static function unlike($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        if (!self::requireSocialTables()) return;
        $storyId = (int)$params['id'];
        $pdo->prepare('DELETE FROM story_likes WHERE story_id = ? AND user_id = ?')->execute([$storyId, $user['id']]);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM story_likes WHERE story_id = ?');
        $stmt->execute([$storyId]);
        Response::success(['liked' => false, 'like_count' => (int)$stmt->fetchColumn()]);
    }

    public static function comments($params)
    {
        global $pdo;
        AuthMiddleware::requireAuth();
        if (!self::requireSocialTables()) return;
        $storyId = (int)$params['id'];
        if (!self::activeStory($storyId)) {
            Response::error('Story not found or expired', 404); return;
        }
        $stmt = $pdo->prepare('SELECT sc.id, sc.user_id, sc.comment, sc.created_at, u.username, u.profile_pic
            FROM story_comments sc JOIN users u ON u.id = sc.user_id
            WHERE sc.story_id = ? ORDER BY sc.created_at ASC');
        $stmt->execute([$storyId]);
        Response::success(['comments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function addComment($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        if (!self::requireSocialTables()) return;
        $storyId = (int)$params['id'];
        if (!self::activeStory($storyId)) {
            Response::error('Story not found or expired', 404); return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $comment = trim(is_array($data) ? ($data['comment'] ?? '') : ($_POST['comment'] ?? ''));
        if ($comment === '') {
            Response::error('Comment text is required', 400); return;
        }
        $comment = function_exists('mb_substr') ? mb_substr($comment, 0, 1000) : substr($comment, 0, 1000);
        $stmt = $pdo->prepare('INSERT INTO story_comments (story_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$storyId, $user['id'], $comment]);
        Response::success(['comment' => [
            'id' => (int)$pdo->lastInsertId(), 'user_id' => $user['id'],
            'username' => $user['username'], 'profile_pic' => null,
            'comment' => $comment, 'created_at' => date('Y-m-d H:i:s'),
        ]], 201);
    }

    public static function view($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $storyId = (int)$params['id'];
        if (!self::activeStory($storyId)) {
            Response::error('Story not found or expired', 404); return;
        }
        $pdo->prepare('INSERT IGNORE INTO story_views (story_id, viewer_id, viewed_at) VALUES (?, ?, NOW())')
            ->execute([$storyId, $user['id']]);
        Response::success(['viewed' => true]);
    }

    public static function viewers($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $storyId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT user_id FROM stories WHERE id = ?');
        $stmt->execute([$storyId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Story not found', 404); return; }
        if ((int)$owner !== $user['id'] && $user['role'] !== 'admin') {
            Response::error('Unauthorized', 403); return;
        }
        $stmt = $pdo->prepare('SELECT sv.viewer_id, u.username, sv.viewed_at
            FROM story_views sv JOIN users u ON u.id = sv.viewer_id
            WHERE sv.story_id = ? ORDER BY sv.viewed_at DESC');
        $stmt->execute([$storyId]);
        Response::success(['viewers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function delete($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $storyId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT user_id, media_url, thumbnail_url FROM stories WHERE id = ?');
        $stmt->execute([$storyId]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$story) { Response::error('Story not found', 404); return; }
        if ((int)$story['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            Response::error('Unauthorized', 403); return;
        }

        $pdo->beginTransaction();
        try {
            if (self::ensureSocialTables()) {
                $pdo->prepare('DELETE FROM story_comments WHERE story_id = ?')->execute([$storyId]);
                $pdo->prepare('DELETE FROM story_likes WHERE story_id = ?')->execute([$storyId]);
            }
            $pdo->prepare('DELETE FROM story_views WHERE story_id = ?')->execute([$storyId]);
            $pdo->prepare('DELETE FROM stories WHERE id = ?')->execute([$storyId]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Story delete failed: ' . $e->getMessage());
            Response::error('Could not delete story', 500); return;
        }

        $mediaPath = self::mediaFilePath($story['media_url']);
        $thumbPath = self::mediaFilePath($story['thumbnail_url']);
        if ($mediaPath && is_file($mediaPath)) @unlink($mediaPath);
        if ($thumbPath && is_file($thumbPath)) @unlink($thumbPath);
        Response::success(['deleted' => true]);
    }
}
