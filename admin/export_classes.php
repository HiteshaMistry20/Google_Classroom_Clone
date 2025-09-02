<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
requireLogin();
requireRole('admin');

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$teacher_filter = $_GET['teacher'] ?? '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.subject LIKE ? OR c.section LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

if (!empty($teacher_filter)) {
    $where_conditions[] = "c.teacher_id = ?";
    $params[] = $teacher_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get classes data
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        c.section,
        c.subject,
        c.description,
        c.class_code,
        c.status,
        c.created_at,
        u.name as teacher_name,
        u.email as teacher_email,
        (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.id) as student_count,
        (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) as assignment_count,
        (SELECT COUNT(*) FROM materials m WHERE m.class_id = c.id) as material_count
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    WHERE $where_clause
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$classes = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="classes_export_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
fputcsv($output, [
    'ID',
    'Class Name',
    'Section',
    'Subject',
    'Description',
    'Class Code',
    'Status',
    'Teacher Name',
    'Teacher Email',
    'Student Count',
    'Assignment Count',
    'Material Count',
    'Created Date'
]);

// Write data rows
foreach ($classes as $class) {
    fputcsv($output, [
        $class['id'],
        $class['name'],
        $class['section'],
        $class['subject'],
        $class['description'],
        $class['class_code'],
        $class['status'],
        $class['teacher_name'],
        $class['teacher_email'],
        $class['student_count'],
        $class['assignment_count'],
        $class['material_count'],
        $class['created_at']
    ]);
}

fclose($output);
exit();
?>
