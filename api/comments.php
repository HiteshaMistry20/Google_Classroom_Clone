<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            $announcement_id = isset($_GET['announcement_id']) ? (int)$_GET['announcement_id'] : 0;
            
            if (!$announcement_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
                exit();
            }
            
            // Get comments for announcement
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as user_name, u.role as user_role
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.announcement_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$announcement_id]);
            $comments = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'comments' => $comments
            ]);
            break;
            
        case 'add':
            $announcement_id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
            $content = trim($_POST['content'] ?? '');
            
            if (!$announcement_id || empty($content)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Announcement ID and content required']);
                exit();
            }
            
            // Verify user has access to this announcement
            if ($user_role === 'student') {
                $stmt = $pdo->prepare("
                    SELECT 1 FROM announcements a
                    JOIN classes c ON a.class_id = c.id
                    JOIN class_enrollments ce ON c.id = ce.class_id
                    WHERE a.id = ? AND ce.student_id = ?
                ");
                $stmt->execute([$announcement_id, $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit();
                }
            } elseif ($user_role === 'teacher') {
                $stmt = $pdo->prepare("
                    SELECT 1 FROM announcements a
                    JOIN classes c ON a.class_id = c.id
                    WHERE a.id = ? AND c.teacher_id = ?
                ");
                $stmt->execute([$announcement_id, $user_id]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit();
                }
            }
            
            // Add comment
            $stmt = $pdo->prepare("INSERT INTO comments (announcement_id, user_id, content) VALUES (?, ?, ?)");
            if ($stmt->execute([$announcement_id, $user_id, $content])) {
                $comment_id = $pdo->lastInsertId();
                
                // Get the new comment with user info
                $stmt = $pdo->prepare("
                    SELECT c.*, u.name as user_name, u.role as user_role
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.id = ?
                ");
                $stmt->execute([$comment_id]);
                $new_comment = $stmt->fetch();
                
                // Notify announcement author (if different from commenter)
                $stmt = $pdo->prepare("SELECT author_id FROM announcements WHERE id = ?");
                $stmt->execute([$announcement_id]);
                $announcement = $stmt->fetch();
                
                if ($announcement && $announcement['author_id'] != $user_id) {
                    createNotification($pdo, $announcement['author_id'], 'New Comment', 
                        "Someone commented on your announcement", 'announcement', $announcement_id);
                }
                
                echo json_encode([
                    'success' => true,
                    'comment' => $new_comment,
                    'message' => 'Comment added successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
            }
            break;
            
        case 'delete':
            $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
            
            if (!$comment_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Comment ID required']);
                exit();
            }
            
            // Verify user owns the comment or is the announcement author
            $stmt = $pdo->prepare("
                SELECT c.user_id, a.author_id 
                FROM comments c
                JOIN announcements a ON c.announcement_id = a.id
                WHERE c.id = ?
            ");
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Comment not found']);
                exit();
            }
            
            if ($comment['user_id'] != $user_id && $comment['author_id'] != $user_id) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit();
            }
            
            // Delete comment
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            if ($stmt->execute([$comment_id])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Comment deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete comment']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Comments API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
