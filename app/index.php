<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error-log.txt');
error_reporting(E_ALL);

echo "INDEX START<br>";


echo "INDEX START<br>";


register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo "<pre>FATAL ERROR:\n";
        print_r($error);
        echo "</pre>";
    }
});

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/core/Pagination.php';
require_once __DIR__ . '/core/ActivityLogger.php';

// Create router instance
$router = new Router();

// Load all routes
require_once __DIR__ . '/routes/api.php';

echo "ROUTE=" . ($_GET['route'] ?? 'NONE') . "<br>";

// Run the router
$router->run();
