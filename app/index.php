<?php
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-error-log.txt');
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function (Throwable $error) {
    error_log('Unhandled API error: ' . $error->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'The server could not complete this request']);
});

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/core/Pagination.php';
require_once __DIR__ . '/core/ActivityLogger.php';

$router = new Router();
require_once __DIR__ . '/routes/api.php';
$router->run();
