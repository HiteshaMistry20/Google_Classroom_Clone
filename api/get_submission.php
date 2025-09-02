<?php
// Suppress any HTML output
error_reporting(0);
ini_set('display_errors', 0);

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
$submission_id = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;

if (!$submission_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Submission ID is required']);
    exit();
}

try {
    // Get submission details with access control
    if ($user_role === 'teacher') {
        // Teacher can view submissions for assignments they created
        $stmt = $pdo->prepare("
            SELECT s.*, u.name as student_name, u.email as student_email,
                   a.title as assignment_title, a.description as assignment_description,
                   a.due_date, a.max_points, c.name as class_name
            FROM submissions s
            JOIN users u ON s.student_id = u.id
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE s.id = ? AND a.teacher_id = ?
        ");
        $stmt->execute([$submission_id, $user_id]);
    } elseif ($user_role === 'student') {
        // Student can only view their own submissions
        $stmt = $pdo->prepare("
            SELECT s.*, u.name as student_name, u.email as student_email,
                   a.title as assignment_title, a.description as assignment_description,
                   a.due_date, a.max_points, c.name as class_name
            FROM submissions s
            JOIN users u ON s.student_id = u.id
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE s.id = ? AND s.student_id = ?
        ");
        $stmt->execute([$submission_id, $user_id]);
    } else {
        // Admin can view all submissions
        $stmt = $pdo->prepare("
            SELECT s.*, u.name as student_name, u.email as student_email,
                   a.title as assignment_title, a.description as assignment_description,
                   a.due_date, a.max_points, c.name as class_name
            FROM submissions s
            JOIN users u ON s.student_id = u.id
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$submission_id]);
    }
    
    $submission = $stmt->fetch();
    
    if (!$submission) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Submission not found or access denied']);
        exit();
    }
    
    // Get submission files
    $stmt = $pdo->prepare("
        SELECT id, file_name, file_path, file_type, file_size
        FROM submission_files
        WHERE submission_id = ?
        ORDER BY id
    ");
    $stmt->execute([$submission_id]);
    $files = $stmt->fetchAll();
    
    // Process files to include view URLs
    foreach ($files as &$file) {
        // Clean up the file path - remove any relative path components
        $cleanPath = basename($file['file_path']);
        $file['view_url'] = '../uploads/submissions/' . $cleanPath;
        $file['download_url'] = '../uploads/submissions/' . $cleanPath;
        $file['file_icon'] = getFileIcon($file['file_type']);
        $file['file_size_formatted'] = formatFileSize($file['file_size']);
    }
    
    // Check if submission is late
    $is_late = strtotime($submission['submitted_at']) > strtotime($submission['due_date']);
    
    echo json_encode([
        'success' => true,
        'submission' => [
            'id' => $submission['id'],
            'student_name' => $submission['student_name'],
            'student_email' => $submission['student_email'],
            'assignment_title' => $submission['assignment_title'],
            'assignment_description' => $submission['assignment_description'],
            'class_name' => $submission['class_name'],
            'submitted_at' => $submission['submitted_at'],
            'due_date' => $submission['due_date'],
            'is_late' => $is_late,
            'grade' => $submission['grade'],
            'feedback' => $submission['feedback'],
            'status' => $submission['status'],
            'max_points' => $submission['max_points'],
            'files' => $files
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get submission API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}




?>
