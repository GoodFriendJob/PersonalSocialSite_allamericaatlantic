-- ---------------------------------------------------------------------------
-- Migration 003 — align the messaging tables with the messaging code
--
--   mysql -u root all_america_atlantic < migrations/003_messaging_schema.sql
--
-- MessageController reads and writes messages.thread_id, and ThreadController
-- selects message_threads.created_at. Neither column existed, so every
-- messaging endpoint failed with "Unknown column".
--
-- The messages table is empty, so thread_id needs no backfill. It is left
-- nullable so any stray legacy row would survive the change.
-- ---------------------------------------------------------------------------

START TRANSACTION;

ALTER TABLE `messages`
  ADD COLUMN `thread_id` INT(11) DEFAULT NULL AFTER `id`,
  ADD KEY `thread_id` (`thread_id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`id`) ON DELETE CASCADE;

ALTER TABLE `message_threads`
  ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `user2_id`;

-- Give existing threads a created_at rather than leaving them NULL.
UPDATE `message_threads`
   SET `created_at` = COALESCE(`last_message_at`, NOW())
 WHERE `created_at` IS NULL;

COMMIT;
