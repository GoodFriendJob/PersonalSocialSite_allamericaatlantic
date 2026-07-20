<?php
/**
 * Loads app/config/env.php once and exposes it via dot-notation lookup.
 *
 *     config('db.host')
 *     config('app.debug', false)
 */

function config(?string $key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/env.php';

        if (!is_file($file)) {
            error_log('Missing app/config/env.php');
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Server is not configured. Copy app/config/env.example.php to app/config/env.php.'
            ]);
            exit;
        }

        $config = require $file;
    }

    if ($key === null) {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}
