-- ---------------------------------------------------------------------------
-- Migration 007 — remap the pre-Media.php folder names
--
-- Migration 002 mapped "/post_images/x" -> "uploads/posts/x", and 006
-- re-anchored anything containing an "uploads/" segment. Rows written with the
-- app/public prefix AND the old folder names fall through both, because they
-- contain no "uploads/" segment for 006 to anchor on:
--
--   /app/public/post_images/post_18_a0ecfb7de1.jpg
--
-- 002's WHERE was LIKE '/post_images/%', which the app/public prefix defeats.
--
-- These statements key off the legacy folder name rather than the prefix, and
-- rebuild the value from the filename alone (SUBSTRING_INDEX(..., '/', -1) is
-- everything after the last slash). That is prefix-agnostic, so it fixes the
-- app/public form and any other wrapper directory in one pass.
--
-- Canonical destinations come from Media::url(): post images -> uploads/posts,
-- videos -> uploads/videos, avatars -> uploads/avatars.
--
-- Re-runnable: once a row is 'uploads/posts/x' it no longer matches
-- '%post_images/%', so a second run is a no-op. http/protocol-relative URLs
-- are excluded so remote media is never rewritten.
-- ---------------------------------------------------------------------------

UPDATE `post_images`
   SET `image_url` = CONCAT('uploads/posts/', SUBSTRING_INDEX(`image_url`, '/', -1))
 WHERE `image_url` LIKE '%post_images/%'
   AND `image_url` NOT LIKE 'http%'
   AND `image_url` NOT LIKE '//%';

UPDATE `post_images`
   SET `thumbnail_url` = CONCAT('uploads/posts/', SUBSTRING_INDEX(`thumbnail_url`, '/', -1))
 WHERE `thumbnail_url` LIKE '%post_images/%'
   AND `thumbnail_url` NOT LIKE 'http%'
   AND `thumbnail_url` NOT LIKE '//%';

UPDATE `post_videos`
   SET `video_url` = CONCAT('uploads/videos/', SUBSTRING_INDEX(`video_url`, '/', -1))
 WHERE `video_url` LIKE '%post_videos/%'
   AND `video_url` NOT LIKE 'http%'
   AND `video_url` NOT LIKE '//%';

UPDATE `post_videos`
   SET `thumbnail_url` = CONCAT('uploads/videos/', SUBSTRING_INDEX(`thumbnail_url`, '/', -1))
 WHERE `thumbnail_url` LIKE '%post_videos/%'
   AND `thumbnail_url` NOT LIKE 'http%'
   AND `thumbnail_url` NOT LIKE '//%';

UPDATE `profiles`
   SET `avatar_url` = CONCAT('uploads/avatars/', SUBSTRING_INDEX(`avatar_url`, '/', -1))
 WHERE `avatar_url` LIKE '%avatars/%'
   AND `avatar_url` NOT LIKE 'uploads/avatars/%'
   AND `avatar_url` NOT LIKE 'http%'
   AND `avatar_url` NOT LIKE '//%';

UPDATE `users`
   SET `profile_pic` = CONCAT('uploads/avatars/', SUBSTRING_INDEX(`profile_pic`, '/', -1))
 WHERE `profile_pic` LIKE '%avatars/%'
   AND `profile_pic` NOT LIKE 'uploads/avatars/%'
   AND `profile_pic` NOT LIKE 'http%'
   AND `profile_pic` NOT LIKE '//%';

-- Catch-all: anything still carrying an app/public prefix that the folder-name
-- rules above did not cover. Re-anchors at the first uploads/ segment.
UPDATE `stories`
   SET `media_url` = SUBSTRING(`media_url`, LOCATE('uploads/', `media_url`))
 WHERE `media_url` LIKE '%app/public/%uploads/%'
   AND `media_url` NOT LIKE 'http%';

UPDATE `stories`
   SET `thumbnail_url` = SUBSTRING(`thumbnail_url`, LOCATE('uploads/', `thumbnail_url`))
 WHERE `thumbnail_url` LIKE '%app/public/%uploads/%'
   AND `thumbnail_url` NOT LIKE 'http%';
