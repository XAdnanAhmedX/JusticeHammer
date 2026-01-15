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

    // default config (fallback)
    $config = [];
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        // reuse the loader from db.php if available
        if (!function_exists('loadEnvFile')) {
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
                    if ((substr($val,0,1) === '"' && substr($val,-1) === '"') || (substr($val,0,1) === "'" && substr($val,-1) === "'")) {
                        $val = substr($val,1,-1);
                    }
                    $vars[$key] = $val;
                }
                return $vars;
            }
        }
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

    // analytics-specific keys may be prefixed; try both
    $host = isset($config['ANALYTICS_DB_HOST']) ? $config['ANALYTICS_DB_HOST'] : (isset($config['DB_HOST']) ? $config['DB_HOST'] : '127.0.0.1');
    $port = isset($config['ANALYTICS_DB_PORT']) ? $config['ANALYTICS_DB_PORT'] : (isset($config['DB_PORT']) ? $config['DB_PORT'] : 3306);
    $dbname = isset($config['ANALYTICS_DB_NAME']) ? $config['ANALYTICS_DB_NAME'] : 'justice_hammer_analytics';
    $user = isset($config['ANALYTICS_DB_USER']) ? $config['ANALYTICS_DB_USER'] : (isset($config['DB_USER']) ? $config['DB_USER'] : 'root');
    $pass = isset($config['ANALYTICS_DB_PASS']) ? $config['ANALYTICS_DB_PASS'] : (isset($config['DB_PASS']) ? $config['DB_PASS'] : '');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $analyticsPdo = new PDO($dsn, $user, $pass, [
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
            echo json_encode(['ok' => false, 'error' => 'Analytics database connection failed']);
            exit;
        }
    }
}
