<?php

class ActivityLogger {

    public static function log($userId, $action, $targetId = null, $targetType = null) {
        global $pdo;

        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (user_id, action, target_id, target_type, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );

        $stmt->execute([$userId, $action, $targetId, $targetType]);
    }
}
