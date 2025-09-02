<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo "Please log in first.";
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

echo "<h2>Message Debug Information</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>User Role:</strong> $user_role</p>";

// Check messages table structure
echo "<h3>Messages Table Structure:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage();
}

// Check total messages
echo "<h3>Total Messages in Database:</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
    $result = $stmt->fetch();
    echo "<p>Total messages: " . $result['total'] . "</p>";
} catch (Exception $e) {
    echo "Error counting messages: " . $e->getMessage();
}

// Check messages for current user
echo "<h3>Messages for Current User:</h3>";
try {
    // Received messages
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ?");
    $stmt->execute([$user_id]);
    $received = $stmt->fetch();
    
    // Sent messages
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE sender_id = ?");
    $stmt->execute([$user_id]);
    $sent = $stmt->fetch();
    
    echo "<p>Messages received: " . $received['count'] . "</p>";
    echo "<p>Messages sent: " . $sent['count'] . "</p>";
} catch (Exception $e) {
    echo "Error checking user messages: " . $e->getMessage();
}

// Show recent messages
echo "<h3>Recent Messages:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               s.name as sender_name, 
               r.name as receiver_name
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        JOIN users r ON m.receiver_id = r.id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $messages = $stmt->fetchAll();
    
    if (empty($messages)) {
        echo "<p>No messages found.</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>From</th><th>To</th><th>Subject</th><th>Message</th><th>Created</th><th>Read</th></tr>";
        foreach ($messages as $msg) {
            echo "<tr>";
            echo "<td>" . $msg['id'] . "</td>";
            echo "<td>" . htmlspecialchars($msg['sender_name']) . "</td>";
            echo "<td>" . htmlspecialchars($msg['receiver_name']) . "</td>";
            echo "<td>" . htmlspecialchars($msg['subject']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($msg['message'], 0, 50)) . "...</td>";
            echo "<td>" . $msg['created_at'] . "</td>";
            echo "<td>" . ($msg['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "Error showing recent messages: " . $e->getMessage();
}

// Test message insertion
echo "<h3>Test Message Insertion:</h3>";
if (isset($_POST['test_insert'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$user_id, $user_id, 'Test Message', 'This is a test message to verify the system is working.']);
        
        if ($result) {
            echo "<p style='color: green;'>Test message inserted successfully!</p>";
        } else {
            echo "<p style='color: red;'>Failed to insert test message.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error inserting test message: " . $e->getMessage() . "</p>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_insert'>Insert Test Message</button>";
echo "</form>";

echo "<hr>";
echo "<p><a href='student/messages.php'>Go to Messages</a> | <a href='student/messages_enhanced.php'>Go to Enhanced Messages</a></p>";
?>
