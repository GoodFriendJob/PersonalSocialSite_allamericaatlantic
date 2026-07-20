<?php

class PostController {

    /* -------------------------
       MEDIA HELPERS

       These were called from the upload paths below but never defined, so
       every post that included an image or video died with "Call to
       undefined method PostController::createThumbnail()". Both now delegate
       to Media, which degrades gracefully when GD or ffmpeg is missing.
    -------------------------- */
    private static function createThumbnail($sourcePath, $destPath, $maxWidth = 600) {
        return Media::thumbnail($sourcePath, $destPath, (int)$maxWidth);
    }

    private static function createVideoThumbnail($videoPath, $thumbPath) {
        return Media::videoThumbnail($videoPath, $thumbPath);
    }

    /* -------------------------
       LIST POSTS (WITH MEDIA)
    -------------------------- */
   public static function index($params) {
    global $pdo;

    list($page, $limit, $offset) = Pagination::getPageLimit();

    // Guests may browse public posts, so auth is optional here.
    $me   = AuthMiddleware::user();
    $meId = $me['id'] ?? 0;

    // Optional category filter, driven by the sport tabs in the feed.
    $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
        ? (int)$_GET['category_id']
        : null;

    $categoryClause = $categoryId ? ' AND p.category_id = :cat' : '';

    // Total matching rows, so the client knows whether more pages exist.
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM posts p
          WHERE (p.visibility = 'public' OR p.user_id = :me0)" . $categoryClause
    );
    $countStmt->bindValue(':me0', $meId, PDO::PARAM_INT);
    if ($categoryId) $countStmt->bindValue(':cat', $categoryId, PDO::PARAM_INT);
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT p.id, p.user_id, p.title, p.content, p.created_at, p.visibility,
                p.category_id, c.name AS category_name,
                u.username, u.profile_pic,
                (SELECT COUNT(*) FROM post_likes pl  WHERE pl.post_id = p.id) AS likes,
                (SELECT COUNT(*) FROM comments c     WHERE c.post_id  = p.id) AS comment_count,
                (SELECT COUNT(*) FROM post_shares ps WHERE ps.post_id = p.id) AS share_count,
                EXISTS(SELECT 1 FROM post_likes  pl2 WHERE pl2.post_id = p.id AND pl2.user_id = :me1) AS is_liked,
                EXISTS(SELECT 1 FROM saved_posts sp  WHERE sp.post_id  = p.id AND sp.user_id  = :me2) AS is_saved
         FROM posts p
         JOIN users u ON u.id = p.user_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE (p.visibility = 'public' OR p.user_id = :me3)" . $categoryClause . "
         ORDER BY p.created_at DESC
         LIMIT :lim OFFSET :off"
    );

    $stmt->bindValue(':me1', $meId, PDO::PARAM_INT);
    $stmt->bindValue(':me2', $meId, PDO::PARAM_INT);
    $stmt->bindValue(':me3', $meId, PDO::PARAM_INT);
    if ($categoryId) $stmt->bindValue(':cat', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize the flags MySQL returns as 0/1 strings.
    foreach ($posts as &$p) {
        $p['is_liked']      = (bool)$p['is_liked'];
        $p['is_saved']      = (bool)$p['is_saved'];
        $p['likes']         = (int)$p['likes'];
        $p['comment_count'] = (int)$p['comment_count'];
        $p['share_count']   = (int)$p['share_count'];
        $p['is_mine']       = ((int)$p['user_id'] === $meId);
    }
    unset($p);

    /* Load images + videos for each post */
    foreach ($posts as &$post) {

        // Images
        $stmt2 = $pdo->prepare(
            "SELECT image_url, thumbnail_url
             FROM post_images
             WHERE post_id = ?"
        );
        $stmt2->execute([$post['id']]);
        $post['images'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Videos
        $stmt3 = $pdo->prepare(
            "SELECT video_url, thumbnail_url
             FROM post_videos
             WHERE post_id = ?"
        );
        $stmt3->execute([$post['id']]);
        $post['videos'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }

    Response::success([
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => (int)ceil($total / $limit),
        'has_more'    => ($offset + count($posts)) < $total,
        'category_id' => $categoryId,
        'posts'       => $posts
    ]);
}

    /* -------------------------
       GET SINGLE POST
    -------------------------- */
    public static function show($params) {
        global $pdo;

        $id = (int)$params['id'];

        $stmt = $pdo->prepare(
            "SELECT p.id, p.content, p.created_at, u.username,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comments
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);

        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            Response::error("Post not found", 404);
            return;
        }

        // Images
        $stmt2 = $pdo->prepare("SELECT image_url FROM post_images WHERE post_id = ?");
        $stmt2->execute([$post['id']]);
        $post['images'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        // Videos
        $stmt3 = $pdo->prepare("SELECT video_url FROM post_videos WHERE post_id = ?");
        $stmt3->execute([$post['id']]);
        $post['videos'] = $stmt3->fetchAll(PDO::FETCH_COLUMN);

        Response::success($post);
    }

    /* -------------------------
       CREATE POST (MEDIA)
    -------------------------- */
   public static function create($params) {
    global $pdo;

    $user = AuthMiddleware::requireAuth();

    /* -------------------------
       READ CONTENT (JSON OR FORM-DATA)
    -------------------------- */

    $content = null;

    // 1. Try JSON first
    $json = json_decode(file_get_contents("php://input"), true);
    if (is_array($json) && isset($json['content'])) {
        $content = trim($json['content']);
    }

    // 2. If JSON empty, try form-data
    if (!$content && isset($_POST['content'])) {
        $content = trim($_POST['content']);
    }

    if (!$content) {
        Response::error("Missing content", 400);
        return;
    }

    /* -------------------------
       OPTIONAL FIELDS
    -------------------------- */
    $title      = trim($json['title']      ?? $_POST['title']      ?? '');
    $visibility = trim($json['visibility'] ?? $_POST['visibility'] ?? 'public');
    $categoryId = $json['category_id'] ?? $_POST['category_id'] ?? null;

    if (!in_array($visibility, ['public', 'private'], true)) {
        $visibility = 'public';
    }

    /* -------------------------
       INSERT POST
    -------------------------- */
    $stmt = $pdo->prepare(
        "INSERT INTO posts (user_id, category_id, title, content, visibility, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $user['id'],
        $categoryId !== null && $categoryId !== '' ? (int)$categoryId : null,
        $title !== '' ? $title : null,
        $content,
        $visibility,
    ]);

    $id = (int)$pdo->lastInsertId();

    /* -------------------------
       IMAGE UPLOADS (form-data only)
    -------------------------- */
    if (!empty($_FILES['images'])) {
        $files = $_FILES['images'];

        for ($i = 0; $i < count($files['name']); $i++) {

            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $type = $files['type'][$i];
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($type, $allowed, true)) continue;

            if ($files['size'][$i] > 5 * 1024 * 1024) continue;

            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = 'post_' . $id . '_' . time() . '_' . $i . '.' . $ext;

            $uploadDir = Media::dir('posts');

            $path = $uploadDir . '/' . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $path)) {

                $imageUrl = Media::url('posts', $filename);

                // Thumbnail
                $thumbName = 'thumb_' . $filename;
                $thumbPath = $uploadDir . '/' . $thumbName;
                $thumbUrl  = Media::url('posts', $thumbName);

                self::createThumbnail($path, $thumbPath);

                $stmtImg = $pdo->prepare(
                    "INSERT INTO post_images (post_id, image_url, thumbnail_url)
                     VALUES (?, ?, ?)"
                );
                $stmtImg->execute([$id, $imageUrl, $thumbUrl]);
            }
        }
    }

    /* -------------------------
       VIDEO UPLOAD (form-data only)
    -------------------------- */
    if (!empty($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {

        $file = $_FILES['video'];
        $allowed = ['video/mp4', 'video/webm'];

        if (!in_array($file['type'], $allowed, true)) {
            Response::error("Invalid video format", 400);
            return;
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            Response::error("Video too large", 400);
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'video_' . $id . '_' . time() . '.' . $ext;

        $uploadDir = Media::dir('videos');

        $path = $uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {

            $videoUrl = Media::url('videos', $filename);

            // Thumbnail
            $thumbName = 'thumb_' . $filename . '.jpg';
            $thumbPath = $uploadDir . '/' . $thumbName;
            $thumbUrl  = Media::url('videos', $thumbName);

            self::createVideoThumbnail($path, $thumbPath);

            $stmtVid = $pdo->prepare(
                "INSERT INTO post_videos (post_id, video_url, thumbnail_url)
                 VALUES (?, ?, ?)"
            );
            $stmtVid->execute([$id, $videoUrl, $thumbUrl]);
        }
    }

    ActivityLogger::log($user['id'], 'create_post', $id, 'post');

    Response::success([
        'id'         => $id,
        'user_id'    => (int)$user['id'],
        'content'    => $content,
        'title'      => $title !== '' ? $title : null,
        'visibility' => $visibility,
    ]);
}

    /* -------------------------
       UPDATE POST
    -------------------------- */
   public static function update($params) {
    global $pdo;

    $user = AuthMiddleware::requireAuth();
    $postId = (int)$params['id'];

    /* -------------------------
       VERIFY OWNERSHIP
    -------------------------- */
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $owner = $stmt->fetchColumn();

    if (!$owner) {
        Response::error("Post not found", 404);
        return;
    }

    if ($owner != $user['id'] && $user['role'] !== 'admin') {
        Response::error("Unauthorized", 403);
        return;
    }

    /* -------------------------
       READ INPUT (JSON OR FORM-DATA)
    -------------------------- */

    $input = json_decode(file_get_contents("php://input"), true);
    $isJson = is_array($input);

    // Content
    $newContent = null;

    if ($isJson && isset($input['content'])) {
        $newContent = trim($input['content']);
    }

    if (!$newContent && isset($_POST['content'])) {
        $newContent = trim($_POST['content']);
    }

    // Replace flags
    $replaceImages = false;
    $replaceVideo  = false;

    if ($isJson) {
        $replaceImages = isset($input['replace_images']) && $input['replace_images'] == 1;
        $replaceVideo  = isset($input['replace_video']) && $input['replace_video'] == 1;
    } else {
        $replaceImages = isset($_POST['replace_images']) && $_POST['replace_images'] == "1";
        $replaceVideo  = isset($_POST['replace_video']) && $_POST['replace_video'] == "1";
    }

    /* -------------------------
       UPDATE TEXT
    -------------------------- */
    if ($newContent !== null) {
        $stmt = $pdo->prepare("UPDATE posts SET content = ? WHERE id = ?");
        $stmt->execute([$newContent, $postId]);
    }

    /* -------------------------
       REPLACE IMAGES
    -------------------------- */
    if ($replaceImages) {

        $stmt = $pdo->prepare("SELECT image_url, thumbnail_url FROM post_images WHERE post_id = ?");
        $stmt->execute([$postId]);
        $oldImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($oldImages as $img) {
            $full  = __DIR__ . '/../public' . $img['image_url'];
            $thumb = __DIR__ . '/../public' . $img['thumbnail_url'];
            if (file_exists($full)) unlink($full);
            if (file_exists($thumb)) unlink($thumb);
        }

        $pdo->prepare("DELETE FROM post_images WHERE post_id = ?")->execute([$postId]);
    }

    /* -------------------------
       REPLACE VIDEO
    -------------------------- */
    if ($replaceVideo) {

        $stmt = $pdo->prepare("SELECT video_url, thumbnail_url FROM post_videos WHERE post_id = ?");
        $stmt->execute([$postId]);
        $oldVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($oldVideos as $vid) {
            $full  = __DIR__ . '/../public' . $vid['video_url'];
            $thumb = __DIR__ . '/../public' . $vid['thumbnail_url'];
            if (file_exists($full)) unlink($full);
            if (file_exists($thumb)) unlink($thumb);
        }

        $pdo->prepare("DELETE FROM post_videos WHERE post_id = ?")->execute([$postId]);
    }

    /* -------------------------
       UPLOAD NEW IMAGES (FORM-DATA ONLY)
    -------------------------- */
    if (!empty($_FILES['images'])) {
        $files = $_FILES['images'];

        for ($i = 0; $i < count($files['name']); $i++) {

            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

            $type = $files['type'][$i];
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($type, $allowed, true)) continue;

            if ($files['size'][$i] > 5 * 1024 * 1024) continue;

            $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = 'post_' . $postId . '_' . time() . '_' . $i . '.' . $ext;

            $uploadDir = __DIR__ . '/../public/post_images';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $path = $uploadDir . '/' . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $path)) {

                $imageUrl = '/post_images/' . $filename;

                $thumbName = 'thumb_' . $filename;
                $thumbPath = $uploadDir . '/' . $thumbName;
                $thumbUrl  = '/post_images/' . $thumbName;

                self::createThumbnail($path, $thumbPath);

                $stmtImg = $pdo->prepare(
                    "INSERT INTO post_images (post_id, image_url, thumbnail_url)
                     VALUES (?, ?, ?)"
                );
                $stmtImg->execute([$postId, $imageUrl, $thumbUrl]);
            }
        }
    }

    /* -------------------------
       UPLOAD NEW VIDEO (FORM-DATA ONLY)
    -------------------------- */
    if (!empty($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {

        $file = $_FILES['video'];
        $allowed = ['video/mp4', 'video/webm'];

        if (!in_array($file['type'], $allowed, true)) {
            Response::error("Invalid video format", 400);
            return;
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            Response::error("Video too large", 400);
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'video_' . $postId . '_' . time() . '.' . $ext;

        $uploadDir = __DIR__ . '/../public/post_videos';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $path = $uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {

            $videoUrl = '/post_videos/' . $filename;

            $thumbName = 'thumb_' . $filename . '.jpg';
            $thumbPath = $uploadDir . '/' . $thumbName;
            $thumbUrl  = '/post_videos/' . $thumbName;

            self::createVideoThumbnail($path, $thumbPath);

            $stmtVid = $pdo->prepare(
                "INSERT INTO post_videos (post_id, video_url, thumbnail_url)
                 VALUES (?, ?, ?)"
            );
            $stmtVid->execute([$postId, $videoUrl, $thumbUrl]);
        }
    }

    ActivityLogger::log($user['id'], 'edit_post', $postId, 'post');

    Response::success(['updated' => true]);
}
     /* ----------------------
	 get likes
     -------------------------- */
	public static function getLikes($params) {
    global $pdo;

    $postId = $params['id'];

    $stmt = $pdo->prepare("
        SELECT users.id, users.username, users.profile_pic AS avatar
        FROM post_likes
        JOIN users ON users.id = post_likes.user_id
        WHERE post_likes.post_id = ?
    ");
    $stmt->execute([$postId]);

    $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::json(["success" => true, "likes" => $likes]);
}
    /* ----------------------
   ADD Comments
-------------------------- */
public static function addComment($params) {
    global $pdo;

    $postId = $params['id'];
    $userId = AuthMiddleware::getUserId();

    // Read JSON body safely
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['comment'])) {
        Response::json(["error" => "Missing content"], 400);
        return;
    }

    $comment = trim($data['comment']);

    if ($comment === '') {
        Response::json(["error" => "Comment cannot be empty"], 400);
        return;
    }

    // Insert comment
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $userId, $comment]);

    ActivityLogger::log($userId, "comment_post", $postId, "post");

    Response::json([
        "success" => true,
        "comment_id" => $pdo->lastInsertId()
    ]);
}

      /* -------------------------
      GET COMMENTS FOR POST
    -------------------------- */
	public static function getComments($params) {
    global $pdo;

    $postId = $params['id'];

    $stmt = $pdo->prepare("
        SELECT comments.id, comments.comment, comments.created_at,
               users.id AS user_id, users.username, users.profile_pic AS avatar
        FROM comments
        JOIN users ON users.id = comments.user_id
        WHERE comments.post_id = ?
        ORDER BY comments.id ASC
    ");
    $stmt->execute([$postId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::json(["success" => true, "comments" => $comments]);
}
      /* -------------------------
       DELETE COMMENTS
    -------------------------- */
	public static function deleteComment($params) {
    global $pdo;

    $commentId = $params['id'];
    $userId = AuthMiddleware::getUserId();

    // Check ownership
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if (!$comment) {
        Response::json(["error" => "Comment not found"], 404);
        return;
    }

    if ($comment['user_id'] != $userId) {
        Response::json(["error" => "Unauthorized"], 403);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$commentId]);

    ActivityLogger::log($userId, "delete_comment", $commentId, "comment");

    Response::json(["success" => true, "deleted" => true]);
}

    /* -------------------------
       DELETE POST
    -------------------------- */
    public static function delete($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();
        $postId = (int)$params['id'];

        // Verify ownership
        $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
        $owner = $stmt->fetchColumn();

        if (!$owner) {
            Response::error("Post not found", 404);
            return;
        }

        if ($owner != $user['id'] && $user['role'] !== 'admin') {
            Response::error("Unauthorized", 403);
            return;
        }

        // Delete images
        $stmt = $pdo->prepare("SELECT image_url, thumbnail_url FROM post_images WHERE post_id = ?");
        $stmt->execute([$postId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as $img) {
            $full  = __DIR__ . '/../public' . $img['image_url'];
            $thumb = __DIR__ . '/../public' . $img['thumbnail_url'];
            if (file_exists($full)) unlink($full);
            if (file_exists($thumb)) unlink($thumb);
        }

        // Delete videos
        $stmt = $pdo->prepare("SELECT video_url, thumbnail_url FROM post_videos WHERE post_id = ?");
        $stmt->execute([$postId]);
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($videos as $vid) {
            $full  = __DIR__ . '/../public' . $vid['video_url'];
            $thumb = __DIR__ . '/../public' . $vid['thumbnail_url'];
            if (file_exists($full)) unlink($full);
            if (file_exists($thumb)) unlink($thumb);
        }

        // Delete post
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);

        ActivityLogger::log($user['id'], 'delete_post', $postId, 'post');

        Response::success(['deleted' => true]);
    }
}
