-- ---------------------------------------------------------------------------
-- Migration 002 — normalize stored media URLs
--
--   mysql -u root all_america_atlantic < migrations/002_normalize_media_urls.sql
--
-- Every controller used to write a root-absolute URL ("/uploads/stories/x.jpg",
-- "/post_images/x.jpg", "/avatars/x.jpg") that assumed app/public was the web
-- root. It never was, so all of this media 404'd.
--
-- Storage is now <project root>/uploads/<kind>/ with a relative URL, matching
-- the convention users.profile_pic already used successfully. This rewrites the
-- existing rows to match. Safe to re-run — each statement is filtered by the
-- old prefix, so already-migrated rows are skipped.
-- ---------------------------------------------------------------------------

START TRANSACTION;

-- Stories: same folder, just drop the leading slash.
UPDATE `stories`
   SET `media_url` = TRIM(LEADING '/' FROM `media_url`)
 WHERE `media_url` LIKE '/uploads/%';

UPDATE `stories`
   SET `thumbnail_url` = TRIM(LEADING '/' FROM `thumbnail_url`)
 WHERE `thumbnail_url` LIKE '/uploads/%';

-- Post images: /post_images/x -> uploads/posts/x
UPDATE `post_images`
   SET `image_url` = CONCAT('uploads/posts/', SUBSTRING(`image_url`, LENGTH('/post_images/') + 1))
 WHERE `image_url` LIKE '/post_images/%';

UPDATE `post_images`
   SET `thumbnail_url` = CONCAT('uploads/posts/', SUBSTRING(`thumbnail_url`, LENGTH('/post_images/') + 1))
 WHERE `thumbnail_url` LIKE '/post_images/%';

-- Post videos: /post_videos/x -> uploads/videos/x
UPDATE `post_videos`
   SET `video_url` = CONCAT('uploads/videos/', SUBSTRING(`video_url`, LENGTH('/post_videos/') + 1))
 WHERE `video_url` LIKE '/post_videos/%';

UPDATE `post_videos`
   SET `thumbnail_url` = CONCAT('uploads/videos/', SUBSTRING(`thumbnail_url`, LENGTH('/post_videos/') + 1))
 WHERE `thumbnail_url` LIKE '/post_videos/%';

-- Avatars: /avatars/x -> uploads/avatars/x
UPDATE `profiles`
   SET `avatar_url` = CONCAT('uploads/avatars/', SUBSTRING(`avatar_url`, LENGTH('/avatars/') + 1))
 WHERE `avatar_url` LIKE '/avatars/%';

-- Any remaining leading slash on profile pictures.
UPDATE `users`
   SET `profile_pic` = TRIM(LEADING '/' FROM `profile_pic`)
 WHERE `profile_pic` LIKE '/%';

COMMIT;
