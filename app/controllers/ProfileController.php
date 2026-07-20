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
                    p.bio, p.avatar_url
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

        // Update bio
        if ($bio !== null) {
            $stmt = $pdo->prepare(
                "UPDATE profiles SET bio = ?, updated_at = NOW()
                 WHERE user_id = ?"
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
        $stmt = $pdo->prepare("SELECT avatar_url FROM profiles WHERE user_id = ?");
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

        $avatarUrl = '/avatars/' . $filename;

        // Delete old avatar file if it exists
        if ($oldAvatar && file_exists(__DIR__ . '/../public' . $oldAvatar)) {
            unlink(__DIR__ . '/../public' . $oldAvatar);
        }

        // Save new avatar
        $stmt = $pdo->prepare(
            "UPDATE profiles SET avatar_url = ?, updated_at = NOW()
             WHERE user_id = ?"
        );
        $stmt->execute([$avatarUrl, $user['id']]);

        ActivityLogger::log($user['id'], 'update_avatar', $user['id'], 'user');

        Response::success(['avatar_url' => $avatarUrl]);
    }
}
