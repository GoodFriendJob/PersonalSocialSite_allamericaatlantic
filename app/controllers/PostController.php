<?php

class PostController
{
    private static function publicMediaUrl($url)
    {
        if (!$url) return null;
        if (strpos($url, '/post_images/') === 0 || strpos($url, '/post_videos/') === 0) {
            return '/app/public' . $url;
        }
        return $url;
    }

    private static function mediaFilePath($url)
    {
        if (!$url) return null;
        if (strpos($url, '/app/public/') === 0) {
            return __DIR__ . '/../public/' . substr($url, strlen('/app/public/'));
        }
        if (strpos($url, '/post_images/') === 0 || strpos($url, '/post_videos/') === 0) {
            return __DIR__ . '/../public' . $url;
        }
        return null;
    }

    private static function createThumbnail($sourcePath, $destPath, $maxWidth = 900)
    {
        if (!function_exists('imagecreatetruecolor')) return false;
        $info = @getimagesize($sourcePath);
        if (!$info || empty($info[0]) || empty($info[1])) return false;
        list($width, $height) = $info;
        $newWidth = min((int)$maxWidth, (int)$width);
        $newHeight = max(1, (int)round($height * ($newWidth / $width)));

        switch ($info['mime']) {
            case 'image/jpeg': $source = @imagecreatefromjpeg($sourcePath); break;
            case 'image/png': $source = @imagecreatefrompng($sourcePath); break;
            case 'image/gif': $source = @imagecreatefromgif($sourcePath); break;
            default: return false;
        }
        if (!$source) return false;
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $saved = imagejpeg($thumb, $destPath, 84);
        imagedestroy($source);
        imagedestroy($thumb);
        return $saved;
    }

    private static function createVideoThumbnail($videoPath, $destPath)
    {
        if (!function_exists('exec')) return false;
        try {
            $output = [];
            $status = 1;
            exec('ffmpeg -y -i ' . escapeshellarg($videoPath) . ' -ss 00:00:01 -vframes 1 ' . escapeshellarg($destPath), $output, $status);
            return $status === 0 && is_file($destPath);
        } catch (Throwable $error) {
            error_log('Post video thumbnail unavailable: ' . $error->getMessage());
            return false;
        }
    }

    private static function normalizePostMedia(&$post)
    {
        $post['id'] = (int)$post['id'];
        $post['user_id'] = (int)$post['user_id'];
        $post['likes'] = (int)($post['likes'] ?? 0);
        $post['comment_count'] = (int)($post['comment_count'] ?? 0);
        $post['liked_by_me'] = (bool)($post['liked_by_me'] ?? false);
        $post['saved_by_me'] = (bool)($post['saved_by_me'] ?? false);
        $post['rating'] = $post['rating'] !== null ? (float)$post['rating'] : null;
        $post['rating_count'] = (int)($post['rating_count'] ?? 0);
        $post['my_rating'] = $post['my_rating'] !== null ? (int)$post['my_rating'] : null;
        foreach ($post['images'] as &$image) {
            $image['image_url'] = self::publicMediaUrl($image['image_url']);
            $image['thumbnail_url'] = self::publicMediaUrl($image['thumbnail_url']);
        }
        unset($image);
        foreach ($post['videos'] as &$video) {
            $video['video_url'] = self::publicMediaUrl($video['video_url']);
            $video['thumbnail_url'] = self::publicMediaUrl($video['thumbnail_url']);
        }
        unset($video);
    }

    private static function loadMedia(array &$posts)
    {
        global $pdo;
        $imageStmt = $pdo->prepare('SELECT image_url, thumbnail_url FROM post_images WHERE post_id = ?');
        $videoStmt = $pdo->prepare('SELECT video_url, thumbnail_url FROM post_videos WHERE post_id = ?');
        foreach ($posts as &$post) {
            $imageStmt->execute([$post['id']]);
            $post['images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
            $videoStmt->execute([$post['id']]);
            $post['videos'] = $videoStmt->fetchAll(PDO::FETCH_ASSOC);
            self::normalizePostMedia($post);
        }
        unset($post);
    }

    public static function index($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        list($page, $limit, $offset) = Pagination::getPageLimit();
        $limit = min($limit, 50);
        $ratingReady = RatingController::ensureTable();
        $ratingSelect = $ratingReady
            ? ", (SELECT AVG(cr.rating) FROM community_ratings cr WHERE cr.target_type = 'post' AND cr.target_id = p.id) AS rating,
                 (SELECT COUNT(*) FROM community_ratings crc WHERE crc.target_type = 'post' AND crc.target_id = p.id) AS rating_count,
                 (SELECT crm.rating FROM community_ratings crm WHERE crm.target_type = 'post' AND crm.target_id = p.id AND crm.user_id = ?) AS my_rating"
            : ', p.rating AS rating, 0 AS rating_count, NULL AS my_rating';

        $stmt = $pdo->prepare("SELECT p.id, p.user_id, p.content, p.created_at,
                    u.username, u.profile_pic, u.sport, u.position,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
                    EXISTS(SELECT 1 FROM post_likes mine WHERE mine.post_id = p.id AND mine.user_id = ?) AS liked_by_me,
                    EXISTS(SELECT 1 FROM saved_posts sp WHERE sp.post_id = p.id AND sp.user_id = ?) AS saved_by_me
                    {$ratingSelect}
                FROM posts p
                JOIN users u ON u.id = p.user_id
                ORDER BY CASE
                    WHEN p.user_id = ? THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM friends f
                        WHERE f.status = 'accepted'
                          AND ((f.user_id = ? AND f.friend_id = p.user_id)
                            OR (f.friend_id = ? AND f.user_id = p.user_id))
                    ) THEN 1
                    ELSE 2
                END, p.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $user['id'], PDO::PARAM_INT);
        $position = 3;
        if ($ratingReady) $stmt->bindValue($position++, $user['id'], PDO::PARAM_INT);
        $stmt->bindValue($position++, $user['id'], PDO::PARAM_INT);
        $stmt->bindValue($position++, $user['id'], PDO::PARAM_INT);
        $stmt->bindValue($position++, $user['id'], PDO::PARAM_INT);
        $stmt->bindValue($position++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($position, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::loadMedia($posts);
        Response::success(['page' => $page, 'limit' => $limit, 'posts' => $posts]);
    }

    public static function show($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $ratingReady = RatingController::ensureTable();
        $ratingSelect = $ratingReady
            ? ", (SELECT AVG(cr.rating) FROM community_ratings cr WHERE cr.target_type = 'post' AND cr.target_id = p.id) AS rating,
                 (SELECT COUNT(*) FROM community_ratings crc WHERE crc.target_type = 'post' AND crc.target_id = p.id) AS rating_count,
                 (SELECT crm.rating FROM community_ratings crm WHERE crm.target_type = 'post' AND crm.target_id = p.id AND crm.user_id = ?) AS my_rating"
            : ', p.rating AS rating, 0 AS rating_count, NULL AS my_rating';
        $stmt = $pdo->prepare("SELECT p.id, p.user_id, p.content, p.created_at,
                    u.username, u.profile_pic, u.sport, u.position,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
                    EXISTS(SELECT 1 FROM post_likes mine WHERE mine.post_id = p.id AND mine.user_id = ?) AS liked_by_me,
                    EXISTS(SELECT 1 FROM saved_posts sp WHERE sp.post_id = p.id AND sp.user_id = ?) AS saved_by_me
                    {$ratingSelect}
                FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?");
        $queryParams = [$user['id'], $user['id']];
        if ($ratingReady) $queryParams[] = $user['id'];
        $queryParams[] = (int)$params['id'];
        $stmt->execute($queryParams);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$posts) {
            Response::error('Post not found', 404);
            return;
        }
        self::loadMedia($posts);
        Response::success(['post' => $posts[0]]);
    }

    public static function create($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            $json = json_decode(file_get_contents('php://input'), true);
            $content = trim(is_array($json) ? ($json['content'] ?? '') : '');
        }
        $hasImages = !empty($_FILES['images']['name'][0]);
        $hasVideo = !empty($_FILES['video']['name']);
        if ($content === '' && !$hasImages && !$hasVideo) {
            Response::error('Write something or choose a photo or video', 400);
            return;
        }
        $content = function_exists('mb_substr') ? mb_substr($content, 0, 5000) : substr($content, 0, 5000);
        $rating = isset($_POST['rating']) && is_numeric($_POST['rating']) ? (int)$_POST['rating'] : null;
        if ($rating !== null && ($rating < 1 || $rating > 5)) $rating = null;

        $stmt = $pdo->prepare('INSERT INTO posts (user_id, content, rating, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$user['id'], $content, $rating]);
        $postId = (int)$pdo->lastInsertId();

        try {
            if ($hasImages) self::uploadImages($postId, $_FILES['images']);
            if ($hasVideo) self::uploadVideo($postId, $_FILES['video']);
        } catch (RuntimeException $error) {
            $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
            Response::error($error->getMessage(), 400);
            return;
        }

        ActivityLogger::log($user['id'], 'create_post', $postId, 'post');
        Response::success(['id' => $postId, 'user_id' => $user['id'], 'content' => $content], 201);
    }

    private static function uploadImages($postId, $files)
    {
        global $pdo;
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        $count = min(count($files['name']), 6);
        $uploadDir = __DIR__ . '/../public/post_images';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Could not prepare the photo upload folder');
        }
        $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

        for ($i = 0; $i < $count; $i++) {
            if ((int)$files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ((int)$files['size'][$i] > 8 * 1024 * 1024) {
                throw new RuntimeException('Each photo must be smaller than 8 MB');
            }
            $mime = $finfo ? $finfo->file($files['tmp_name'][$i]) : mime_content_type($files['tmp_name'][$i]);
            if (!isset($allowed[$mime])) throw new RuntimeException('One of the selected photos is not supported');
            $base = 'post_' . $postId . '_' . bin2hex(random_bytes(5));
            $filename = $base . '.' . $allowed[$mime];
            $path = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($files['tmp_name'][$i], $path)) {
                throw new RuntimeException('A photo could not be saved');
            }
            $thumbName = 'thumb_' . $base . '.jpg';
            $thumbPath = $uploadDir . '/' . $thumbName;
            $thumbUrl = null;
            if (self::createThumbnail($path, $thumbPath)) {
                $thumbUrl = '/app/public/post_images/' . $thumbName;
            }
            $pdo->prepare('INSERT INTO post_images (post_id, image_url, thumbnail_url) VALUES (?, ?, ?)')
                ->execute([$postId, '/app/public/post_images/' . $filename, $thumbUrl]);
        }
    }

    private static function uploadVideo($postId, $file)
    {
        global $pdo;
        if ((int)$file['error'] !== UPLOAD_ERR_OK) return;
        if ((int)$file['size'] > 75 * 1024 * 1024) throw new RuntimeException('Video must be smaller than 75 MB');
        $allowed = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/x-m4v' => 'm4v'];
        $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? $finfo->file($file['tmp_name']) : mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) throw new RuntimeException('That video format is not supported');
        $uploadDir = __DIR__ . '/../public/post_videos';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new RuntimeException('Could not prepare the video upload folder');
        }
        $base = 'video_' . $postId . '_' . bin2hex(random_bytes(5));
        $filename = $base . '.' . $allowed[$mime];
        $path = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $path)) throw new RuntimeException('The video could not be saved');
        $thumbName = 'thumb_' . $base . '.jpg';
        $thumbPath = $uploadDir . '/' . $thumbName;
        $thumbUrl = self::createVideoThumbnail($path, $thumbPath) ? '/app/public/post_videos/' . $thumbName : null;
        $pdo->prepare('INSERT INTO post_videos (post_id, video_url, thumbnail_url) VALUES (?, ?, ?)')
            ->execute([$postId, '/app/public/post_videos/' . $filename, $thumbUrl]);
    }

    public static function update($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Post not found', 404); return; }
        if ((int)$owner !== $user['id'] && $user['role'] !== 'admin') {
            Response::error('Unauthorized', 403); return;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $content = trim(is_array($data) ? ($data['content'] ?? '') : ($_POST['content'] ?? ''));
        if ($content === '') { Response::error('Post text is required', 400); return; }
        $pdo->prepare('UPDATE posts SET content = ? WHERE id = ?')->execute([$content, $postId]);
        ActivityLogger::log($user['id'], 'edit_post', $postId, 'post');
        Response::success(['updated' => true]);
    }

    public static function getLikes($params)
    {
        global $pdo;
        AuthMiddleware::requireAuth();
        $stmt = $pdo->prepare('SELECT u.id, u.username, u.profile_pic AS avatar
            FROM post_likes pl JOIN users u ON u.id = pl.user_id WHERE pl.post_id = ? ORDER BY pl.created_at DESC');
        $stmt->execute([(int)$params['id']]);
        Response::success(['likes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public static function save($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT id FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        if (!$stmt->fetchColumn()) { Response::error('Post not found', 404); return; }
        $stmt = $pdo->prepare('SELECT 1 FROM saved_posts WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$user['id'], $postId]);
        if (!$stmt->fetchColumn()) {
            $pdo->prepare('INSERT INTO saved_posts (user_id, post_id, created_at) VALUES (?, ?, NOW())')
                ->execute([$user['id'], $postId]);
        }
        Response::success(['saved' => true]);
    }

    public static function unsave($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $pdo->prepare('DELETE FROM saved_posts WHERE user_id = ? AND post_id = ?')
            ->execute([$user['id'], (int)$params['id']]);
        Response::success(['saved' => false]);
    }

    public static function delete($params)
    {
        global $pdo;
        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];
        $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
        $stmt->execute([$postId]);
        $owner = $stmt->fetchColumn();
        if (!$owner) { Response::error('Post not found', 404); return; }
        if ((int)$owner !== $user['id'] && $user['role'] !== 'admin') {
            Response::error('Unauthorized', 403); return;
        }

        $imageStmt = $pdo->prepare('SELECT image_url, thumbnail_url FROM post_images WHERE post_id = ?');
        $imageStmt->execute([$postId]);
        $videoStmt = $pdo->prepare('SELECT video_url, thumbnail_url FROM post_videos WHERE post_id = ?');
        $videoStmt->execute([$postId]);
        $files = array_merge($imageStmt->fetchAll(PDO::FETCH_ASSOC), $videoStmt->fetchAll(PDO::FETCH_ASSOC));

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM saved_posts WHERE post_id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM post_likes WHERE post_id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM comments WHERE post_id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM post_images WHERE post_id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM post_videos WHERE post_id = ?')->execute([$postId]);
            $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
            $pdo->commit();
        } catch (PDOException $error) {
            $pdo->rollBack();
            error_log('Post delete failed: ' . $error->getMessage());
            Response::error('Could not delete post', 500); return;
        }

        foreach ($files as $file) {
            foreach (['image_url', 'video_url', 'thumbnail_url'] as $key) {
                $path = self::mediaFilePath($file[$key] ?? null);
                if ($path && is_file($path)) @unlink($path);
            }
        }
        ActivityLogger::log($user['id'], 'delete_post', $postId, 'post');
        Response::success(['deleted' => true]);
    }
}
