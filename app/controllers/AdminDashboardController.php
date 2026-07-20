<?php

class AdminDashboardController {

    /* -------------------------
       BASIC STATS
    -------------------------- */
    public static function stats() {
        global $pdo;
        AdminMiddleware::requireAdminOrModerator();

        $users       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $banned      = $pdo->query("SELECT COUNT(*) FROM users WHERE banned = 1")->fetchColumn();
        $posts       = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $comments    = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $openReports = $pdo->query("SELECT COUNT(*) FROM reports WHERE resolved = 0")->fetchColumn();

        Response::success([
            'users'        => (int)$users,
            'banned_users' => (int)$banned,
            'posts'        => (int)$posts,
            'comments'     => (int)$comments,
            'open_reports' => (int)$openReports,
        ]);
    }

    /* -------------------------
       RECENT ACTIVITY LOG
    -------------------------- */
    public static function recentActivity() {
        global $pdo;
        AdminMiddleware::requireAdminOrModerator();

        list($page, $limit, $offset) = Pagination::getPageLimit();

        $stmt = $pdo->prepare(
            "SELECT a.*, u.username
             FROM activity_log a
             JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?"
        );

        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        Response::success(['activity' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
