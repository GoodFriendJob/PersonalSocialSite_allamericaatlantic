-- ---------------------------------------------------------------------------
-- Migration 001 — forum support + data integrity
--
-- Run once, against the all_america_atlantic database:
--   mysql -u root all_america_atlantic < migrations/001_forum_and_integrity.sql
--
-- Written for MySQL 5.7 compatibility (production), so it deliberately avoids
-- MariaDB-only syntax such as ADD COLUMN IF NOT EXISTS. Re-running it will
-- error on the ALTERs — that is expected, it is not idempotent.
-- ---------------------------------------------------------------------------

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1. Post visibility (public / private)
--    Spec: "Posts can be set as Public or Private (private posts are visible
--    only to the owner)." Existing posts stay public.
-- ---------------------------------------------------------------------------
ALTER TABLE `posts`
  ADD COLUMN `visibility` ENUM('public','private') NOT NULL DEFAULT 'public' AFTER `content`,
  ADD KEY `visibility` (`visibility`);

-- ---------------------------------------------------------------------------
-- 2. Sharing
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `post_shares` (
  `id`         INT(11)   NOT NULL AUTO_INCREMENT,
  `post_id`    INT(11)   NOT NULL,
  `user_id`    INT(11)   NOT NULL,
  `comment`    VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME  DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `post_shares_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- 3. De-duplicate engagement tables, then add the constraints that should
--    have prevented the duplicates.
--
--    post_likes currently contains genuine duplicates (e.g. user 2 liked
--    post 1 twice), which inflates like counts and makes unlike unreliable.
--    Keep the earliest row for each pair.
-- ---------------------------------------------------------------------------

DELETE FROM activity_log  WHERE user_id = 3;
DELETE FROM post_likes    WHERE user_id = 3;
DELETE FROM comments      WHERE post_id IN (SELECT id FROM posts WHERE user_id = 3);
DELETE FROM post_likes    WHERE post_id IN (SELECT id FROM posts WHERE user_id = 3);
DELETE FROM posts         WHERE user_id = 3;
DELETE FROM profiles      WHERE user_id = 3;
DELETE FROM user_settings WHERE user_id = 3;



DELETE `a` FROM `post_likes` `a`
  JOIN `post_likes` `b`
    ON `a`.`post_id` = `b`.`post_id`
   AND `a`.`user_id` = `b`.`user_id`
   AND `a`.`id`      > `b`.`id`;

ALTER TABLE `post_likes`
  ADD UNIQUE KEY `uniq_post_like` (`post_id`, `user_id`);

DELETE `a` FROM `comment_likes` `a`
  JOIN `comment_likes` `b`
    ON `a`.`comment_id` = `b`.`comment_id`
   AND `a`.`user_id`    = `b`.`user_id`
   AND `a`.`id`         > `b`.`id`;

ALTER TABLE `comment_likes`
  ADD UNIQUE KEY `uniq_comment_like` (`comment_id`, `user_id`);

DELETE `a` FROM `saved_posts` `a`
  JOIN `saved_posts` `b`
    ON `a`.`post_id` = `b`.`post_id`
   AND `a`.`user_id` = `b`.`user_id`
   AND `a`.`id`      > `b`.`id`;

ALTER TABLE `saved_posts`
  ADD UNIQUE KEY `uniq_saved_post` (`user_id`, `post_id`);

DELETE `a` FROM `followers` `a`
  JOIN `followers` `b`
    ON `a`.`follower_id`  = `b`.`follower_id`
   AND `a`.`following_id` = `b`.`following_id`
   AND `a`.`id`           > `b`.`id`;

ALTER TABLE `followers`
  ADD UNIQUE KEY `uniq_follow` (`follower_id`, `following_id`);

-- ---------------------------------------------------------------------------
-- 4. Friend requests
--    `status` existed but nothing ever wrote it and it had no default.
-- ---------------------------------------------------------------------------
UPDATE `friends` SET `status` = 'accepted' WHERE `status` IS NULL OR `status` = '';

ALTER TABLE `friends`
  MODIFY COLUMN `status` ENUM('pending','accepted','blocked') NOT NULL DEFAULT 'pending',
  ADD UNIQUE KEY `uniq_friendship` (`user_id`, `friend_id`);

-- ---------------------------------------------------------------------------
-- 5. Admin roles
--    AuthMiddleware LEFT JOINs this table to resolve a role. Without a unique
--    key, two rows for one user would duplicate that user's row in the join.
-- ---------------------------------------------------------------------------
DELETE `a` FROM `admin_roles` `a`
  JOIN `admin_roles` `b`
    ON `a`.`user_id` = `b`.`user_id`
   AND `a`.`id`      > `b`.`id`;

ALTER TABLE `admin_roles`
  MODIFY COLUMN `role` ENUM('admin','moderator') NOT NULL DEFAULT 'moderator',
  ADD UNIQUE KEY `uniq_admin_user` (`user_id`);

-- ---------------------------------------------------------------------------
-- 6. Categories: 'Tech' was duplicated as id 3 and id 10. Nothing references
--    id 10, so it is safe to remove. Adds a unique name to stop it recurring.
-- ---------------------------------------------------------------------------
DELETE FROM `categories` WHERE `id` = 10 AND `name` = 'Tech';

ALTER TABLE `categories`
  ADD UNIQUE KEY `uniq_category_name` (`name`);

COMMIT;
