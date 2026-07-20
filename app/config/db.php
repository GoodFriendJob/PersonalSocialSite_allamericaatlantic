<?php
/**
 * Builds the shared $pdo connection from app/config/env.php.
 */

require_once __DIR__ . '/config.php';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    config('db.host'),
    config('db.name'),
    config('db.charset', 'utf8mb4')
);

try {
    $pdo = new PDO($dsn, config('db.user'), config('db.pass'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Log the detail, return a generic message. The old version echoed
    // getMessage() straight to the client, which leaked the db host and user.
    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
