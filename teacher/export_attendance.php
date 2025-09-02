<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m');

if (!$class_id) {
    setFlashMessage('error', 'Class ID is required.');
    header('Location: attendance.php');
    exit();
}

// Verify class ownership
$stmt = $pdo->prepare("SELECT id, name, subject FROM classes WHERE id = ? AND teacher_id = ? AND status = 'active'");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    setFlashMessage('error', 'Class not found or access denied.');
    header('Location: attendance.php');
    exit();
}

// Get attendance data for the month
$month_start = date('Y-m-01', strtotime($date));
$month_end = date('Y-m-t', strtotime($date));

$stmt = $pdo->prepare("
    SELECT 
        u.name as student_name,
        u.email as student_email,
        a.attendance_date,
        a.status,
        a.marked_at
    FROM attendance a 
    JOIN users u ON a.student_id = u.id 
    WHERE a.class_id = ? AND a.attendance_date BETWEEN ? AND ?
    ORDER BY u.name, a.attendance_date
");
$stmt->execute([$class_id, $month_start, $month_end]);
$attendance_records = $stmt->fetchAll();

// Get all students in the class for complete report
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email
    FROM users u 
    JOIN class_enrollments ce ON u.id = ce.student_id 
    WHERE ce.class_id = ? 
    ORDER BY u.name
");
$stmt->execute([$class_id]);
$all_students = $stmt->fetchAll();

// Create CSV content
$filename = $class['name'] . '_attendance_' . date('Y-m', strtotime($date)) . '.csv';

// Set headers for CSV download with Excel compatibility
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 encoding (helps Excel recognize encoding)
fwrite($output, "\xEF\xBB\xBF");

// Write CSV headers with proper formatting
fputcsv($output, [
    'Student Name',
    'Student Email', 
    'Date',
    'Status',
    'Marked At',
    'Class',
    'Subject'
]);

// Write attendance data with proper formatting
foreach ($attendance_records as $record) {
    // Format date for better Excel compatibility
    $formatted_date = date('Y-m-d', strtotime($record['attendance_date']));
    
    // Format marked_at time for better readability
    $marked_time = $record['marked_at'] ? date('Y-m-d H:i:s', strtotime($record['marked_at'])) : '';
    
    // Capitalize status
    $status = ucfirst($record['status']);
    
    fputcsv($output, [
        trim($record['student_name']), // Remove extra spaces
        trim($record['student_email']), // Remove extra spaces
        $formatted_date,
        $status,
        $marked_time,
        $class['name'],
        $class['subject']
    ]);
}

// Close the file
fclose($output);
exit();
?>
