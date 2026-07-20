<?php

$dsn = 'mysql:host=allamericaatlanticco.mydomaincommysql.com;dbname=all_america_atlantic';
$user = 'charlesf426';
$pass = 'Fletch426$';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
}
