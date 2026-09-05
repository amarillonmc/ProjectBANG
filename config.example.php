<?php
/** Copy to config.php (one directory ABOVE public/). No Composer required. */
return [
    'database' => [
        // 'sqlite': enable pdo_sqlite, give only PHP write access to var/.
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/var/game.sqlite',
        'prefix' => 'im_',

        // For a shared host with MySQL: driver => mysql, enable pdo_mysql.
        // Create the database in the hosting control panel first. Tables initialize automatically.
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'your_database',
        'username' => 'your_database_user',
        'password' => 'replace_this_password',
    ],
    // Keep false for public testing. Errors are written to PHP's server error log.
    'debug' => false,
    'max_builds' => 30,
    'max_rooms_per_host' => 10,
];
