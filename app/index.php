<?php
/**
 * API front controller. Every response from here is JSON.
 *
 * Nothing may echo before the router runs — stray output corrupts the JSON
 * body and makes response.json() throw on the client for every endpoint.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/Session.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/core/Media.php';
require_once __DIR__ . '/core/AdminMiddleware.php';
require_once __DIR__ . '/core/Pagination.php';
require_once __DIR__ . '/core/ActivityLogger.php';

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error-log.txt');
error_reporting(E_ALL);

// Errors are logged, never printed: a PHP notice in the body breaks the JSON.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

header('Content-Type: application/json; charset=utf-8');

Session::start();

// Turn a fatal into a JSON 500 instead of a blank page or an HTML error dump.
register_shutdown_function(function () {
    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'error'   => 'Internal server error',
            'details' => config('app.debug') ? $error['message'] : null,
        ]);
    }
});

$router = new Router();
require_once __DIR__ . '/routes/api.php';

try {
    $router->run();
} catch (Throwable $e) {
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'error'   => 'Internal server error',
        'details' => config('app.debug') ? $e->getMessage() : null,
    ]);
}
