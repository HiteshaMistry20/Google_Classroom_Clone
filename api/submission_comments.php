<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get comments for a submission
        $submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
        
        if (!$submission_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Submission ID required']);
            exit();
        }
        
        // Verify user has access to this submission
        $stmt = $pdo->prepare("
            SELECT s.*, a.class_id, c.teacher_id 
            FROM submissions s 
            JOIN assignments a ON s.assignment_id = a.id 
            JOIN classes c ON a.class_id = c.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Submission not found']);
            exit();
        }
        
        // Check if user has access (student who submitted or teacher of the class)
        $has_access = false;
        if ($user_role === 'student' && $submission['student_id'] == $user_id) {
            $has_access = true;
        } elseif ($user_role === 'teacher' && $submission['teacher_id'] == $user_id) {
            $has_access = true;
        }
        
        if (!$has_access) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit();
        }
        
        // Get comments
        $stmt = $pdo->prepare("
            SELECT sc.*, u.name as user_name, u.role as user_role
            FROM submission_comments sc
            JOIN users u ON sc.user_id = u.id
            WHERE sc.submission_id = ?
            ORDER BY sc.created_at ASC
        ");
        $stmt->execute([$submission_id]);
        $comments = $stmt->fetchAll();
        
        // Filter comments based on user role and comment type
        $filtered_comments = [];
        foreach ($comments as $comment) {
            // Teachers can see all comments
            if ($user_role === 'teacher') {
                $filtered_comments[] = $comment;
            }
            // Students can see:
            // 1. All public comments
            // 2. Their own private comments
            // 3. Private comments from teachers (about their submission)
            elseif ($user_role === 'student') {
                if ($comment['comment_type'] === 'public' || 
                    $comment['user_id'] == $user_id ||
                    ($comment['comment_type'] === 'private' && $comment['user_role'] === 'teacher')) {
                    $filtered_comments[] = $comment;
                }
            }
        }
        
        echo json_encode(['success' => true, 'comments' => $filtered_comments]);
        break;
        
    case 'POST':
        // Add a new comment
        $input = json_decode(file_get_contents('php://input'), true);
        
        $submission_id = isset($input['submission_id']) ? (int)$input['submission_id'] : 0;
        $comment_type = isset($input['comment_type']) ? $input['comment_type'] : 'public';
        $content = isset($input['content']) ? trim($input['content']) : '';
        
        if (!$submission_id || !$content) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Submission ID and content required']);
            exit();
        }
        
        if (!in_array($comment_type, ['public', 'private'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid comment type']);
            exit();
        }
        
        // Verify user has access to this submission
        $stmt = $pdo->prepare("
            SELECT s.*, a.class_id, c.teacher_id 
            FROM submissions s 
            JOIN assignments a ON s.assignment_id = a.id 
            JOIN classes c ON a.class_id = c.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Submission not found']);
            exit();
        }
        
        // Check if user has access (student who submitted or teacher of the class)
        $has_access = false;
        if ($user_role === 'student' && $submission['student_id'] == $user_id) {
            $has_access = true;
        } elseif ($user_role === 'teacher' && $submission['teacher_id'] == $user_id) {
            $has_access = true;
        }
        
        if (!$has_access) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit();
        }
        
        // Insert comment
        $stmt = $pdo->prepare("
            INSERT INTO submission_comments (submission_id, user_id, comment_type, content) 
            VALUES (?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$submission_id, $user_id, $comment_type, $content])) {
            $comment_id = $pdo->lastInsertId();
            
            // Get the created comment with user info
            $stmt = $pdo->prepare("
                SELECT sc.*, u.name as user_name, u.role as user_role
                FROM submission_comments sc
                JOIN users u ON sc.user_id = u.id
                WHERE sc.id = ?
            ");
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();
            
            echo json_encode(['success' => true, 'comment' => $comment]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
        }
        break;
        
    case 'DELETE':
        // Delete a comment
        $comment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$comment_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Comment ID required']);
            exit();
        }
        
        // Get comment and verify ownership
        $stmt = $pdo->prepare("
            SELECT sc.*, s.student_id, a.class_id, c.teacher_id
            FROM submission_comments sc
            JOIN submissions s ON sc.submission_id = s.id
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE sc.id = ?
        ");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Comment not found']);
            exit();
        }
        
        // Check if user can delete this comment (comment author or teacher)
        $can_delete = false;
        if ($comment['user_id'] == $user_id) {
            $can_delete = true;
        } elseif ($user_role === 'teacher' && $comment['teacher_id'] == $user_id) {
            $can_delete = true;
        }
        
        if (!$can_delete) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot delete this comment']);
            exit();
        }
        
        // Delete comment
        $stmt = $pdo->prepare("DELETE FROM submission_comments WHERE id = ?");
        if ($stmt->execute([$comment_id])) {
            echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete comment']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>
