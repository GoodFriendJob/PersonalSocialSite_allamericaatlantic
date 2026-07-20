<?php

class PostController {

    /* -------------------------
       LIST POSTS (WITH MEDIA)
    -------------------------- */
   public static function index($params) {
    global $pdo;

    list($page, $limit, $offset) = Pagination::getPageLimit();

    $stmt = $pdo->prepare(
        "SELECT p.id, p.content, p.created_at, u.username,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
                (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
         FROM posts p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at DESC
         LIMIT ? OFFSET ?"
    );

    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        'page'  => $page,
        'limit' => $limit,
        'posts' => $posts
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
       INSERT POST
    -------------------------- */
    $stmt = $pdo->prepare(
        "INSERT INTO posts (user_id, content, created_at)
         VALUES (?, ?, NOW())"
    );
    $stmt->execute([$user['id'], $content]);

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

            $uploadDir = __DIR__ . '/../public/post_images';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $path = $uploadDir . '/' . $filename;

            if (move_uploaded_file($files['tmp_name'][$i], $path)) {

                $imageUrl = '/post_images/' . $filename;

                // Thumbnail
                $thumbName = 'thumb_' . $filename;
                $thumbPath = $uploadDir . '/' . $thumbName;
                $thumbUrl  = '/post_images/' . $thumbName;

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

        $uploadDir = __DIR__ . '/../public/post_videos';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $path = $uploadDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $path)) {

            $videoUrl = '/post_videos/' . $filename;

            // Thumbnail
            $thumbName = 'thumb_' . $filename . '.jpg';
            $thumbPath = $uploadDir . '/' . $thumbName;
            $thumbUrl  = '/post_videos/' . $thumbName;

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
        'id'      => $id,
        'user_id' => (int)$user['id'],
        'content' => $content
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
