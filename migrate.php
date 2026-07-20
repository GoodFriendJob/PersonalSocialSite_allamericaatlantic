<?php
/**
 * One-time migration runner for hosts where you only have SFTP access.
 *
 * USAGE
 *   1. Set a migration_token in app/config/env.php (see app section).
 *   2. Upload this file and the migrations/ folder.
 *   3. Visit  https://your-site/migrate.php?token=YOUR_TOKEN
 *   4. DELETE THIS FILE when the run reports success.
 *
 * Applied migrations are recorded in a schema_migrations table, so re-running
 * is safe — already-applied files are skipped rather than re-executed.
 *
 * This file is a deployment tool, not part of the application. Leaving it on a
 * live server lets anyone with the token alter your database.
 */

require_once __DIR__ . '/app/config/db.php';

// Imports can take a while on shared hosting.
@set_time_limit(0);
@ini_set('memory_limit', '512M');

header('Content-Type: text/html; charset=utf-8');

$token    = config('app.migration_token');
$provided = $_GET['token'] ?? '';

/* ------------------------------------------------------------------ */
/* Gate                                                                */
/* ------------------------------------------------------------------ */
if (!$token) {
    fail(
        'No migration token configured.',
        'Add <code>\'migration_token\' => \'some-long-random-string\'</code> to the '
        . '<code>app</code> section of <code>app/config/env.php</code>, then reload this page with '
        . '<code>?token=that-string</code>.'
    );
}

if (!is_string($provided) || !hash_equals((string)$token, $provided)) {
    http_response_code(403);
    fail('Invalid or missing token.', 'Append <code>?token=YOUR_TOKEN</code> to the URL.');
}

/* ------------------------------------------------------------------ */
/* Ledger                                                              */
/* ------------------------------------------------------------------ */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `schema_migrations` (
      `filename`   VARCHAR(255) NOT NULL,
      `applied_at` DATETIME NOT NULL,
      PRIMARY KEY (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$applied = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files, SORT_STRING);

if (!$files) {
    fail('No migration files found.', 'Upload the <code>migrations/</code> folder next to this script.');
}

/* ------------------------------------------------------------------ */
/* Run                                                                 */
/* ------------------------------------------------------------------ */
echo '<!doctype html><meta charset="utf-8"><title>Database migrations</title>';
echo '<style>
  body{font:15px/1.55 system-ui,sans-serif;max-width:820px;margin:40px auto;padding:0 20px;color:#111}
  h1{font-size:22px} h2{font-size:16px;margin:26px 0 6px}
  .ok{color:#15803d}.skip{color:#6b7280}.warn{color:#b45309}.err{color:#b91c1c}
  pre{background:#f6f7f9;padding:10px 12px;border-radius:6px;overflow-x:auto;font-size:13px}
  .box{border:1px solid #e5e7eb;border-radius:8px;padding:14px 18px;margin-top:22px}
  code{background:#f1f2f4;padding:1px 5px;border-radius:4px}
</style>';
echo '<h1>Database migrations</h1>';

$ranAny = false;
$failed = false;

foreach ($files as $path) {
    $name = basename($path);

    if (isset($applied[$name])) {
        echo '<p class="skip">— ' . h($name) . ' &middot; already applied</p>';
        continue;
    }

    echo '<h2>' . h($name) . '</h2>';

    $statements = splitSql(file_get_contents($path));
    $okCount = 0;
    $warnings = [];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
            $okCount++;
        } catch (PDOException $e) {
            // These mean the change is already in place — treat as a warning so
            // a partially-applied migration can still be completed.
            $benign = ['42S01', '42S21']; // table exists, duplicate column
            $code   = $e->errorInfo[1] ?? 0;
            $benignCodes = [1050, 1060, 1061, 1091]; // table/column/key exists, can't drop

            if (in_array($e->getCode(), $benign, true) || in_array($code, $benignCodes, true)) {
                $warnings[] = $e->getMessage();
                continue;
            }

            $failed = true;
            echo '<p class="err"><strong>Failed.</strong> ' . h($e->getMessage()) . '</p>';
            echo '<pre>' . h(trim($sql)) . '</pre>';
            echo '<p class="err">Stopped. Nothing after this statement ran, and '
               . h($name) . ' was not marked as applied. Fix the cause and reload.</p>';
            break 2;
        }
    }

    foreach ($warnings as $w) {
        echo '<p class="warn">already in place &middot; ' . h($w) . '</p>';
    }

    $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename, applied_at) VALUES (?, NOW())");
    $stmt->execute([$name]);

    echo '<p class="ok">Applied &middot; ' . $okCount . ' statement(s) executed'
       . ($warnings ? ', ' . count($warnings) . ' already in place' : '') . '.</p>';

    $ranAny = true;
}

if (!$failed) {
    echo '<div class="box">';
    echo $ranAny
        ? '<p class="ok"><strong>All migrations applied.</strong></p>'
        : '<p class="skip"><strong>Nothing to do — the database is already up to date.</strong></p>';
    echo '<p class="err"><strong>Now delete this file (migrate.php) from the server.</strong> '
       . 'While it exists, anyone with the token can alter your database.</p>';
    echo '</div>';
}

/* ------------------------------------------------------------------ */

/**
 * Splits a script into statements on semicolons, ignoring those inside
 * quoted strings, and drops comment lines and transaction control.
 *
 * DDL causes an implicit commit in MySQL, so the START TRANSACTION/COMMIT in
 * the migration files cannot actually roll back an ALTER. They are stripped
 * here so the ledger, not the transaction, is what tracks progress.
 */
function splitSql(string $sql): array
{
    // Strip /* ... */ block comments, but keep /*! ... */ — MySQL executes those.
    $sql = preg_replace('#/\*(?!!).*?\*/#s', '', $sql);

    // Strip full-line -- and # comments.
    $lines = preg_split('/\R/', $sql);
    $lines = array_filter($lines, function ($line) {
        $t = ltrim($line);
        return $t !== '' && strpos($t, '--') !== 0 && strpos($t, '#') !== 0;
    });
    $sql = implode("\n", $lines);

    $statements = [];
    $current = '';
    $quote = null;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        if ($quote !== null) {
            $current .= $ch;
            if ($ch === '\\' && $i + 1 < $len) {      // escaped char inside a string
                $current .= $sql[++$i];
            } elseif ($ch === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $current .= $ch;
            continue;
        }

        if ($ch === ';') {
            $statements[] = $current;
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return array_values(array_filter(array_map('trim', $statements), function ($s) {
        if ($s === '') return false;

        // Transaction control: DDL implicitly commits in MySQL, so these can't
        // roll back an ALTER anyway. The ledger tracks progress instead.
        if (preg_match('/^(START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK)$/i', $s)) return false;

        // A phpMyAdmin export starts by creating and selecting a database.
        // The connection already has one selected, and the credentials may not
        // permit CREATE DATABASE, so drop those lines when importing a dump.
        if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $s)) return false;

        return true;
    }));
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fail(string $title, string $detail): void
{
    echo '<!doctype html><meta charset="utf-8">';
    echo '<body style="font:15px/1.55 system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 20px">';
    echo '<h1 style="font-size:20px;color:#b91c1c">' . h($title) . '</h1><p>' . $detail . '</p>';
    exit;
}
