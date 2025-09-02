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
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid assignment ID']);
    exit();
}

try {
    if ($user_role === 'teacher') {
        // Teacher can only access their own assignments
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name 
            FROM assignments a 
            JOIN classes c ON a.class_id = c.id 
            WHERE a.id = ? AND a.teacher_id = ?
        ");
        $stmt->execute([$assignment_id, $user_id]);
    } elseif ($user_role === 'admin') {
        // Admin can access all assignments
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name 
            FROM assignments a 
            JOIN classes c ON a.class_id = c.id 
            WHERE a.id = ?
        ");
        $stmt->execute([$assignment_id]);
    } else {
        // Students cannot edit assignments
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $assignment = $stmt->fetch();
    
    if ($assignment) {
        // Get assignment files
        $stmt = $pdo->prepare("
            SELECT file_path, file_name, file_type, file_size 
            FROM assignment_files 
            WHERE assignment_id = ?
        ");
        $stmt->execute([$assignment_id]);
        $files = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'assignment' => $assignment,
            'files' => $files
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Assignment not found']);
    }
    
} catch (Exception $e) {
    error_log("Get Assignment API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
