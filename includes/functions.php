<?php

function generate_tracking_code($len = 8) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $len; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}


 // Generate a random filename for uploaded files
function random_filename($originalFilename) {
    $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $randomName = uniqid('', true) . '.' . $ext;
    return $randomName;
}


function json_response($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}



function getConfig($key, $default = null) {
    static $config = null;
    if ($config === null) {
        $config = require_once __DIR__ . '/config.php';
    }
    return $config[$key] ?? $default;
}


function base_url($path = '') {
    $base = rtrim(getConfig('BASE_URL', 'http://127.0.0.1/JusticeHammerDBMS_corrected'), '/');
    $path = ltrim($path, '/');
    return $base . ($path ? "/$path" : '');
}


function redirect_to($path) {
    header('Location: ' . base_url($path));
    exit;
}
