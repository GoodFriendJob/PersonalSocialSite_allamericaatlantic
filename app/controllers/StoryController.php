<?php

class StoryController {

    /* -------------------------
       IMAGE THUMBNAIL FUNCTION
    -------------------------- */
    private static function createThumbnail($sourcePath, $destPath, $maxWidth = 300) {
        $info = getimagesize($sourcePath);
        if (!$info) return false;

        list($width, $height) = $info;
        $ratio = $height / $width;

       $newWidth  = (int)$maxWidth;
       $newHeight = (int)round($maxWidth * $ratio);

        switch ($info['mime']) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $src = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagejpeg($thumb, $destPath, 80);

        imagedestroy($src);
        imagedestroy($thumb);

        return true;
    }

    /* -------------------------
       VIDEO THUMBNAIL FUNCTION
    -------------------------- */
    private static function createVideoThumbnail($videoPath, $thumbPath) {
        $cmd = "ffmpeg -i " . escapeshellarg($videoPath) . " -ss 00:00:01 -vframes 1 " . escapeshellarg($thumbPath);
        exec($cmd, $output, $return);
        return $return === 0;
    }

 /* -------------------------
   CREATE STORY (IMAGE/VIDEO)
-------------------------- */
public static function create($params) {
    global $pdo;
    $user = AuthMiddleware::requireAuth();

    /* -------------------------
       VALIDATE MEDIA FILE
    -------------------------- */
    if (empty($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        Response::error("Media file is required", 400);
        return;
    }

    $caption = $_POST['caption'] ?? null;
    $file    = $_FILES['media'];

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']);

    /* -------------------------
       ALLOWED TYPES
    -------------------------- */
    $allowedImageMimes = ['image/jpeg', 'image/png', 'image/gif'];

    $allowedVideoExtensions = ['mp4', 'webm', 'mov'];
    $allowedVideoMimes = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/3gpp',
        'video/3gpp2',
        'video/x-m4v'
    ];

    /* -------------------------
       DETECT IMAGE OR VIDEO
    -------------------------- */
    $isImage = in_array($mime, $allowedImageMimes, true);

    $isVideo = (
        in_array($ext, $allowedVideoExtensions, true) ||
        in_array($mime, $allowedVideoMimes, true) ||
        strpos($mime, 'video') === 0
    );

    if (!$isImage && !$isVideo) {
        Response::error("Invalid media type", 400);
        return;
    }

    if ($file['size'] > 25 * 1024 * 1024) {
        Response::error("Video too large (max 25MB)", 400);
        return;
    }

    $mediaType = $isImage ? 'image' : 'video';

    /* -------------------------
       SAVE MEDIA FILE
    -------------------------- */
    $uploadDir = __DIR__ . '/../public/uploads/stories';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'story_' . $user['id'] . '_' . time() . '.' . $ext;
    $path     = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        Response::error("Failed to save media file", 500);
        return;
    }

    $mediaUrl = '/uploads/stories/' . $filename;

    /* -------------------------
       THUMBNAIL HANDLING
       (NO FFMPEG — FRONTEND PROVIDES BASE64)
    -------------------------- */
    $thumbnailUrl = null;

    // IMAGE THUMBNAIL (server-generated)
    if ($isImage) {
        $thumbName = 'thumb_' . $filename;
        $thumbPath = $uploadDir . '/' . $thumbName;
        $thumbnailUrl = '/uploads/stories/' . $thumbName;

        self::createThumbnail($path, $thumbPath);
    }

    // VIDEO THUMBNAIL (frontend-generated Base64)
    if ($isVideo) {
        $thumbnailBase64 = $_POST['thumbnail'] ?? null;

        if ($thumbnailBase64) {
            $thumbName = 'thumb_' . $filename . '.jpg';
            $thumbPath = $uploadDir . '/' . $thumbName;
            $thumbnailUrl = '/uploads/stories/' . $thumbName;

            // Extract Base64 data
            $thumbnailData = explode(',', $thumbnailBase64)[1] ?? null;

            if ($thumbnailData) {
                file_put_contents($thumbPath, base64_decode($thumbnailData));
            }
        }
    }

    /* -------------------------
       SAVE STORY
    -------------------------- */
    $stmt = $pdo->prepare(
        "INSERT INTO stories (user_id, media_url, media_type, thumbnail_url, caption, created_at, expires_at)
         VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))"
    );
    $stmt->execute([$user['id'], $mediaUrl, $mediaType, $thumbnailUrl, $caption]);

    $storyId = $pdo->lastInsertId();

    ActivityLogger::log($user['id'], 'create_story', $storyId, 'story');

    Response::success([
        'id'            => (int)$storyId,
        'media_url'     => $mediaUrl,
        'thumbnail_url' => $thumbnailUrl,
        'media_type'    => $mediaType
    ]);
}


    /* -------------------------
       STORY FEED (GROUPED + SEEN)
    -------------------------- */
    public static function feed($params) {
        global $pdo;
        $user = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare(
            "SELECT s.id, s.user_id, u.username, s.media_url, s.media_type, s.thumbnail_url, s.created_at
             FROM stories s
             JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > NOW()
             ORDER BY s.created_at DESC"
        );
        $stmt->execute();
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // seen / unseen
        $seenStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM story_views WHERE story_id = ? AND viewer_id = ?"
        );

        foreach ($stories as &$story) {
            $seenStmt->execute([$story['id'], $user['id']]);
            $story['seen'] = $seenStmt->fetchColumn() > 0;
        }
        unset($story);

        // group by user
        $grouped = [];

        foreach ($stories as $story) {
            $uid = $story['user_id'];

            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user_id'  => $uid,
                    'username' => $story['username'],
                    'stories'  => []
                ];
            }

            $grouped[$uid]['stories'][] = $story;
        }

        Response::success(['stories' => array_values($grouped)]);
    }

    /* -------------------------
       MY STORIES (ACTIVE ONLY)
    -------------------------- */
    public static function myStories($params) {
        global $pdo;
        $user = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare(
            "SELECT id, media_url, media_type, thumbnail_url, created_at, expires_at
             FROM stories
             WHERE user_id = ? AND expires_at > NOW()
             ORDER BY created_at DESC"
        );
        $stmt->execute([$user['id']]);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success(['stories' => $stories]);
    }

    /* -------------------------
       MARK STORY AS VIEWED
    -------------------------- */
    public static function view($params) {
        global $pdo;
        $user    = AuthMiddleware::requireAuth();
        $storyId = (int)$params['id'];

        $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND expires_at > NOW()");
        $stmt->execute([$storyId]);
        if (!$stmt->fetchColumn()) {
            Response::error("Story not found or expired", 404);
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO story_views (story_id, viewer_id, viewed_at)
             VALUES (?, ?, NOW())"
        );
        $stmt->execute([$storyId, $user['id']]);

        Response::success(['viewed' => true]);
    }

    /* -------------------------
       GET STORY VIEWERS
    -------------------------- */
    public static function viewers($params) {
        global $pdo;
        $user    = AuthMiddleware::requireAuth();
        $storyId = (int)$params['id'];

        $stmt = $pdo->prepare("SELECT user_id FROM stories WHERE id = ?");
        $stmt->execute([$storyId]);
        $owner = $stmt->fetchColumn();

        if (!$owner) {
            Response::error("Story not found", 404);
            return;
        }

        if ($owner != $user['id'] && $user['role'] !== 'admin') {
            Response::error("Unauthorized", 403);
            return;
        }

        $stmt = $pdo->prepare(
            "SELECT sv.viewer_id, u.username, sv.viewed_at
             FROM story_views sv
             JOIN users u ON u.id = sv.viewer_id
             WHERE sv.story_id = ?
             ORDER BY sv.viewed_at DESC"
        );
        $stmt->execute([$storyId]);
        $viewers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success(['viewers' => $viewers]);
    }

    /* -------------------------
       DELETE STORY
    -------------------------- */
    public static function delete($params) {
        global $pdo;
        $user    = AuthMiddleware::requireAuth();
        $storyId = (int)$params['id'];

        $stmt = $pdo->prepare("SELECT user_id, media_url, thumbnail_url FROM stories WHERE id = ?");
        $stmt->execute([$storyId]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$story) {
            Response::error("Story not found", 404);
            return;
        }

        if ($story['user_id'] != $user['id'] && $user['role'] !== 'admin') {
            Response::error("Unauthorized", 403);
            return;
        }

        $media = __DIR__ . '/../public' . $story['media_url'];
        $thumb = __DIR__ . '/../public' . $story['thumbnail_url'];

        if (file_exists($media)) unlink($media);
        if ($story['thumbnail_url'] && file_exists($thumb)) unlink($thumb);

        $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
        $stmt->execute([$storyId]);

        Response::success(['deleted' => true]);
    }
}
