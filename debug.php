<?php
// Simple debug file to test PHP functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Debug Test</h1>";
echo "<p>PHP is working!</p>";

// Test database connection
echo "<h2>Testing Database Connection</h2>";
try {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    
    // First test basic MySQL connection
    $pdo_temp = new PDO("mysql:host=$host", $username, $password);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ Basic MySQL connection successful</p>";
    
    // Test if database exists
    $stmt = $pdo_temp->query("SHOW DATABASES LIKE 'classroom_clone'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Database 'classroom_clone' exists</p>";
        
        // Test connection to specific database
        $pdo = new PDO("mysql:host=$host;dbname=classroom_clone;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color: green;'>✓ Connection to classroom_clone database successful</p>";
        
        // Test if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Users table exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Users table does not exist</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Database 'classroom_clone' does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database Error: " . $e->getMessage() . "</p>";
}

// Test file includes
echo "<h2>Testing File Includes</h2>";
$files_to_test = [
    'config/database.php',
    'includes/functions.php',
    'includes/email_functions.php',
    'includes/sidebar.php'
];

foreach ($files_to_test as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>";
    } else {
        echo "<p style='color: red;'>✗ $file missing</p>";
    }
}

// Test upload directories
echo "<h2>Testing Upload Directories</h2>";
$dirs_to_test = [
    'uploads',
    'uploads/materials',
    'uploads/assignments',
    'uploads/submissions'
];

foreach ($dirs_to_test as $dir) {
    if (is_dir($dir)) {
        echo "<p style='color: green;'>✓ $dir exists</p>";
    } else {
        echo "<p style='color: red;'>✗ $dir missing</p>";
    }
}

echo "<h2>PHP Info</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";
?>
