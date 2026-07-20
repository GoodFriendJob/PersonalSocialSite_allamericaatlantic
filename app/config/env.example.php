<?php
/**
 * Configuration template.
 *
 * Copy this file to env.php and fill in real values:
 *     cp app/config/env.example.php app/config/env.php
 *
 * env.php is gitignored and must never be committed.
 */

return [
    'db' => [
        'host'    => 'allamericaatlanticco.mydomaincommysql.com',
        'name'    => 'all_america_atlantic',
        'user'    => 'charlesf426',
        'pass'    => 'Fletch426$',
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
        'migration_token'            => 'test',
    ],
];
