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

// Create Excel content using simple HTML table format that Excel can open
$filename = $class['name'] . '_attendance_' . date('Y-m', strtotime($date)) . '.xls';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Start HTML table that Excel can read
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<style>';
echo 'table { border-collapse: collapse; width: 100%; }';
echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
echo 'th { background-color: #f2f2f2; font-weight: bold; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table>';
echo '<tr>';
echo '<th style="width: 200px;">Student Name</th>';
echo '<th style="width: 250px;">Student Email</th>';
echo '<th style="width: 100px;">Date</th>';
echo '<th style="width: 80px;">Status</th>';
echo '<th style="width: 150px;">Marked At</th>';
echo '<th style="width: 150px;">Class</th>';
echo '<th style="width: 120px;">Subject</th>';
echo '</tr>';

// Write attendance data
foreach ($attendance_records as $record) {
    // Format date for better Excel compatibility
    $formatted_date = date('Y-m-d', strtotime($record['attendance_date']));
    
    // Format marked_at time for better readability
    $marked_time = $record['marked_at'] ? date('Y-m-d H:i:s', strtotime($record['marked_at'])) : '';
    
    // Capitalize status
    $status = ucfirst($record['status']);
    
    echo '<tr>';
    echo '<td>' . htmlspecialchars(trim($record['student_name'])) . '</td>';
    echo '<td>' . htmlspecialchars(trim($record['student_email'])) . '</td>';
    echo '<td>' . $formatted_date . '</td>';
    echo '<td>' . $status . '</td>';
    echo '<td>' . $marked_time . '</td>';
    echo '<td>' . htmlspecialchars($class['name']) . '</td>';
    echo '<td>' . htmlspecialchars($class['subject']) . '</td>';
    echo '</tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';
exit();
?>
