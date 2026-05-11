<?php
/**
 * Database Configuration
 */

return [
    'host' => getenv('DB_HOST', 'localhost'),
    'port' => getenv('DB_PORT', 3306),
    'user' => getenv('DB_USER', 'root'),
    'pass' => getenv('DB_PASS', ''),
    'name' => getenv('DB_NAME', 'intermica_db'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
