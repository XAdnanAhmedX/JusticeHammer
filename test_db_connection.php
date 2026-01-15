<?php
/**
 * Test Database Connection
 * Run this file to test if database connections work
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/db_analytics.php';

echo "Testing Database Connections...\n";
echo "================================\n\n";

// Test Primary Database
try {
    $pdo = getDbConnection();
    echo "✓ Primary DB (justice_hammer): Connected successfully\n";

    // Test query - safe: check for existence of a known table if present
    try {
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()');
        $result = $stmt->fetch();
        echo "  - Primary DB has " . $result['count'] . " tables (may vary per setup)\n";
    } catch (Exception $e) {
        echo "  - Primary DB query test: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "✗ Primary DB Error: " . $e->getMessage() . "\n";
}

// Test Analytics Database
try {
    $analyticsPdo = getAnalyticsDbConnection();
    echo "✓ Analytics DB (justice_hammer_analytics): Connected successfully\n";

    try {
        $stmt = $analyticsPdo->query('SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()');
        $result = $stmt->fetch();
        echo "  - Analytics DB has " . $result['count'] . " tables (may vary per setup)\n";
    } catch (Exception $e) {
        echo "  - Analytics DB query test: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "✗ Analytics DB Error: " . $e->getMessage() . "\n";
}

echo "\n================================\n";
echo "Test Complete\n";
