<?php
require_once 'config/database.php';

try {
    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Drop all tables in reverse order of dependencies
$tables = [
        'user_settings',
        'comments',
        'notifications',
        'messages',
        'submission_files',
        'submissions',
        'assignment_files',
        'assignments',
        'materials',
        'attendance',
        'announcements',
        'class_enrollments',
        'classes',
        'users'
    ];

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "Dropped table: $table<br>";
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Import the new database structure
    $sql = file_get_contents('database_import.sql');
    $pdo->exec($sql);
    echo "<br>Successfully imported new database structure!<br>";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

echo "<br>Database setup completed successfully!";
?>
