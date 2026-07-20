-- ---------------------------------------------------------------------------
-- Migration 004 — seed the sport categories used by the feed tabs
--
--   mysql -u root all_america_atlantic < migrations/004_sport_categories.sql
--
-- app.php hardcoded a row of sport tabs (Football, Basketball, ...) that were
-- not backed by anything, so clicking one did nothing. They now exist as real
-- categories and posts can be filed under them.
--
-- INSERT IGNORE relies on the unique key on categories.name from migration
-- 001, so re-running this is a no-op.
-- ---------------------------------------------------------------------------

START TRANSACTION;

INSERT IGNORE INTO `categories` (`name`, `description`) VALUES
  ('Football',      'Football highlights, training and results'),
  ('Basketball',    'Basketball highlights, training and results'),
  ('Baseball',      'Baseball highlights, training and results'),
  ('Soccer',        'Soccer highlights, training and results'),
  ('Hockey',        'Hockey highlights, training and results'),
  ('Tennis',        'Tennis highlights, training and results'),
  ('Golf',          'Golf highlights, training and results'),
  ('Track & Field', 'Track and field events, times and personal bests'),
  ('Wrestling',     'Wrestling matches, technique and results'),
  ('Volleyball',    'Volleyball highlights, training and results'),
  ('Table Tennis',  'Table tennis matches and technique');

-- Speeds up the category-filtered feed query.
ALTER TABLE `posts` ADD KEY `category_created` (`category_id`, `created_at`);

COMMIT;
