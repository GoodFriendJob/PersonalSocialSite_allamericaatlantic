-- ---------------------------------------------------------------------------
-- Migration 006 — strip stale directory prefixes from stored media paths
--
-- Migration 002 normalised "/uploads/x" to "uploads/x", but it matched on
-- LIKE '/uploads/%' and so never touched rows written with the app/public
-- prefix. Those survive as e.g.
--
--   /app/public/uploads/stories/thumb_story_106_1784540226_46d056a5.jpg
--
-- Api.assetUrl() only strips leading slashes, so it hands that straight to the
-- browser and the thumbnail 404s — the uploads folder is at the project root,
-- not under app/public.
--
-- Rather than hard-coding "app/public", each statement re-anchors the value at
-- the first "uploads/" segment, which normalises any wrapper directory that
-- may have accumulated. The WHERE clauses:
--
--   LIKE '%uploads/%'      — only touch rows that have an uploads/ segment
--   NOT LIKE 'uploads/%'   — skip rows already correct, making this re-runnable
--   NOT LIKE 'http%'       — never rewrite an absolute URL to a remote host
--   NOT LIKE '//%'         — same, for protocol-relative URLs
-- ---------------------------------------------------------------------------

UPDATE `stories`
   SET `media_url` = SUBSTRING(`media_url`, LOCATE('uploads/', `media_url`))
 WHERE `media_url` LIKE '%uploads/%'
   AND `media_url` NOT LIKE 'uploads/%'
   AND `media_url` NOT LIKE 'http%'
   AND `media_url` NOT LIKE '//%';

UPDATE `stories`
   SET `thumbnail_url` = SUBSTRING(`thumbnail_url`, LOCATE('uploads/', `thumbnail_url`))
 WHERE `thumbnail_url` LIKE '%uploads/%'
   AND `thumbnail_url` NOT LIKE 'uploads/%'
   AND `thumbnail_url` NOT LIKE 'http%'
   AND `thumbnail_url` NOT LIKE '//%';

UPDATE `post_images`
   SET `image_url` = SUBSTRING(`image_url`, LOCATE('uploads/', `image_url`))
 WHERE `image_url` LIKE '%uploads/%'
   AND `image_url` NOT LIKE 'uploads/%'
   AND `image_url` NOT LIKE 'http%'
   AND `image_url` NOT LIKE '//%';

UPDATE `post_images`
   SET `thumbnail_url` = SUBSTRING(`thumbnail_url`, LOCATE('uploads/', `thumbnail_url`))
 WHERE `thumbnail_url` LIKE '%uploads/%'
   AND `thumbnail_url` NOT LIKE 'uploads/%'
   AND `thumbnail_url` NOT LIKE 'http%'
   AND `thumbnail_url` NOT LIKE '//%';

UPDATE `post_videos`
   SET `video_url` = SUBSTRING(`video_url`, LOCATE('uploads/', `video_url`))
 WHERE `video_url` LIKE '%uploads/%'
   AND `video_url` NOT LIKE 'uploads/%'
   AND `video_url` NOT LIKE 'http%'
   AND `video_url` NOT LIKE '//%';

UPDATE `post_videos`
   SET `thumbnail_url` = SUBSTRING(`thumbnail_url`, LOCATE('uploads/', `thumbnail_url`))
 WHERE `thumbnail_url` LIKE '%uploads/%'
   AND `thumbnail_url` NOT LIKE 'uploads/%'
   AND `thumbnail_url` NOT LIKE 'http%'
   AND `thumbnail_url` NOT LIKE '//%';

UPDATE `profiles`
   SET `avatar_url` = SUBSTRING(`avatar_url`, LOCATE('uploads/', `avatar_url`))
 WHERE `avatar_url` LIKE '%uploads/%'
   AND `avatar_url` NOT LIKE 'uploads/%'
   AND `avatar_url` NOT LIKE 'http%'
   AND `avatar_url` NOT LIKE '//%';

UPDATE `users`
   SET `profile_pic` = SUBSTRING(`profile_pic`, LOCATE('uploads/', `profile_pic`))
 WHERE `profile_pic` LIKE '%uploads/%'
   AND `profile_pic` NOT LIKE 'uploads/%'
   AND `profile_pic` NOT LIKE 'http%'
   AND `profile_pic` NOT LIKE '//%';
