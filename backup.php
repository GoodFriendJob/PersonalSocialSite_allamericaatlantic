<?php
/**
 * Browser-triggered database backup, for hosts with no phpMyAdmin and no shell.
 *
 * USAGE
 *   1. Set migration_token in app/config/env.php.
 *   2. Visit  https://your-site/backup.php?token=YOUR_TOKEN
 *   3. Download the generated .sql from backups/ over SFTP.
 *   4. DELETE THIS FILE when finished.
 *
 * Writes a timestamped dump to backups/. Pure PHP — mysqldump and exec() are
 * usually unavailable on shared hosting.
 *
 * The dump is wrapped in SET FOREIGN_KEY_CHECKS=0, so unlike the phpMyAdmin
 * export it restores cleanly even though the data contains an orphaned
 * reference (user id 3 is referenced by 7 rows but missing from users).
 */

require_once __DIR__ . '/app/config/db.php';

@set_time_limit(0);
header('Content-Type: text/html; charset=utf-8');

$token    = config('app.migration_token');
$provided = $_GET['token'] ?? '';

if (!$token || !is_string($provided) || !hash_equals((string)$token, $provided)) {
    http_response_code(403);
    exit('<h1 style="font:20px system-ui;color:#b91c1c">Invalid or missing token.</h1>'
       . '<p style="font:15px system-ui">Set <code>migration_token</code> in app/config/env.php '
       . 'and call this page with <code>?token=YOUR_TOKEN</code>.</p>');
}

$dir = __DIR__ . '/backups';
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
    exit('<p style="font:15px system-ui;color:#b91c1c">Could not create the backups/ directory. '
       . 'Create it over SFTP and make it writable.</p>');
}

$dbName = config('db.name');
$stamp  = gmdate('Ymd_His');
$file   = $dir . "/backup_{$dbName}_{$stamp}.sql";

$out = fopen($file, 'w');
if (!$out) {
    exit('<p style="font:15px system-ui;color:#b91c1c">Could not write to backups/. Check permissions.</p>');
}

fwrite($out, "-- Backup of `{$dbName}`\n");
fwrite($out, "-- Generated " . gmdate('Y-m-d H:i:s') . " UTC by backup.php\n");
fwrite($out, "-- Restore with migrate.php or any SQL client.\n\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
fwrite($out, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$totalRows = 0;

foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];

    fwrite($out, "\n-- ---------------------------------------------------------\n");
    fwrite($out, "-- Table `{$table}`\n");
    fwrite($out, "-- ---------------------------------------------------------\n");
    fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
    fwrite($out, $create . ";\n\n");

    // Stream rows so memory stays flat regardless of table size.
    $rows = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_ASSOC);
    $count = 0;
    $batch = [];

    foreach ($rows as $row) {
        $values = array_map(function ($v) use ($pdo) {
            return $v === null ? 'NULL' : $pdo->quote((string)$v);
        }, $row);

        $batch[] = '(' . implode(',', $values) . ')';
        $count++;

        if (count($batch) >= 100) {
            fwrite($out, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        }
    }

    if ($batch) {
        fwrite($out, "INSERT INTO `{$table}` VALUES\n" . implode(",\n", $batch) . ";\n");
    }

    $totalRows += $count;
    $summary[] = ['table' => $table, 'rows' => $count];
}

fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($out);

$size = filesize($file);

echo '<!doctype html><meta charset="utf-8"><title>Database backup</title>';
echo '<style>body{font:15px/1.6 system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 20px}
table{border-collapse:collapse;margin:14px 0}td,th{padding:3px 14px 3px 0;text-align:left;font-size:13px}
code{background:#f1f2f4;padding:1px 5px;border-radius:4px}.ok{color:#15803d}.err{color:#b91c1c}</style>';
echo '<h1 style="font-size:22px">Database backup</h1>';
echo '<p class="ok"><strong>Written.</strong></p>';
echo '<p><code>' . htmlspecialchars(str_replace(__DIR__, '', $file)) . '</code><br>'
   . number_format($size) . ' bytes &middot; ' . count($tables) . ' tables &middot; '
   . number_format($totalRows) . ' rows</p>';

echo '<table><tr><th>Table</th><th>Rows</th></tr>';
foreach ($summary as $s) {
    echo '<tr><td>' . htmlspecialchars($s['table']) . '</td><td>' . number_format($s['rows']) . '</td></tr>';
}
echo '</table>';

echo '<p><strong>Download it over SFTP now</strong>, then delete both this file and '
   . '<code>migrate.php</code> from the server.</p>';
echo '<p class="err">A backup left on a public server is a copy of your entire database. '
   . 'Do not leave <code>backups/</code> in place.</p>';
