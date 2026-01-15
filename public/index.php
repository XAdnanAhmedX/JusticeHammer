<?php
/**
 * Health Check Endpoint
 * Tests connectivity to both primary and analytics databases
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/db_analytics.php';

header('Content-Type: text/plain; charset=utf-8');

$primaryStatus = 'Unknown';
$analyticsStatus = 'Unknown';
$primaryError = null;
$analyticsError = null;

// Test Primary Database
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT 1');
    $res = $stmt->fetch();
    if ($res !== false) {
        $primaryStatus = 'Connected';
    } else {
        $primaryStatus = 'Failed - No result';
    }
} catch (Exception $e) {
    $primaryStatus = 'Failed';
    $primaryError = $e->getMessage();
}

// Test Analytics Database
try {
    $analyticsPdo = getAnalyticsDbConnection();
    $stmt = $analyticsPdo->query('SELECT 1');
    $res = $stmt->fetch();
    if ($res !== false) {
        $analyticsStatus = 'Connected';
    } else {
        $analyticsStatus = 'Failed - No result';
    }
} catch (Exception $e) {
    $analyticsStatus = 'Failed';
    $analyticsError = $e->getMessage();
}

echo "Primary DB: " . $primaryStatus;
if ($primaryError) {
    echo " - " . $primaryError;
}
echo "\n";
echo "Analytics DB: " . $analyticsStatus;
if ($analyticsError) {
    echo " - " . $analyticsError;
}
echo "\n";

if ($primaryStatus === 'Connected' && $analyticsStatus === 'Connected') {
    echo "\n✓ All systems operational\n";
    http_response_code(200);
} else {
    echo "\n✗ One or more database connections failed\n";
    http_response_code(503);
}
