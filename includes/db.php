<?php

function loadEnvFile(string $envFile): array
{
    $vars = [];
    if (!file_exists($envFile)) {
        return $vars;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);

        if (
            (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))
        ) {
            $val = substr($val, 1, -1);
        }

        $vars[$key] = $val;
    }

    return $vars;
}

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = [];
    $envFile = __DIR__ . '/../.env';

    if (file_exists($envFile)) {
        $config = loadEnvFile($envFile);
    } else {
        $cfgFile = __DIR__ . '/config.php';
        if (file_exists($cfgFile)) {
            $cfg = include $cfgFile;
            if (is_array($cfg)) {
                $config = $cfg;
            }
        }
    }

    foreach ($config as $key => $value) {
        $_ENV[$key] = $value;
    }

    $host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port   = $_ENV['DB_PORT'] ?? 3306;
    $dbname = $_ENV['DB_NAME'] ?? 'justice_hammer';
    $user   = $_ENV['DB_USER'] ?? 'root';
    $pass   = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Database connection failed');
    }
    


    return $pdo;
}
