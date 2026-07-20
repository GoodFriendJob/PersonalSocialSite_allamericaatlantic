<?php

class ReportController {

    /* -------------------------
       CREATE REPORT
    -------------------------- */
    public static function create($params) {
        global $pdo;

        $user = AuthMiddleware::requireAuth();

        // Safe JSON read
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            Response::error("Invalid JSON body", 400);
            return;
        }

        $targetId   = isset($data['target_id']) ? (int)$data['target_id'] : 0;
        $targetType = isset($data['target_type']) ? trim($data['target_type']) : null;
        $reason     = isset($data['reason']) ? trim($data['reason']) : null;

        // Validate target type
        $allowedTypes = ['post', 'comment', 'message'];
        if (!in_array($targetType, $allowedTypes, true)) {
            Response::error("Invalid target type", 400);
            return;
        }

        if ($targetId <= 0 || $reason === '') {
            Response::error("Missing fields", 400);
            return;
        }

        // Insert report
        $stmt = $pdo->prepare(
            "INSERT INTO reports (user_id, target_id, target_type, reason, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$user['id'], $targetId, $targetType, $reason]);

        ActivityLogger::log($user['id'], 'create_report', $targetId, $targetType);

        Response::success(['reported' => true]);
    }

    /* -------------------------
       GET MY REPORTS
    -------------------------- */
    public static function myReports() {
        global $pdo;

        $user = AuthMiddleware::requireAuth();

        $stmt = $pdo->prepare(
            "SELECT * FROM reports
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$user['id']]);

        Response::success(['reports' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
