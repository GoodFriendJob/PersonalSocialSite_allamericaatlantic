<?php

class AdminController {

    /* -------------------------
       BAN USER (ADMIN ONLY)
    -------------------------- */
    public static function banUser($params) {
        global $pdo;
        AdminMiddleware::requireAdmin();

        $userId = (int)$params['id'];

        $stmt = $pdo->prepare("UPDATE users SET banned = 1 WHERE id = ?");
        $stmt->execute([$userId]);

        ActivityLogger::log(0, 'admin_ban_user', $userId, 'user');

        Response::success(['banned' => true]);
    }

    /* -------------------------
       UNBAN USER (ADMIN ONLY)
    -------------------------- */
    public static function unbanUser($params) {
        global $pdo;
        AdminMiddleware::requireAdmin();

        $userId = (int)$params['id'];

        $stmt = $pdo->prepare("UPDATE users SET banned = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        ActivityLogger::log(0, 'admin_unban_user', $userId, 'user');

        Response::success(['unbanned' => true]);
    }

    /* -------------------------
       DELETE POST (ADMIN + MOD)
    -------------------------- */
    public static function deletePost($params) {
        global $pdo;
        AdminMiddleware::requireAdminOrModerator();

        $postId = (int)$params['id'];

        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$postId]);

        ActivityLogger::log(0, 'admin_delete_post', $postId, 'post');

        Response::success(['deleted' => true]);
    }

    /* -------------------------
       DELETE COMMENT (ADMIN + MOD)
    -------------------------- */
    public static function deleteComment($params) {
        global $pdo;
        AdminMiddleware::requireAdminOrModerator();

        $commentId = (int)$params['id'];

        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);

        ActivityLogger::log(0, 'admin_delete_comment', $commentId, 'comment');

        Response::success(['deleted' => true]);
    }

    /* -------------------------
       VIEW REPORTS (ADMIN + MOD)
    -------------------------- */
    public static function reports() {
        global $pdo;
        AdminMiddleware::requireAdminOrModerator();

        list($page, $limit, $offset) = Pagination::getPageLimit();

        $stmt = $pdo->prepare(
            "SELECT r.*, u.username AS reporter
             FROM reports r
             JOIN users u ON u.id = r.user_id
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?"
        );

        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::success(['reports' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /* -------------------------
       RESOLVE REPORT (ADMIN + MOD)
    -------------------------- */
    public static function resolveReport($params) {
        global $pdo;
        AdminMiddleware::requireAdminOrModerator();

        $reportId = (int)$params['id'];

        $stmt = $pdo->prepare("UPDATE reports SET resolved = 1 WHERE id = ?");
        $stmt->execute([$reportId]);

        ActivityLogger::log(0, 'admin_resolve_report', $reportId, 'report');

        Response::success(['resolved' => true]);
    }
}
