<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Validate class ownership
$stmt = $pdo->prepare("SELECT c.*, 
                              (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as total_students
                       FROM classes c 
                       WHERE c.id = ? AND c.teacher_id = ?");
$stmt->execute([$class_id, $teacher_id]);
$class = $stmt->fetch();

if (!$class) {
    setFlashMessage('error', 'Class not found or access denied.');
    header('Location: assignments.php');
    exit();
}

// Get assignments with analytics
$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted') as submitted_count,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'graded') as graded_count,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.is_late = 1) as late_count,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.grade IS NOT NULL) as graded_submissions_count,
           (SELECT AVG(s.grade) FROM submissions s WHERE s.assignment_id = a.id AND s.grade IS NOT NULL) as average_grade
    FROM assignments a 
    JOIN classes c ON a.class_id = c.id 
    WHERE a.class_id = ? AND c.teacher_id = ? 
    ORDER BY a.due_date ASC
");
$stmt->execute([$class_id, $teacher_id]);
$assignments = $stmt->fetchAll();

// Get detailed student submission data
$stmt = $pdo->prepare("
    SELECT 
        u.id as student_id,
        u.name as student_name,
        u.email as student_email,
        a.id as assignment_id,
        a.title as assignment_title,
        s.id as submission_id,
        s.submitted_at,
        s.is_late,
        s.grade,
        s.status,
        s.feedback
    FROM class_enrollments ce
    JOIN users u ON ce.student_id = u.id
    JOIN assignments a ON a.class_id = ce.class_id
    LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = u.id
    WHERE ce.class_id = ?
    ORDER BY u.name, a.due_date
");
$stmt->execute([$class_id]);
$student_submissions = $stmt->fetchAll();

if ($format === 'excel') {
    // Export as Excel (CSV format)
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="assignment_analytics_' . $class['name'] . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['Assignment Analytics Report - ' . $class['name']]);
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Students: ' . $class['total_students']]);
    fputcsv($output, ['Total Assignments: ' . count($assignments)]);
    fputcsv($output, []);
    
    // Write assignment summary
    fputcsv($output, ['Assignment Summary']);
    fputcsv($output, ['Title', 'Due Date', 'Total Students', 'Submitted', 'Not Submitted', 'Late', 'Graded', 'Submission Rate (%)', 'Average Grade']);
    
    foreach ($assignments as $assignment) {
        $total_students = $class['total_students'];
        $submitted_count = $assignment['submitted_count'];
        $not_submitted = $total_students - $submitted_count;
        $submission_rate = $total_students > 0 ? round(($submitted_count / $total_students) * 100, 1) : 0;
        $average_grade = $assignment['average_grade'] ? round($assignment['average_grade'], 1) : 'N/A';
        
        fputcsv($output, [
            $assignment['title'],
            $assignment['due_date'],
            $total_students,
            $submitted_count,
            $not_submitted,
            $assignment['late_count'],
            $assignment['graded_count'],
            $submission_rate,
            $average_grade
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['Detailed Student Submissions']);
    fputcsv($output, ['Student Name', 'Student Email', 'Assignment', 'Due Date', 'Submitted', 'Submission Date', 'Late', 'Grade', 'Status']);
    
    foreach ($student_submissions as $submission) {
        fputcsv($output, [
            $submission['student_name'],
            $submission['student_email'],
            $submission['assignment_title'],
            $submission['submitted_at'] ? 'Yes' : 'No',
            $submission['submitted_at'] ? $submission['submitted_at'] : 'N/A',
            $submission['is_late'] ? 'Yes' : 'No',
            $submission['grade'] ? $submission['grade'] : 'N/A',
            $submission['status'] ?: 'Not Submitted'
        ]);
    }
    
    fclose($output);
    
} elseif ($format === 'pdf') {
    // For PDF export, we'll create a simple HTML report that can be printed
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Assignment Analytics - <?= htmlspecialchars($class['name']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .summary { margin-bottom: 30px; }
            .summary table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .summary th, .summary td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .summary th { background-color: #f2f2f2; }
            .student-table { margin-bottom: 30px; }
            .student-table table { width: 100%; border-collapse: collapse; }
            .student-table th, .student-table td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 12px; }
            .student-table th { background-color: #f2f2f2; }
            .stats { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .stat-box { text-align: center; padding: 10px; border: 1px solid #ddd; flex: 1; margin: 0 5px; }
            .stat-number { font-size: 24px; font-weight: bold; }
            .stat-label { font-size: 12px; color: #666; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Assignment Analytics Report</h1>
            <h2><?= htmlspecialchars($class['name']) ?></h2>
            <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
        </div>
        
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()">Print Report</button>
            <button onclick="window.close()">Close</button>
        </div>
        
        <div class="summary">
            <h3>Class Summary</h3>
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?= $class['total_students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?= count($assignments) ?></div>
                    <div class="stat-label">Total Assignments</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">
                        <?php 
                        $total_submitted = array_sum(array_column($assignments, 'submitted_count'));
                        echo $total_submitted;
                        ?>
                    </div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">
                        <?php 
                        $total_late = array_sum(array_column($assignments, 'late_count'));
                        echo $total_late;
                        ?>
                    </div>
                    <div class="stat-label">Late Submissions</div>
                </div>
            </div>
            
            <h4>Assignment Summary</h4>
            <table>
                <thead>
                    <tr>
                        <th>Assignment</th>
                        <th>Due Date</th>
                        <th>Submitted</th>
                        <th>Not Submitted</th>
                        <th>Late</th>
                        <th>Graded</th>
                        <th>Submission Rate</th>
                        <th>Average Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php 
                        $total_students = $class['total_students'];
                        $submitted_count = $assignment['submitted_count'];
                        $not_submitted = $total_students - $submitted_count;
                        $submission_rate = $total_students > 0 ? round(($submitted_count / $total_students) * 100, 1) : 0;
                        $average_grade = $assignment['average_grade'] ? round($assignment['average_grade'], 1) : 'N/A';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($assignment['title']) ?></td>
                            <td><?= date('M d, Y', strtotime($assignment['due_date'])) ?></td>
                            <td><?= $submitted_count ?></td>
                            <td><?= $not_submitted ?></td>
                            <td><?= $assignment['late_count'] ?></td>
                            <td><?= $assignment['graded_count'] ?></td>
                            <td><?= $submission_rate ?>%</td>
                            <td><?= $average_grade ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="student-table">
            <h3>Student Submission Details</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Assignment</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Late</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($student_submissions as $submission): ?>
                        <tr>
                            <td><?= htmlspecialchars($submission['student_name']) ?></td>
                            <td><?= htmlspecialchars($submission['assignment_title']) ?></td>
                            <td><?= $submission['submission_id'] ? 'Submitted' : 'Not Submitted' ?></td>
                            <td><?= $submission['submitted_at'] ? date('M d, Y H:i', strtotime($submission['submitted_at'])) : 'N/A' ?></td>
                            <td><?= $submission['is_late'] ? 'Yes' : 'No' ?></td>
                            <td><?= $submission['grade'] ? $submission['grade'] : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
} else {
    setFlashMessage('error', 'Invalid export format.');
    header('Location: assignments.php?class_id=' . $class_id);
    exit();
}
?>
