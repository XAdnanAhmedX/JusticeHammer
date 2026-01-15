<?php
/**
 * Check PHP Configuration (Web Accessible)
 * Navigate to: http://localhost/JusticeHammerDBMS/check_php_web.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "PHP Version: " . phpversion() . "\n";
echo "================================\n\n";

echo "Available PDO Drivers:\n";
$drivers = PDO::getAvailableDrivers();
if (empty($drivers)) {
    echo "✗ NO PDO DRIVERS FOUND!\n";
    echo "\n";
    echo "SOLUTION:\n";
    echo "1. Open: C:\\xampp\\php\\php.ini\n";
    echo "2. Find: ;extension=pdo_mysql\n";
    echo "3. Remove semicolon: extension=pdo_mysql\n";
    echo "4. Save and restart Apache in XAMPP\n";
} else {
    echo "✓ " . implode(', ', $drivers) . "\n";
    
    if (in_array('mysql', $drivers)) {
        echo "\n✓ PDO MySQL driver is available!\n\n";
        
        // Test connection
        echo "Testing Database Connection:\n";
        echo "----------------------------\n";
        
        try {
            require_once __DIR__ . '/includes/db.php';
            $pdo = getDbConnection();
            echo "✓ Primary DB: Connected successfully!\n";
            
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
            $result = $stmt->fetch();
            echo "  Users table: " . $result['count'] . " rows\n";
            
        } catch (Exception $e) {
            echo "✗ Connection failed: " . $e->getMessage() . "\n";
            echo "\nPossible issues:\n";
            echo "- MySQL service not running in XAMPP\n";
            echo "- Database 'justice_hammer' doesn't exist\n";
            echo "- Wrong credentials in .env file\n";
        }
    } else {
        echo "\n✗ PDO MySQL driver is NOT available!\n";
        echo "You need to enable extension=pdo_mysql in php.ini\n";
    }
}

echo "\n================================\n";
echo "Loaded Extensions (PDO/MySQL related):\n";
$extensions = get_loaded_extensions();
$relevant = array_filter($extensions, function($ext) {
    return stripos($ext, 'pdo') !== false || stripos($ext, 'mysql') !== false;
});
echo implode(', ', $relevant) . "\n";
