<?php
/**
 * Primary Database Connection (OLTP)
 * Returns PDO connection to justice_hammer database
 */

function getDbConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
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
    
    $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
    $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
    $dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'justice_hammer');
    $username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
    $password = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');
    
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        $errorMsg = "Database connection failed: " . $e->getMessage();
        error_log($errorMsg);
        if (php_sapi_name() === 'cli') {
            die($errorMsg . "\n");
        } else {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Database connection failed']));
        }
    }
}

// For backward compatibility, also provide $pdo global
$pdo = getDbConnection();