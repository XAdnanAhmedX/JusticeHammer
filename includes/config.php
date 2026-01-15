<?php
/**
 * Simple PHP configuration fallback for XAMPP / local development.
 * If you prefer .env, keep .env in project root. This file is an alternative
 * that is easier to edit when running under XAMPP.
 *
 * Return an associative array $config.
 */

return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'justice_hammer',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'ANALYTICS_DB_HOST' => '127.0.0.1',
    'ANALYTICS_DB_PORT' => 3306,
    'ANALYTICS_DB_NAME' => 'justice_hammer_analytics',
    'ANALYTICS_DB_USER' => 'root',
    'ANALYTICS_DB_PASS' => '',
    'UPLOADS_DIR' => 'uploads',
    'MAX_FILE_SIZE' => 10485760, // 10MB in bytes
    'BASE_URL' => 'http://127.0.0.1/JusticeHammerDBMS_corrected',
];
