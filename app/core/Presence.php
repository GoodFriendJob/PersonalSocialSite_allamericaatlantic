<?php
/**
 * "Active now" for the Network Friends sidebar.
 *
 * There is no presence column in the shipped schema, so the old
 * app/routes/get_friends.php hardcoded is_online = false and every friend
 * rendered as Offline. Presence is a single `users.last_activity` timestamp,
 * stamped on each authenticated API request (see AuthMiddleware) plus a
 * client heartbeat, and read back as "touched within WINDOW_MINUTES".
 *
 * Run migrations/2026_07_20_000001_add_user_last_activity.sql to add the
 * column — via migrate.php on hosts with FTP-only access. Until that runs,
 * hasColumn() is false and everything here degrades to the old behaviour
 * rather than throwing on a missing column.
 */

class Presence
{
    /** A friend counts as online if they were active this recently. */
    const WINDOW_MINUTES = 5;

    /** Cached per request — the sidebar reads presence several times. */
    private static $hasColumn = null;

    public static function hasColumn(): bool
    {
        global $pdo;

        if (self::$hasColumn !== null) {
            return self::$hasColumn;
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_activity'");
            self::$hasColumn = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $e) {
            error_log('Presence column check failed: ' . $e->getMessage());
            self::$hasColumn = false;
        }

        return self::$hasColumn;
    }

    /**
     * Record that a user is active right now. Never fatal: presence is a
     * nice-to-have, so a failed write must not take down the request that
     * was actually asked for.
     */
    public static function touch(int $userId): void
    {
        global $pdo;

        if ($userId <= 0 || !self::hasColumn()) {
            return;
        }

        try {
            $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")
                ->execute([$userId]);
        } catch (PDOException $e) {
            error_log('Presence touch failed: ' . $e->getMessage());
        }
    }

    /**
     * SQL expression yielding 1/0 for a joined user alias, so the friends
     * query can compute presence in the same round trip.
     */
    public static function sqlIsOnline(string $alias = 'u'): string
    {
        if (!self::hasColumn()) {
            return '0';
        }

        return "($alias.last_activity IS NOT NULL"
             . " AND $alias.last_activity >= DATE_SUB(NOW(), INTERVAL " . self::WINDOW_MINUTES . " MINUTE))";
    }
}
