<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

if (!$class_id) {
    setFlashMessage('error', 'Class ID is required.');
    header('Location: attendance.php');
    exit();
}

// Verify class ownership
$stmt = $pdo->prepare("SELECT c.id, c.name, c.subject, u.name as teacher_name FROM classes c JOIN users u ON c.teacher_id = u.id WHERE c.id = ? AND c.teacher_id = ? AND c.status = 'active'");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    setFlashMessage('error', 'Class not found or access denied.');
    header('Location: attendance.php');
    exit();
}

// Get all dates in the month that have attendance records
$month_start = date('Y-m-01', strtotime($month));
$month_end = date('Y-m-t', strtotime($month));

$stmt = $pdo->prepare("
    SELECT DISTINCT attendance_date 
    FROM attendance 
    WHERE class_id = ? AND attendance_date BETWEEN ? AND ?
    ORDER BY attendance_date
");
$stmt->execute([$class_id, $month_start, $month_end]);
$attendance_dates = $stmt->fetchAll();

// If no attendance records found for the month, show a message
if (empty($attendance_dates)) {
    setFlashMessage('warning', 'No attendance records found for the selected month.');
    header('Location: attendance.php?class_id=' . $class_id);
    exit();
}

// Get all students in the class
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email
    FROM users u 
    JOIN class_enrollments ce ON u.id = ce.student_id 
    WHERE ce.class_id = ? 
    ORDER BY u.name
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll();

// Get attendance data for all students and dates
$attendance_data = [];
if (!empty($students) && !empty($attendance_dates)) {
    $student_ids = array_column($students, 'id');
    $date_list = array_column($attendance_dates, 'attendance_date');
    
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    $date_placeholders = str_repeat('?,', count($date_list) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT student_id, attendance_date, status
        FROM attendance 
        WHERE class_id = ? AND student_id IN ($placeholders) AND attendance_date IN ($date_placeholders)
    ");
    
    $params = array_merge([$class_id], $student_ids, $date_list);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll();
    
    // Organize data by student and date
    foreach ($attendance_records as $record) {
        $attendance_data[$record['student_id']][$record['attendance_date']] = $record['status'];
    }
}

// Create Excel content
$filename = $class['name'] . '_attendance_spreadsheet_' . date('Y-m', strtotime($month)) . '.xls';

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
echo 'table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }';
echo 'th, td { border: 1px solid #000; padding: 3px; text-align: center; vertical-align: middle; }';
echo '.header-blue { background-color: #4472C4; color: white; font-weight: bold; }';
echo '.header-yellow { background-color: #FFD966; color: black; font-weight: bold; }';
echo '.header-pink { background-color: #FFB6C1; color: black; }';
echo '.header-brown { background-color: #8B4513; color: white; font-weight: bold; }';
echo '.header-black { background-color: #000000; color: white; font-weight: bold; }';
echo '.student-name { text-align: left; font-weight: bold; }';
echo '.attendance-cell { background-color: #FFE6E6; }';
echo '.summary-cell { background-color: #F0F0F0; font-weight: bold; }';
echo '.present { background-color: #90EE90; }';
echo '.absent { background-color: #FFB6C1; }';
echo '.late { background-color: #FFD700; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table>';

// Title row
echo '<tr>';
echo '<td colspan="' . (count($attendance_dates) + 6) . '" style="text-align: center; font-size: 16px; font-weight: bold; padding: 10px; background-color: #4472C4; color: white;">';
echo 'ATTENDANCE REPORT - ' . htmlspecialchars($class['name']) . ' (' . htmlspecialchars($class['subject']) . ') - ' . date('F Y', strtotime($month));
echo '</td>';
echo '</tr>';

// Row 1: Column headers (A, B, C, etc.)
echo '<tr>';
echo '<td style="width: 30px;"></td>'; // Empty cell
echo '<td style="width: 30px;"></td>'; // Empty cell
echo '<td style="width: 30px;"></td>'; // Empty cell

// Add column headers for dates
foreach ($attendance_dates as $date) {
    $day_name = date('D', strtotime($date));
    echo '<td style="width: 30px; font-weight: bold;">' . $day_name . '</td>';
}

// Add summary column headers
echo '<td style="width: 30px; font-weight: bold;">IL</td>';
echo '<td style="width: 30px; font-weight: bold;">Total</td>';
echo '<td style="width: 30px; font-weight: bold;">Per 100</td>';
echo '</tr>';

// Row 2: Date information
echo '<tr class="header-blue">';
echo '<td></td>'; // Empty cell
echo '<td></td>'; // Empty cell
echo '<td>Date ==>-2025</td>';

// Add dates
foreach ($attendance_dates as $date) {
    $formatted_date = date('d-M-Y', strtotime($date));
    echo '<td colspan="1">' . $formatted_date . '</td>';
}

// Add summary headers
echo '<td rowspan="3" class="header-brown">IL Count</td>';
echo '<td colspan="2" class="header-black">Over All</td>';
echo '</tr>';

// Row 3: Day information
echo '<tr class="header-blue">';
echo '<td></td>'; // Empty cell
echo '<td></td>'; // Empty cell
echo '<td>Day ==> day</td>';

// Add day names
foreach ($attendance_dates as $date) {
    $day_name = date('l', strtotime($date));
    echo '<td>' . $day_name . '</td>';
}
echo '</tr>';

// Row 4: Faculty information
echo '<tr class="header-blue">';
echo '<td></td>'; // Empty cell
echo '<td></td>'; // Empty cell
echo '<td>Faculty ==></td>';

// Add faculty name across all date columns
foreach ($attendance_dates as $date) {
    echo '<td>' . htmlspecialchars($class['teacher_name']) . '</td>';
}
echo '</tr>';

// Row 5: Student Name header
echo '<tr class="header-blue">';
echo '<td></td>'; // Empty cell
echo '<td>Student Name / Sub ==></td>';
echo '<td>Session ==></td>';

// Add session type across all date columns
foreach ($attendance_dates as $date) {
    echo '<td class="header-yellow">Session</td>';
}
echo '</tr>';

// Student rows
foreach ($students as $index => $student) {
    echo '<tr>';
    echo '<td>' . ($index + 1) . '</td>'; // Row number
    echo '<td class="student-name">' . htmlspecialchars($student['name']) . '</td>';
    echo '<td class="header-yellow">Session</td>';
    
    // Add attendance data for each date
    foreach ($attendance_dates as $date) {
        $status = isset($attendance_data[$student['id']][$date]) ? $attendance_data[$student['id']][$date] : '';
        $status_display = '';
        
        switch ($status) {
            case 'present':
                $status_display = 'P';
                $cell_class = 'present';
                break;
            case 'absent':
                $status_display = 'A';
                $cell_class = 'absent';
                break;
            case 'late':
                $status_display = 'L';
                $cell_class = 'late';
                break;
            default:
                $status_display = '';
                $cell_class = 'attendance-cell';
        }
        
        echo '<td class="' . $cell_class . '">' . $status_display . '</td>';
    }
    
    // Calculate summary data
    $total_days = count($attendance_dates);
    $present_days = 0;
    $absent_days = 0;
    $late_days = 0;
    
    foreach ($attendance_dates as $date) {
        $status = isset($attendance_data[$student['id']][$date]) ? $attendance_data[$student['id']][$date] : '';
        switch ($status) {
            case 'present':
                $present_days++;
                break;
            case 'absent':
                $absent_days++;
                break;
            case 'late':
                $late_days++;
                break;
        }
    }
    
    $attendance_percentage = $total_days > 0 ? round(($present_days / $total_days) * 100, 2) : 0;
    
    // Summary columns
    echo '<td class="summary-cell">' . $absent_days . '</td>'; // IL Count (absent days)
    echo '<td class="summary-cell">' . $present_days . '</td>'; // Total present days
    echo '<td class="summary-cell">' . $attendance_percentage . '%</td>'; // Percentage
    echo '</tr>';
}

// Add summary footer
echo '<tr style="background-color: #F0F0F0; font-weight: bold;">';
echo '<td colspan="3">TOTAL STUDENTS</td>';
echo '<td colspan="' . count($attendance_dates) . '">' . count($students) . '</td>';
echo '<td colspan="3">SUMMARY</td>';
echo '</tr>';

echo '<tr style="background-color: #F0F0F0;">';
echo '<td colspan="3">LEGEND</td>';
echo '<td colspan="' . count($attendance_dates) . '">P = Present, A = Absent, L = Late</td>';
echo '<td colspan="3">Generated on: ' . date('Y-m-d H:i:s') . '</td>';
echo '</tr>';

echo '</table>';
echo '</body>';
echo '</html>';
exit();
?>
