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
        // Admin can see all assignments
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name, u.name as teacher_name,
                   (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
            FROM assignments a 
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.teacher_id = u.id 
            ORDER BY a.due_date ASC
        ");
        $stmt->execute();
    } elseif ($user_role === 'teacher') {
        // Teacher can see assignments they created
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name, u.name as teacher_name,
                   (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
            FROM assignments a 
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.teacher_id = u.id 
            WHERE a.teacher_id = ?
            ORDER BY a.due_date ASC
        ");
        $stmt->execute([$user_id]);
    } else {
        // Student can see assignments from classes they're enrolled in
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name, u.name as teacher_name,
                   s.status as submission_status, s.grade, s.submitted_at
            FROM assignments a 
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.teacher_id = u.id 
            JOIN enrollments e ON c.id = e.class_id
            LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
            WHERE e.student_id = ?
            ORDER BY a.due_date ASC
        ");
        $stmt->execute([$user_id, $user_id]);
    }
    
    $assignments = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'assignments' => $assignments
    ]);
    
} catch (Exception $e) {
    error_log("Assignments API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
