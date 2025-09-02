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
$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'get' && isset($_GET['id'])) {
        // Get individual class details
        $class_id = $_GET['id'];
        
        if ($user_role === 'admin') {
            // Admin can see any class
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as teacher_name, u.email as teacher_email
                FROM classes c 
                JOIN users u ON c.teacher_id = u.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$class_id]);
        } elseif ($user_role === 'teacher') {
            // Teacher can see their own classes
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as teacher_name, u.email as teacher_email
                FROM classes c 
                JOIN users u ON c.teacher_id = u.id 
                WHERE c.id = ? AND c.teacher_id = ?
            ");
            $stmt->execute([$class_id, $user_id]);
        } else {
            // Student can see classes they're enrolled in
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as teacher_name, u.email as teacher_email
                FROM classes c 
                JOIN users u ON c.teacher_id = u.id 
                JOIN class_enrollments e ON c.id = e.class_id
                WHERE c.id = ? AND e.student_id = ?
            ");
            $stmt->execute([$class_id, $user_id]);
        }
        
        $class = $stmt->fetch();
        
        if ($class) {
            echo json_encode([
                'success' => true,
                'class' => $class
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Class not found or access denied'
            ]);
        }
        
    } else {
        // List classes (existing functionality)
        if ($user_role === 'admin') {
            // Admin can see all classes
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as teacher_name, 
                       (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
                FROM classes c 
                JOIN users u ON c.teacher_id = u.id 
                WHERE c.status = 'active'
                ORDER BY c.created_at DESC
            ");
            $stmt->execute();
        } elseif ($user_role === 'teacher') {
            // Teacher can see their own classes
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as teacher_name,
                       (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
                FROM classes c 
                JOIN users u ON c.teacher_id = u.id 
                WHERE c.teacher_id = ? AND c.status = 'active'
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$user_id]);
        } else {
            // Student can see classes they're enrolled in
            $stmt = $pdo->prepare("
                SELECT c.*, u.name as teacher_name,
                       (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
                FROM classes c 
                JOIN users u ON c.teacher_id = u.id 
                JOIN class_enrollments e ON c.id = e.class_id
                WHERE e.student_id = ? AND c.status = 'active'
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$user_id]);
        }
        
        $classes = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'classes' => $classes
        ]);
    }
    
} catch (Exception $e) {
    error_log("Classes API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
