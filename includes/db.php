<?php
/**
 * Primary Database Connection (OLTP)
 * Returns PDO connection to justice_hammer database
 */

function loadEnvFile($envFile) {
    $vars = [];
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // remove surrounding quotes
        if ((substr($val,0,1) === '"' && substr($val,-1) === '"') || (substr($val,0,1) === "'" && substr($val,-1) === "'")) {
            $val = substr($val,1,-1);
        }
        $vars[$key] = $val;
    }
    return $vars;
}

function getDbConnection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // default config (fallback)
    $config = [];
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $config = loadEnvFile($envFile);
    } else {
        // fallback to includes/config.php if present
        $cfgFile = __DIR__ . '/config.php';
        if (file_exists($cfgFile)) {
            $cfg = include $cfgFile;
            if (is_array($cfg)) {
                $config = $cfg;
            }
        }
    }

    // set defaults if missing
    $host = isset($config['DB_HOST']) ? $config['DB_HOST'] : '127.0.0.1';
    $port = isset($config['DB_PORT']) ? $config['DB_PORT'] : 3306;
    $dbname = isset($config['DB_NAME']) ? $config['DB_NAME'] : 'justice_hammer';
    $user = isset($config['DB_USER']) ? $config['DB_USER'] : 'root';
    $pass = isset($config['DB_PASS']) ? $config['DB_PASS'] : '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
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
            echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
            exit;
        }
    }
}
