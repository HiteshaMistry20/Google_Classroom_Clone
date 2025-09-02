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

try {
    if ($user_role === 'admin') {
        // Admin can see all materials
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as class_name, u.name as teacher_name
            FROM materials m 
            JOIN classes c ON m.class_id = c.id
            JOIN users u ON m.teacher_id = u.id 
            ORDER BY m.created_at DESC
        ");
        $stmt->execute();
    } elseif ($user_role === 'teacher') {
        // Teacher can see materials they uploaded
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as class_name, u.name as teacher_name
            FROM materials m 
            JOIN classes c ON m.class_id = c.id
            JOIN users u ON c.teacher_id = u.id 
            WHERE m.teacher_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        // Student can see materials from classes they're enrolled in
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as class_name, u.name as teacher_name
            FROM materials m 
            JOIN classes c ON m.class_id = c.id
            JOIN users u ON m.teacher_id = u.id 
            JOIN enrollments e ON c.id = e.class_id
            WHERE e.student_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }
    
    $materials = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'materials' => $materials
    ]);
    
} catch (Exception $e) {
    error_log("Materials API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
