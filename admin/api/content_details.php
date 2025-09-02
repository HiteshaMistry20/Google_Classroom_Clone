<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$content_type = $_GET['type'] ?? '';
$content_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$content_id || !$content_type) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    switch ($content_type) {
        case 'announcement':
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.name as author_name,
                    u.email as author_email,
                    c.name as class_name,
                    c.id as class_id
                FROM announcements a
                JOIN users u ON a.teacher_id = u.id
                JOIN classes c ON a.class_id = c.id
                WHERE a.id = ?
            ");
            $stmt->execute([$content_id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'comment':
            $stmt = $pdo->prepare("
                SELECT 
                    co.*,
                    u.name as author_name,
                    u.email as author_email,
                    c.name as class_name,
                    c.id as class_id,
                    a.title as announcement_title
                FROM comments co
                JOIN users u ON co.user_id = u.id
                JOIN announcements a ON co.announcement_id = a.id
                JOIN classes c ON a.class_id = c.id
                WHERE co.id = ?
            ");
            $stmt->execute([$content_id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'material':
            $stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    u.name as author_name,
                    u.email as author_email,
                    c.name as class_name,
                    c.id as class_id
                FROM materials m
                JOIN users u ON m.teacher_id = u.id
                JOIN classes c ON m.class_id = c.id
                WHERE m.id = ?
            ");
            $stmt->execute([$content_id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'assignment':
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.name as author_name,
                    u.email as author_email,
                    c.name as class_name,
                    c.id as class_id
                FROM assignments a
                JOIN users u ON a.teacher_id = u.id
                JOIN classes c ON a.class_id = c.id
                WHERE a.id = ?
            ");
            $stmt->execute([$content_id]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid content type']);
            exit();
    }
    
    if ($content) {
        echo json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Content not found']);
    }
    
} catch (Exception $e) {
    error_log("Content details API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
