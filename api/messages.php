<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            // Get unread messages
            $stmt = $pdo->prepare("
                SELECT id, sender_id, receiver_id, subject, message, created_at, is_read
                FROM messages 
                WHERE receiver_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ]);
            break;
            
        case 'mark_read':
            $message_id = $_POST['message_id'] ?? null;
            
            if ($message_id) {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
                $stmt->execute([$message_id, $user_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Message ID required']);
            }
            break;
            
        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ?");
            $stmt->execute([$user_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Messages API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
