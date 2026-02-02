<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$evidenceId = null;
$args = $argv ?? [];

foreach ($args as $arg) {
    if (strpos($arg, '--evidenceId=') === 0) {
        $evidenceId = (int)substr($arg, strlen('--evidenceId='));
    }
}

if (!$evidenceId || $evidenceId <= 0) {
    echo json_encode([
        'ok' => false,
        'error' => 'Usage: php tools/verify_evidence.php --evidenceId=<id>'
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

try {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare('SELECT id, filename, stored_path, sha256 FROM evidence WHERE id = :evidenceId');
    $stmt->execute(['evidenceId' => $evidenceId]);
    $evidence = $stmt->fetch();
    
    if (!$evidence) {
        echo json_encode([
            'ok' => false,
            'error' => 'Evidence record not found'
        ], JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
    
    // Build full file path
    $uploadsDir = getConfig('UPLOADS_DIR', 'uploads');
    $filePath = __DIR__ . '/../' . $evidence['stored_path'];
    
    if (!file_exists($filePath)) {
        echo json_encode([
            'ok' => false,
            'error' => 'File not found',
            'stored_path' => $evidence['stored_path'],
            'expected_path' => $filePath
        ], JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
    
    // Compute SHA256 from file
    $computedHash = hash_file('sha256', $filePath);
    $storedHash = $evidence['sha256'];
    
    // Compare hashes
    $match = ($computedHash === $storedHash);
    
    echo json_encode([
        'ok' => true,
        'match' => $match,
        'computed' => $computedHash,
        'stored' => $storedHash,
        'evidenceId' => $evidence['id'],
        'filename' => $evidence['filename']
    ], JSON_PRETTY_PRINT) . "\n";
    
    exit($match ? 0 : 1);
    
} catch (PDOException $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Unexpected error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
