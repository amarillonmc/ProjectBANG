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
    'max_versions_per_build' => 200,
    'max_collections' => 30,
    'max_portraits' => 100,
    // Workshop budgets. Bundled examples continue to use 18 / 24.
    'rules' => [
        'characterBudget' => 18,
        'customBudget' => 24,
        'customCardBudget' => 12,
        // A puzzle limit of 0 means unlimited; this is the runtime safety ceiling.
        'unlimitedUses' => 98,
        'unlimitedBudgetWeight' => 2,
        // Resource limits, not two-skill / three-effect game design restrictions.
        'maxSkills' => 98,
        'maxEffects' => 98,
        'maxAmount' => 98,
        'maxHp' => 98,
        'maxActionsPerTurn' => 512,
        'maxResolutionSteps' => 4096,
        'maxTurns' => 300,
        'negativeBudgetFloor' => -12,
    ],
];
