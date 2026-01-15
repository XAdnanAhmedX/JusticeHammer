<?php
/**
 * Check PHP Extensions
 * Run this to see what extensions are loaded
 */

echo "PHP Version: " . phpversion() . "\n";
echo "================================\n\n";

echo "Checking PDO drivers:\n";
$pdoDrivers = PDO::getAvailableDrivers();
echo "Available PDO drivers: " . (empty($pdoDrivers) ? 'NONE' : implode(', ', $pdoDrivers)) . "\n\n";

echo "Checking loaded extensions (filtered):\n";
$extensions = get_loaded_extensions();
$relevantNames = ['pdo', 'pdo_mysql', 'mysqli', 'openssl', 'mbstring', 'json', 'curl'];
$relevant = array_values(array_intersect($relevantNames, $extensions));
if (empty($relevant)) {
    echo "No common extensions from the filter were detected. Detected extensions include: " . implode(', ', array_slice($extensions,0,20)) . " ...\n\n";
} else {
    echo "Found extensions: " . implode(', ', $relevant) . "\n";
}

echo "\n";
if (!in_array('pdo_mysql', $extensions) && !in_array('mysqli', $extensions)) {
    echo "WARNING: pdo_mysql and mysqli do not appear to be loaded. This will prevent MySQL connections.\n";
    echo "SOLUTION:\n";
    echo "1. Open php.ini file in XAMPP (usually in C:\\xampp\\php\\php.ini)\n";
    echo "2. Find and uncomment these lines (remove the semicolon):\n";
    echo "   ;extension=pdo_mysql\n";
    echo "   ;extension=mysqli\n";
    echo "3. Save and restart Apache in XAMPP\n";
} else {
    echo "✓ MySQL extensions look present.\n";
}

echo "\n";
echo "Checking if MySQL service is running is environment-specific; try starting MySQL from XAMPP Control Panel.\n";
