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

// Get detailed attendance records
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

// Get attendance summary for each student
$stmt = $pdo->prepare("
    SELECT 
        u.name as student_name,
        u.email as student_email,
        COUNT(*) as total_days,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        ROUND((SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
    FROM attendance a 
    JOIN users u ON a.student_id = u.id 
    WHERE a.class_id = ? AND a.attendance_date BETWEEN ? AND ?
    GROUP BY u.id, u.name, u.email
    ORDER BY u.name
");
$stmt->execute([$class_id, $month_start, $month_end]);
$attendance_summary = $stmt->fetchAll();

// Create Excel content using HTML table format
$filename = $class['name'] . '_attendance_summary_' . date('Y-m', strtotime($date)) . '.xls';

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
echo 'table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }';
echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
echo 'th { background-color: #f2f2f2; font-weight: bold; }';
echo '.summary-table th { background-color: #4CAF50; color: white; }';
echo '.detail-table th { background-color: #2196F3; color: white; }';
echo '</style>';
echo '</head>';
echo '<body>';

// Report Header
echo '<h2>Attendance Report - ' . htmlspecialchars($class['name']) . ' (' . htmlspecialchars($class['subject']) . ')</h2>';
echo '<h3>Period: ' . date('F Y', strtotime($date)) . '</h3>';
echo '<br>';

// Attendance Summary Table
echo '<h3>Attendance Summary</h3>';
echo '<table class="summary-table">';
echo '<tr>';
echo '<th style="width: 200px;">Student Name</th>';
echo '<th style="width: 250px;">Student Email</th>';
echo '<th style="width: 80px;">Total Days</th>';
echo '<th style="width: 80px;">Present</th>';
echo '<th style="width: 80px;">Late</th>';
echo '<th style="width: 80px;">Absent</th>';
echo '<th style="width: 100px;">Attendance %</th>';
echo '</tr>';

foreach ($attendance_summary as $summary) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars(trim($summary['student_name'])) . '</td>';
    echo '<td>' . htmlspecialchars(trim($summary['student_email'])) . '</td>';
    echo '<td>' . $summary['total_days'] . '</td>';
    echo '<td>' . $summary['present_days'] . '</td>';
    echo '<td>' . $summary['late_days'] . '</td>';
    echo '<td>' . $summary['absent_days'] . '</td>';
    echo '<td>' . $summary['attendance_percentage'] . '%</td>';
    echo '</tr>';
}

echo '</table>';

// Detailed Attendance Records
echo '<h3>Detailed Attendance Records</h3>';
echo '<table class="detail-table">';
echo '<tr>';
echo '<th style="width: 200px;">Student Name</th>';
echo '<th style="width: 250px;">Student Email</th>';
echo '<th style="width: 100px;">Date</th>';
echo '<th style="width: 80px;">Status</th>';
echo '<th style="width: 150px;">Marked At</th>';
echo '</tr>';

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
    echo '</tr>';
}

echo '</table>';

// Class Statistics
$total_students = count($attendance_summary);
$total_days = array_sum(array_column($attendance_summary, 'total_days'));
$total_present = array_sum(array_column($attendance_summary, 'present_days'));
$total_late = array_sum(array_column($attendance_summary, 'late_days'));
$total_absent = array_sum(array_column($attendance_summary, 'absent_days'));
$avg_attendance = $total_days > 0 ? round((($total_present + $total_late) / $total_days) * 100, 2) : 0;

echo '<h3>Class Statistics</h3>';
echo '<table>';
echo '<tr><th>Metric</th><th>Value</th></tr>';
echo '<tr><td>Total Students</td><td>' . $total_students . '</td></tr>';
echo '<tr><td>Total Days</td><td>' . $total_days . '</td></tr>';
echo '<tr><td>Total Present</td><td>' . $total_present . '</td></tr>';
echo '<tr><td>Total Late</td><td>' . $total_late . '</td></tr>';
echo '<tr><td>Total Absent</td><td>' . $total_absent . '</td></tr>';
echo '<tr><td>Average Attendance</td><td>' . $avg_attendance . '%</td></tr>';
echo '</table>';

echo '</body>';
echo '</html>';
exit();
?>
