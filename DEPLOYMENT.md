# Deploying to the live server (SFTP only)

Follow these in order. Steps 2, 4 and 5 are the ones that silently break the
site if skipped.

---

## 0. Back up the database first

No phpMyAdmin and no shell, so use the bundled `backup.php`:

1. Set `migration_token` in `app/config/env.php` (step 2 below)
2. Visit `https://allamericaatlantic.com/backup.php?token=YOUR_TOKEN`
3. It writes a timestamped `.sql` into `backups/` — **download it over SFTP**
4. Delete `backup.php` and the `backups/` folder from the server when done

A backup sitting on a public server is a full copy of your database. Do not
leave it there.

> **This matters more than usual: your phpMyAdmin-style export does not restore.**
> User id 3 is missing from the `users` table, but 7 rows across `activity_log`,
> `posts`, `post_likes`, `profiles` and `user_settings` still reference it. A
> restore therefore fails when it reaches the foreign-key step, and you end up
> with a database containing **zero** foreign keys.
>
> Tested side by side on a copy of your live data:
>
> | Dump | Restore | Foreign keys |
> |---|---|---|
> | phpMyAdmin export | fails at line 1084 | 0 |
> | `backup.php` | succeeds | 39 |
>
> `backup.php` wraps the dump in `SET FOREIGN_KEY_CHECKS=0`, so it restores
> despite the orphan. Fixing the underlying data is still worth doing:
>

> Fix it once, on the live database, and every backup after that is sound:
>
> ```sql
> -- Option A: recreate the missing user (keeps the old posts/likes attached)
> INSERT INTO users (id, username, email, password, created_at)
> VALUES (3, 'maria', 'maria@example.com', '!', NOW());
>
> -- Option B: delete the orphaned rows instead
> DELETE FROM activity_log  WHERE user_id = 3;
> DELETE FROM post_likes    WHERE user_id = 3;
> DELETE FROM comments      WHERE post_id IN (SELECT id FROM posts WHERE user_id = 3);
> DELETE FROM post_likes    WHERE post_id IN (SELECT id FROM posts WHERE user_id = 3);
> DELETE FROM posts         WHERE user_id = 3;
> DELETE FROM profiles      WHERE user_id = 3;
> DELETE FROM user_settings WHERE user_id = 3;
> ```
>
> Option A is safer — it keeps posts 3 and 6 and their likes.

---

## 1. Rotate the leaked credentials

These are in public git history and must be changed before going live:

- the database password
- the SMTP password for `admin@allamericaatlantic.com`

Use the new values in step 2.

---

## 2. Create `app/config/env.php` on the server

**This file is gitignored and will not arrive with your upload — create it by hand.**
Without it every page returns a 500 with "Server is not configured".

Copy `app/config/env.example.php` to `app/config/env.php` and fill in:

```php
return [
    'db' => [
        'host'    => 'allamericaatlanticco.mydomaincommysql.com',
        'name'    => 'all_america_atlantic',
        'user'    => 'charlesf426',
        'pass'    => 'YOUR_NEW_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host'      => 'netsol-smtp-oxcs.hostingplatform.com',
        'port'      => 587,
        'username'  => 'admin@allamericaatlantic.com',
        'password'  => 'YOUR_NEW_SMTP_PASSWORD',
        'from_addr' => 'admin@allamericaatlantic.com',
        'from_name' => 'All America Atlantic',
    ],
    'app' => [
        'url'                        => 'https://allamericaatlantic.com',
        'debug'                      => false,   // MUST be false live
        'require_email_verification' => true,    // MUST be true live
        'migration_token'            => 'paste-a-long-random-string-here',
    ],
];
```

`debug => false` matters: with it on, PHP error text (including SQL and file
paths) is returned to the browser.

---

## 3. Upload the files

Upload everything **except**:

```
.git/            .claude/         app/config/env.php  (already created in step 2)
debug.txt        debug_log.txt    mail_debug.txt
app/php-error-log.txt             app/routes/error.log
```

---

## 4. Move the existing media files

Migration 002 rewrites stored media URLs from `/uploads/stories/…` to
`uploads/stories/…`, relative to the site root. The files themselves must move
to match, or every existing story image 404s.

On the server, move the **contents** of:

```
app/public/uploads/stories/   ->   uploads/stories/
```

(25 files. Copy rather than move if you want a fallback.)

---

## 5. Create the upload directories

Create these if they don't exist, and make sure PHP can write to them
(permissions `755`, or `775` if your host runs PHP as a separate user):

```
uploads/stories/
uploads/posts/
uploads/videos/
uploads/avatars/
uploads/profile_pics/
```

New uploads fail silently without these.

---

## 6. Run the migrations

There are four, and they must run in filename order. With no phpMyAdmin and no
shell, use the bundled runner:

1. Make sure `migration_token` is set in `env.php` (step 2)
2. Visit `https://allamericaatlantic.com/migrate.php?token=YOUR_TOKEN`
3. It applies anything outstanding, in filename order, and records each file in
   a `schema_migrations` table — so re-running is safe, applied files are skipped
4. **Delete `migrate.php` from the server when it reports success**

Tested against a restored copy of your production data: all four applied
(16, 8, 3 and 2 statements), a second run skipped everything, a wrong token
returned 403, and the 39 existing foreign keys survived (42 afterwards, with
the 3 new ones).

### Running any other SQL from the browser

The runner executes **any** `.sql` file in `migrations/`, in filename order.
To run a one-off script — a data fix, or importing a dump — name it so it sorts
last (e.g. `005_my_fix.sql`), upload it to `migrations/`, and reload the runner.
It records what it ran, so nothing executes twice.

It tolerates full database exports: `CREATE DATABASE` and `USE` lines are
skipped (your connection already has a database selected, and the account may
not be allowed to create one), block comments are stripped, and `/*! ... */`
conditional syntax is passed through to MySQL.

If a statement fails, the runner stops there, shows the failing SQL, and does
**not** mark that file as applied — so you can fix it and re-run.

---

## 7. After the migrations

Verify quickly:

- Log in
- The feed loads and the category tabs filter posts
- Post something with a photo, then comment / like / save / share it
- Existing story images still load (this confirms step 4 worked)

---

## Known server-side limitations

**Image thumbnails need the GD extension.** If GD is missing, uploads still
work — the thumbnail is a full-size copy instead of a resized one. Check via
phpinfo or ask your host to enable `gd`.

**Video poster frames need ffmpeg,** which shared hosting usually lacks. Videos
upload and play fine; they just have no poster image.

Neither of these blocks anything — they were previously fatal errors that lost
the entire upload, and now degrade quietly.

---

## Rolling back

Migrations 001, 003 and 004 change the schema and are not reversible by
re-running them. To roll back, restore the backup from step 0 — which is why
fixing the orphaned user id 3 first matters.
