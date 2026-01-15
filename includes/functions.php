<?php
/**
 * Helper Functions
 * Various utility functions used throughout the application
 */

/**
 * Generate a random tracking code
 * @param int $len Length of the code (default 8)
 * @return string Uppercase alphanumeric code
 */
function generate_tracking_code($len = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Generate a random filename for uploaded files
 * @param string $originalFilename Original filename
 * @return string Random filename with original extension
 */
function random_filename($originalFilename) {
    $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $randomName = uniqid('', true) . '.' . $ext;
    return $randomName;
}

/**
 * Send JSON response
 * @param array $data Response data
 * @param int $httpCode HTTP status code (default 200)
 */
function json_response($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize string input
 * @param string $data Input string
 * @return string Sanitized string
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


/**
 * Get configuration value
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function getConfig($key, $default = null) {
    static $config = null;
    if ($config === null) {
        $config = require_once __DIR__ . '/config.php';
    }
    return $config[$key] ?? $default;
}

/**
 * Build base URL-aware paths
 */
function base_url($path = '') {
    $base = rtrim(getConfig('BASE_URL', 'http://127.0.0.1/JusticeHammerDBMS_corrected'), '/');
    $path = ltrim($path, '/');
    return $base . ($path ? "/$path" : '');
}

/**
 * Redirect helper
 */
function redirect_to($path) {
    header('Location: ' . base_url($path));
    exit;
}
