-- Presence tracking for the Network Friends sidebar.
--
-- Adds the timestamp AuthMiddleware stamps on each authenticated request and
-- that Presence::sqlIsOnline() reads back to decide who is "Active Now".
--
-- Re-running is safe: migrate.php treats "duplicate column" and "duplicate key"
-- as already-in-place rather than as failures.
--
-- Until this runs, the sidebar still lists friends and requests, and every
-- friend simply renders as Offline (see app/core/Presence.php).

ALTER TABLE `users`
    ADD COLUMN `last_activity` DATETIME NULL DEFAULT NULL;

ALTER TABLE `users`
    ADD INDEX `idx_users_last_activity` (`last_activity`);
