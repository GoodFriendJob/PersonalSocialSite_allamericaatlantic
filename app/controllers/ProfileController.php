<?php
require_once __DIR__ . '/../core/AuthMiddleware.php';

class ProfileController {

    /* -------------------------
       GET CURRENT USER PROFILE
    -------------------------- */
    public static function me() {
        global $pdo;
        $user = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, u.email,
                    u.bio,
                    COALESCE(NULLIF(u.profile_pic, ''), NULLIF(p.avatar_url, ''), 'assets/img/default-avatar.svg') AS avatar_url
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE u.id = ?"
        );
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::success(['user' => $profile]);
    }

    /* -------------------------
       UPDATE PROFILE (username + bio)
    -------------------------- */
    public static function updateProfile() {
        global $pdo;
        $user = AuthMiddleware::requireAuth();

        // Safe JSON read
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error("Invalid JSON body", 400);
            return;
        }

        $bio      = isset($data['bio']) ? trim($data['bio']) : null;
        $username = isset($data['username']) ? trim($data['username']) : null;

        // Update username
        if ($username !== null && $username !== '') {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $user['id']]);
        }

        // Keep the users table authoritative because posts, stories, and the
        // main profile UI all read their avatar and bio from it.
        if ($bio !== null) {
            $stmt = $pdo->prepare(
                "UPDATE users SET bio = ? WHERE id = ?"
            );
            $stmt->execute([$bio, $user['id']]);

            $stmt = $pdo->prepare(
                "UPDATE profiles SET bio = ?, updated_at = NOW() WHERE user_id = ?"
            );
            $stmt->execute([$bio, $user['id']]);
        }

        ActivityLogger::log($user['id'], 'update_profile', $user['id'], 'user');

        Response::success(['updated' => true]);
    }

    /* -------------------------
       UPLOAD AVATAR
    -------------------------- */
    public static function uploadAvatar() {
        global $pdo;
        $user = AuthMiddleware::requireAuth();

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            Response::error("Avatar file is required", 400);
            return;
        }

        // Get old avatar
        $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $oldAvatar = $stmt->fetchColumn();

        $file = $_FILES['avatar'];

        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed, true)) {
            Response::error("Invalid file type", 400);
            return;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            Response::error("File too large", 400);
            return;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;

        $uploadDir = __DIR__ . '/../public/avatars';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $path = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            Response::error("Failed to save file", 500);
            return;
        }

        $avatarUrl = '/app/public/avatars/' . $filename;

        // Save the same URL to both avatar columns for old and new clients.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->execute([$avatarUrl, $user['id']]);
            $stmt = $pdo->prepare(
                "UPDATE profiles SET avatar_url = ?, updated_at = NOW() WHERE user_id = ?"
            );
            $stmt->execute([$avatarUrl, $user['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            @unlink($path);
            Response::error("Failed to save avatar", 500);
            return;
        }

        // Remove the previous managed file only after the new database values
        // have committed successfully.
        if ($oldAvatar && strpos($oldAvatar, '/app/public/avatars/') === 0) {
            $oldPath = __DIR__ . '/../public/avatars/' . basename($oldAvatar);
            if (is_file($oldPath)) @unlink($oldPath);
        }

        ActivityLogger::log($user['id'], 'update_avatar', $user['id'], 'user');

        Response::success(['avatar_url' => $avatarUrl]);
    }
}
