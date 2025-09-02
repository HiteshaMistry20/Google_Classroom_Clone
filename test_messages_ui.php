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

echo "<h2>Messages UI Test</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>User Role:</strong> $user_role</p>";

// Check if there are any messages
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE sender_id = ? OR receiver_id = ?");
$stmt->execute([$user_id, $user_id]);
$result = $stmt->fetch();
echo "<p><strong>Total Messages:</strong> " . $result['count'] . "</p>";

// Show some sample messages
$stmt = $pdo->prepare("
    SELECT m.*, 
           s.name as sender_name, 
           r.name as receiver_name
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    JOIN users r ON m.receiver_id = r.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$messages = $stmt->fetchAll();

echo "<h3>Sample Messages:</h3>";
if (empty($messages)) {
    echo "<p>No messages found. Let's create a test message.</p>";
    
    // Create a test message
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt->execute([$user_id, $user_id, 'Test Message', 'This is a test message to verify the UI is working.'])) {
        echo "<p style='color: green;'>Test message created successfully!</p>";
    } else {
        echo "<p style='color: red;'>Failed to create test message.</p>";
    }
} else {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>From</th><th>To</th><th>Subject</th><th>Message</th><th>Created</th></tr>";
    foreach ($messages as $msg) {
        echo "<tr>";
        echo "<td>" . $msg['id'] . "</td>";
        echo "<td>" . htmlspecialchars($msg['sender_name']) . "</td>";
        echo "<td>" . htmlspecialchars($msg['receiver_name']) . "</td>";
        echo "<td>" . htmlspecialchars($msg['subject']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($msg['message'], 0, 50)) . "...</td>";
        echo "<td>" . $msg['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='student/messages.php'>Go to Messages Page</a></p>";
echo "<p><a href='student/messages.php?debug=1'>Go to Messages Page with Debug</a></p>";
?>
