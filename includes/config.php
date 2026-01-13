<?php
/**
 * Application Configuration
 * Loads environment variables and sets application constants
 */

// Load environment variables from .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos(trim($line), '=') === false) continue; // Skip invalid lines
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Application constants
define('UPLOADS_DIR', getenv('UPLOADS_DIR') ?: ($_ENV['UPLOADS_DIR'] ?? 'uploads'));
define('BASE_URL', getenv('BASE_URL') ?: ($_ENV['BASE_URL'] ?? 'http://127.0.0.1:8000'));
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Ensure uploads directory exists
$uploadsPath = __DIR__ . '/../' . UPLOADS_DIR;
if (!is_dir($uploadsPath)) {
    mkdir($uploadsPath, 0755, true);
}
