<?php
/**
 * Analytics Database Connection
 * Returns PDO connection to justice_hammer_analytics database
 */

function getAnalyticsDbConnection() {
    static $analyticsPdo = null;
    
    if ($analyticsPdo !== null) {
        return $analyticsPdo;
    }
    
    // Load environment variables from .env file if it exists
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue; // Skip comments
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
    
    $host = getenv('ANALYTICS_DB_HOST') ?: ($_ENV['ANALYTICS_DB_HOST'] ?? '127.0.0.1');
    $port = getenv('ANALYTICS_DB_PORT') ?: ($_ENV['ANALYTICS_DB_PORT'] ?? '3306');
    $dbname = getenv('ANALYTICS_DB_NAME') ?: ($_ENV['ANALYTICS_DB_NAME'] ?? 'justice_hammer_analytics');
    $username = getenv('ANALYTICS_DB_USER') ?: ($_ENV['ANALYTICS_DB_USER'] ?? 'root');
    $password = getenv('ANALYTICS_DB_PASS') ?: ($_ENV['ANALYTICS_DB_PASS'] ?? '');
    
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    
    try {
        $analyticsPdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $analyticsPdo;
    } catch (PDOException $e) {
        $errorMsg = "Analytics database connection failed: " . $e->getMessage();
        error_log($errorMsg);
        if (php_sapi_name() === 'cli') {
            die($errorMsg . "\n");
        } else {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Analytics database connection failed']));
        }
    }
}

// For backward compatibility, also provide $analyticsPdo global
$analyticsPdo = getAnalyticsDbConnection();
