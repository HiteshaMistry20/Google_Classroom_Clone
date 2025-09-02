<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing notification creation...\n";

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/notification_system.php';

try {
    // Test creating a simple notification
    echo "Creating test notification...\n";
    $result = createNotification($pdo, 1, 'Test Notification', 'This is a test notification for the panel', 'test', null);
    
    if ($result) {
        echo "✓ Test notification created successfully!\n";
        
        // Check if it exists in database
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = 1 AND title = 'Test Notification' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $notification = $stmt->fetch();
        
        if ($notification) {
            echo "✓ Notification found in database:\n";
            echo "  - ID: " . $notification['id'] . "\n";
            echo "  - Title: " . $notification['title'] . "\n";
            echo "  - Message: " . $notification['message'] . "\n";
            echo "  - Type: " . $notification['type'] . "\n";
            echo "  - Is Read: " . ($notification['is_read'] ? 'Yes' : 'No') . "\n";
            echo "  - Created: " . $notification['created_at'] . "\n";
        } else {
            echo "✗ Notification not found in database!\n";
        }
    } else {
        echo "✗ Failed to create test notification!\n";
    }
    
    // Test notification system functions
    echo "\nTesting notification system functions...\n";
    
    // Check if we have any classes
    $stmt = $pdo->query("SELECT id FROM classes LIMIT 1");
    $class = $stmt->fetch();
    
    if ($class) {
        echo "Found class ID: " . $class['id'] . "\n";
        
        // Test notifyClassCreated
        echo "Testing notifyClassCreated...\n";
        $result = notifyClassCreated($pdo, $class['id']);
        echo "Result: " . ($result ? "Success" : "Failed") . "\n";
        
        // Check if notifications were created
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'class_created'");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "Found $count class_created notifications\n";
        
    } else {
        echo "No classes found in database\n";
    }
    
    // Check total notifications
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications");
    $total = $stmt->fetchColumn();
    echo "\nTotal notifications in database: $total\n";
    
    // Show recent notifications
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
    $recent = $stmt->fetchAll();
    
    if ($recent) {
        echo "\nRecent notifications:\n";
        foreach ($recent as $notif) {
            echo "- " . $notif['title'] . " (" . $notif['type'] . ") - " . $notif['created_at'] . "\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
