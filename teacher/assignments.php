<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_assignment'])) {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $class_id = (int)$_POST['class_id'];
        $due_date = $_POST['due_date'];
        $due_time = $_POST['due_time'];
        $max_points = (int)$_POST['max_points'];
        $is_graded = isset($_POST['is_graded']) ? 1 : 0;
        $allow_late = isset($_POST['allow_late']) ? 1 : 0;
        $allow_resubmission = isset($_POST['allow_resubmission']) ? 1 : 0;
        $require_late_approval = isset($_POST['require_late_approval']) ? 1 : 0;
        
        // Validate class ownership
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ? AND status = 'active'");
        $stmt->execute([$class_id, $teacher_id]);
        if (!$stmt->fetch()) {
            setFlashMessage('error', 'Invalid class selected.');
            header('Location: assignments.php');
            exit();
        }
        
        // Combine date and time
        $due_datetime = $due_date . ' ' . $due_time;
        
        // Insert assignment into database
        $stmt = $pdo->prepare("INSERT INTO assignments (class_id, teacher_id, title, description, due_date, max_points, is_graded, allow_late, allow_resubmission, require_late_approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$class_id, $teacher_id, $title, $description, $due_datetime, $max_points, $is_graded, $allow_late, $allow_resubmission, $require_late_approval])) {
            $assignment_id = $pdo->lastInsertId();
            
            // Handle file uploads
            if (isset($_FILES['assignment_files']) && !empty($_FILES['assignment_files']['name'][0])) {
                $upload_dir = '../uploads/assignments/';
                foreach ($_FILES['assignment_files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['assignment_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['assignment_files']['name'][$key],
                            'type' => $_FILES['assignment_files']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $_FILES['assignment_files']['error'][$key],
                            'size' => $_FILES['assignment_files']['size'][$key]
                        ];
                        
                        $upload_result = uploadFile($file, $upload_dir);
                        if ($upload_result) {
                            $stmt = $pdo->prepare("INSERT INTO assignment_files (assignment_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$assignment_id, $upload_result['file_path'], $upload_result['file_name'], $upload_result['file_type'], $upload_result['file_size']]);
                        }
                    }
                }
            }
            
            // Send comprehensive notifications for new assignment
            notifyNewAssignment($pdo, $assignment_id, $class_id);
            
            $success_message = 'Assignment created successfully!';
            if (isset($_FILES['assignment_files']) && !empty($_FILES['assignment_files']['name'][0])) {
                $success_message .= ' Assignment files are now available for students to download.';
            }
            setFlashMessage('success', $success_message);
        } else {
            setFlashMessage('error', 'Failed to create assignment. Please try again.');
        }
        
        header('Location: assignments.php?class_id=' . $class_id);
        exit();
    }
    
    if (isset($_POST['edit_assignment'])) {
        $assignment_id = (int)$_POST['assignment_id'];
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $class_id = (int)$_POST['class_id'];
        $due_date = $_POST['due_date'];
        $due_time = $_POST['due_time'];
        $max_points = (int)$_POST['max_points'];
        $is_graded = isset($_POST['is_graded']) ? 1 : 0;
        $allow_late = isset($_POST['allow_late']) ? 1 : 0;
        $allow_resubmission = isset($_POST['allow_resubmission']) ? 1 : 0;
        $require_late_approval = isset($_POST['require_late_approval']) ? 1 : 0;
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT a.*, c.teacher_id FROM assignments a JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND c.teacher_id = ?");
        $stmt->execute([$assignment_id, $teacher_id]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) {
            setFlashMessage('error', 'Assignment not found or access denied.');
            header('Location: assignments.php?class_id=' . $class_id);
            exit();
        }
        
        // Combine date and time
        $due_datetime = $due_date . ' ' . $due_time;
        
        // Update assignment in database
        $stmt = $pdo->prepare("UPDATE assignments SET title = ?, description = ?, due_date = ?, max_points = ?, is_graded = ?, allow_late = ?, allow_resubmission = ?, require_late_approval = ? WHERE id = ?");
        if ($stmt->execute([$title, $description, $due_datetime, $max_points, $is_graded, $allow_late, $allow_resubmission, $require_late_approval, $assignment_id])) {
            // Handle new file uploads if provided
            if (isset($_FILES['assignment_files']) && !empty($_FILES['assignment_files']['name'][0])) {
                $upload_dir = '../uploads/assignments/';
                foreach ($_FILES['assignment_files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['assignment_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['assignment_files']['name'][$key],
                            'type' => $_FILES['assignment_files']['type'][$key],
                            'tmp_name' => $tmp_name,
                            'error' => $_FILES['assignment_files']['error'][$key],
                            'size' => $_FILES['assignment_files']['size'][$key]
                        ];
                        
                        $upload_result = uploadFile($file, $upload_dir);
                        if ($upload_result) {
                            $stmt = $pdo->prepare("INSERT INTO assignment_files (assignment_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$assignment_id, $upload_result['file_path'], $upload_result['file_name'], $upload_result['file_type'], $upload_result['file_size']]);
                        }
                    }
                }
            }
            
            setFlashMessage('success', 'Assignment updated successfully!');
        } else {
            setFlashMessage('error', 'Failed to update assignment. Please try again.');
        }
        
        header('Location: assignments.php?class_id=' . $class_id);
        exit();
    }
    
    if (isset($_POST['delete_assignment'])) {
        $assignment_id = (int)$_POST['assignment_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT a.*, c.teacher_id FROM assignments a JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND c.teacher_id = ?");
        $stmt->execute([$assignment_id, $teacher_id]);
        $assignment = $stmt->fetch();
        
        if ($assignment) {
            // Delete assignment files
            $stmt = $pdo->prepare("SELECT file_path FROM assignment_files WHERE assignment_id = ?");
            $stmt->execute([$assignment_id]);
            $files = $stmt->fetchAll();
            
            foreach ($files as $file) {
                if (file_exists('../' . $file['file_path'])) {
                    unlink('../' . $file['file_path']);
                }
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM assignment_files WHERE assignment_id = ?");
            $stmt->execute([$assignment_id]);
            
            $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
            if ($stmt->execute([$assignment_id])) {
                setFlashMessage('success', 'Assignment deleted successfully!');
            } else {
                setFlashMessage('error', 'Failed to delete assignment.');
            }
        } else {
            setFlashMessage('error', 'Assignment not found or access denied.');
        }
        
        header('Location: assignments.php?class_id=' . $class_id);
        exit();
    }
}

// Get teacher's classes
$stmt = $pdo->prepare("SELECT id, name, subject FROM classes WHERE teacher_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Get assignments for selected class with detailed analytics
$assignments = [];
$class_analytics = null;
if ($class_id > 0) {
    // Get class information and total enrolled students
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as total_students
        FROM classes c 
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$class_id, $teacher_id]);
    $class_analytics = $stmt->fetch();
    
    if ($class_analytics) {
        // Get assignments with detailed submission analytics
        $stmt = $pdo->prepare("
            SELECT a.*, c.name as class_name,
                   (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count,
                   (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND (s.status = 'submitted' OR s.status = 'graded')) as submitted_count,
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
        
        // Calculate overall analytics
        $total_assignments = count($assignments);
        $total_submissions = 0;
        $total_submitted = 0;
        $total_graded = 0;
        $total_late = 0;
        
        foreach ($assignments as $assignment) {
            $total_submissions += $assignment['submission_count'];
            $total_submitted += $assignment['submitted_count'];
            $total_graded += $assignment['graded_count'];
            $total_late += $assignment['late_count'];
        }
        
        $class_analytics['total_assignments'] = $total_assignments;
        $class_analytics['total_submissions'] = $total_submissions;
        $class_analytics['total_submitted'] = $total_submitted;
        $class_analytics['total_graded'] = $total_graded;
        $class_analytics['total_late'] = $total_late;
        $class_analytics['overall_submission_rate'] = ($class_analytics['total_students'] > 0 && $total_assignments > 0) ? 
            round(($total_submitted / ($class_analytics['total_students'] * $total_assignments)) * 100, 1) : 0;
    }
}

$page_title = "Assignments Management";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Assignments Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                        <i class="fas fa-plus"></i> Create Assignment
                    </button>
                    <?php if ($class_id > 0 && $class_analytics): ?>
                    <div class="btn-group ms-2">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="export_assignment_analytics.php?class_id=<?= $class_id ?>&format=pdf">
                                <i class="fas fa-file-pdf"></i> Export as PDF
                            </a></li>
                            <li><a class="dropdown-item" href="export_assignment_analytics.php?class_id=<?= $class_id ?>&format=excel">
                                <i class="fas fa-file-excel"></i> Export as Excel
                            </a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Class Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="classSelect" class="form-label">Select Class</label>
                    <select class="form-select" id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                        <option value="">Choose a class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($class_id > 0 && $class_analytics): ?>
                <!-- Class Analytics Dashboard -->
                <div class="row mb-4">
                    <!-- Quick Summary Card -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <div class="h2 text-primary mb-2"><?= $class_analytics['overall_submission_rate'] ?>%</div>
                                <h6 class="card-title">Overall Submission Rate</h6>
                                <p class="card-text text-muted">
                                    <?= $class_analytics['total_submitted'] ?> of <?= $class_analytics['total_students'] * $class_analytics['total_assignments'] ?> expected submissions
                                </p>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $class_analytics['overall_submission_rate'] ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending Grading Card -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <?php 
                                $pending_grading = $class_analytics['total_submitted'] - $class_analytics['total_graded'];
                                $grading_rate = $class_analytics['total_submitted'] > 0 ? 
                                    round(($class_analytics['total_graded'] / $class_analytics['total_submitted']) * 100, 1) : 0;
                                ?>
                                <div class="h2 text-warning mb-2"><?= $pending_grading ?></div>
                                <h6 class="card-title">Pending Grading</h6>
                                <p class="card-text text-muted">
                                    <?= $grading_rate ?>% of submissions graded
                                </p>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?= $grading_rate ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Late Submissions Card -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <div class="h2 text-danger mb-2"><?= $class_analytics['total_late'] ?></div>
                                <h6 class="card-title">Late Submissions</h6>
                                <p class="card-text text-muted">
                                    <?= $class_analytics['total_submitted'] > 0 ? round(($class_analytics['total_late'] / $class_analytics['total_submitted']) * 100, 1) : 0 ?>% of total submissions
                                </p>
                                <div class="progress" style="height: 8px;">
                                    <?php $late_rate = $class_analytics['total_submitted'] > 0 ? ($class_analytics['total_late'] / $class_analytics['total_submitted']) * 100 : 0; ?>
                                    <div class="progress-bar bg-danger" style="width: <?= $late_rate ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Analytics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar"></i> Detailed Analytics - <?= htmlspecialchars($class_analytics['name']) ?>
                                </h5>
                                <small class="text-muted analytics-timestamp">Last updated: <?= date('M d, Y H:i:s') ?></small>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Overall Statistics -->
                                    <div class="col-md-3 mb-3">
                                        <div class="text-center">
                                            <div class="h4 text-primary mb-1"><?= $class_analytics['total_students'] ?></div>
                                            <div class="text-muted">Total Students</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="text-center">
                                            <div class="h4 text-success mb-1"><?= $class_analytics['total_assignments'] ?></div>
                                            <div class="text-muted">Total Assignments</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="text-center">
                                            <div class="h4 text-info mb-1"><?= $class_analytics['total_submitted'] ?></div>
                                            <div class="text-muted">Total Submissions</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="text-center">
                                            <div class="h4 text-warning mb-1"><?= $class_analytics['total_late'] ?></div>
                                            <div class="text-muted">Late Submissions</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Overall Submission Rate -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Overall Submission Rate</strong>
                                            <span class="badge bg-primary"><?= $class_analytics['overall_submission_rate'] ?>%</span>
                                        </div>
                                        <div class="progress mb-3" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: <?= $class_analytics['overall_submission_rate'] ?>%">
                                                <?= $class_analytics['overall_submission_rate'] ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Grading Progress -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Grading Progress</strong>
                                            <span class="badge bg-info"><?= $class_analytics['total_graded'] ?> / <?= $class_analytics['total_submitted'] ?> graded</span>
                                        </div>
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                            $grading_rate = $class_analytics['total_submitted'] > 0 ? 
                                                round(($class_analytics['total_graded'] / $class_analytics['total_submitted']) * 100, 1) : 0;
                                            ?>
                                            <div class="progress-bar bg-info" style="width: <?= $grading_rate ?>%">
                                                <?= $grading_rate ?>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignments List -->
                <div class="row">
                    <?php if (empty($assignments)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No assignments created yet for this class.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="col-12 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1"><?= htmlspecialchars($assignment['title']) ?></h5>
                                                <p class="card-text text-muted"><?= htmlspecialchars($assignment['description']) ?></p>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="view_submissions.php?assignment_id=<?= $assignment['id'] ?>">
                                                        <i class="fas fa-eye"></i> View Submissions
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="viewAssignmentFiles(<?= $assignment['id'] ?>)">
                                                        <i class="fas fa-file"></i> View Files
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="editAssignment(<?= $assignment['id'] ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteAssignment(<?= $assignment['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-2">
                                                    <strong>Due Date:</strong> 
                                                    <span class="<?= strtotime($assignment['due_date']) < time() ? 'text-danger' : '' ?>">
                                                        <?= formatDateTime($assignment['due_date']) ?>
                                                    </span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Points:</strong> 
                                                    <?= $assignment['max_points'] ?> <?= $assignment['is_graded'] ? '(Graded)' : '(Ungraded)' ?>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Settings:</strong>
                                                    <?php if ($assignment['allow_late']): ?>
                                                        <span class="badge bg-warning">Late Allowed</span>
                                                    <?php endif; ?>
                                                    <?php if ($assignment['allow_resubmission']): ?>
                                                        <span class="badge bg-info">Resubmission Allowed</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <?php 
                                                $total_students = $class_analytics['total_students'];
                                                $submitted_count = $assignment['submitted_count'];
                                                $not_submitted = $total_students - $submitted_count;
                                                $submission_rate = $total_students > 0 ? round(($submitted_count / $total_students) * 100, 1) : 0;
                                                $grading_rate = $submitted_count > 0 ? round(($assignment['graded_count'] / $submitted_count) * 100, 1) : 0;
                                                $average_grade = $assignment['average_grade'] ? round($assignment['average_grade'], 1) : 0;
                                                ?>
                                                
                                                <!-- Submission Analytics -->
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong>Submission Status</strong>
                                                        <span class="badge bg-primary"><?= $submission_rate ?>%</span>
                                                    </div>
                                                    <div class="row text-center">
                                                        <div class="col-4">
                                                            <div class="h6 text-success mb-0"><?= $submitted_count ?></div>
                                                            <small class="text-muted">Submitted</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="h6 text-danger mb-0"><?= $not_submitted ?></div>
                                                            <small class="text-muted">Not Submitted</small>
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="h6 text-warning mb-0"><?= $assignment['late_count'] ?></div>
                                                            <small class="text-muted">Late</small>
                                                        </div>
                                                    </div>
                                                    <div class="progress mt-2" style="height: 10px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $submission_rate ?>%" title="Submitted: <?= $submitted_count ?>"></div>
                                                        <div class="progress-bar bg-warning" style="width: <?= $assignment['late_count'] > 0 ? ($assignment['late_count'] / $total_students) * 100 : 0 ?>%" title="Late: <?= $assignment['late_count'] ?>"></div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Grading Analytics -->
                                                <?php if ($assignment['is_graded']): ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <strong>Grading Progress</strong>
                                                        <span class="badge bg-info"><?= $grading_rate ?>%</span>
                                                    </div>
                                                    <div class="progress mb-2" style="height: 8px;">
                                                        <div class="progress-bar bg-info" style="width: <?= $grading_rate ?>%"></div>
                                                    </div>
                                                    <?php if ($average_grade > 0): ?>
                                                    <div class="text-center">
                                                        <small class="text-muted">Average Grade: <strong><?= $average_grade ?>/<?= $assignment['max_points'] ?></strong></small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Student Submission Breakdown -->
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#studentBreakdown<?= $assignment['id'] ?>" aria-expanded="false">
                                                <i class="fas fa-users"></i> View Student Breakdown
                                            </button>
                                            <div class="collapse mt-2" id="studentBreakdown<?= $assignment['id'] ?>">
                                                <div class="card card-body bg-light">
                                                    <?php
                                                    // Get detailed student submission data for this assignment
                                                    $stmt = $pdo->prepare("
                                                        SELECT 
                                                            u.id as student_id,
                                                            u.name as student_name,
                                                            u.email as student_email,
                                                            s.id as submission_id,
                                                            s.submitted_at,
                                                            s.is_late,
                                                            s.grade,
                                                            s.status,
                                                            s.feedback
                                                        FROM class_enrollments ce
                                                        JOIN users u ON ce.student_id = u.id
                                                        LEFT JOIN submissions s ON s.assignment_id = ? AND s.student_id = u.id
                                                        WHERE ce.class_id = ?
                                                        ORDER BY u.name
                                                    ");
                                                    $stmt->execute([$assignment['id'], $class_id]);
                                                    $student_submissions = $stmt->fetchAll();
                                                    ?>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6 class="text-success">
                                                                <i class="fas fa-check-circle"></i> Submitted (<?= $submitted_count ?>)
                                                            </h6>
                                                            <div class="list-group list-group-flush">
                                                                <?php foreach ($student_submissions as $student): ?>
                                                                    <?php if ($student['submission_id']): ?>
                                                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                                            <div>
                                                                                <strong><?= htmlspecialchars($student['student_name']) ?></strong>
                                                                                <br>
                                                                                <small class="text-muted">
                                                                                    Submitted: <?= formatDateTime($student['submitted_at']) ?>
                                                                                    <?php if ($student['is_late']): ?>
                                                                                        <span class="badge bg-warning ms-1">Late</span>
                                                                                    <?php endif; ?>
                                                                                </small>
                                                                            </div>
                                                                            <div class="text-end">
                                                                                <?php if ($student['grade'] !== null): ?>
                                                                                    <span class="badge bg-info"><?= $student['grade'] ?>/<?= $assignment['max_points'] ?></span>
                                                                                <?php else: ?>
                                                                                    <span class="badge bg-secondary">Not Graded</span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <h6 class="text-danger">
                                                                <i class="fas fa-times-circle"></i> Not Submitted (<?= $not_submitted ?>)
                                                            </h6>
                                                            <div class="list-group list-group-flush">
                                                                <?php foreach ($student_submissions as $student): ?>
                                                                    <?php if (!$student['submission_id']): ?>
                                                                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                                            <div>
                                                                                <strong><?= htmlspecialchars($student['student_name']) ?></strong>
                                                                                <br>
                                                                                <small class="text-muted"><?= htmlspecialchars($student['student_email']) ?></small>
                                                                            </div>
                                                                            <div>
                                                                                <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                                                    <span class="badge bg-danger">Overdue</span>
                                                                                <?php else: ?>
                                                                                    <span class="badge bg-warning">Pending</span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please select a class to view and manage assignments.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal fade" id="createAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="createAssignmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_id" class="form-label">Class *</label>
                        <select class="form-select" name="class_id" id="class_id" required>
                            <option value="">Select a class...</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Assignment Title *</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date *</label>
                                <input type="date" class="form-control" name="due_date" id="due_date" required>
                                <div class="form-text">Select the date when the assignment is due</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="due_time" class="form-label">Due Time *</label>
                                <input type="time" class="form-control" name="due_time" id="due_time" required>
                                <div class="form-text">Select the time when the assignment is due (24-hour format)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_points" class="form-label">Max Points</label>
                                <input type="number" class="form-control" name="max_points" id="max_points" value="100" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_graded" id="is_graded" checked>
                                    <label class="form-check-label" for="is_graded">
                                        This is a graded assignment
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allow_late" id="allow_late">
                                    <label class="form-check-label" for="allow_late">
                                        Allow late submissions
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allow_resubmission" id="allow_resubmission">
                                    <label class="form-check-label" for="allow_resubmission">
                                        Allow resubmission
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="require_late_approval" id="require_late_approval" checked>
                                    <label class="form-check-label" for="require_late_approval">
                                        Require faculty approval for late submissions
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assignment_files" class="form-label">Assignment Files (Optional)</label>
                        <input type="file" class="form-control" name="assignment_files[]" id="assignment_files" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">You can select multiple files. Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, Images (Max 50MB total)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_assignment" class="btn btn-primary">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editAssignmentForm">
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    <div class="mb-3">
                        <label for="edit_class_id" class="form-label">Class *</label>
                        <select class="form-select" name="class_id" id="edit_class_id" required>
                            <option value="">Select a class...</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class['id'] ?>">
                                    <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Assignment Title *</label>
                        <input type="text" class="form-control" name="title" id="edit_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="4"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_due_date" class="form-label">Due Date *</label>
                                <input type="date" class="form-control" name="due_date" id="edit_due_date" required>
                                <div class="form-text">Select the date when the assignment is due</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_due_time" class="form-label">Due Time *</label>
                                <input type="time" class="form-control" name="due_time" id="edit_due_time" required>
                                <div class="form-text">Select the time when the assignment is due (24-hour format)</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_max_points" class="form-label">Max Points</label>
                                <input type="number" class="form-control" name="max_points" id="edit_max_points" value="100" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_graded" id="edit_is_graded">
                                    <label class="form-check-label" for="edit_is_graded">
                                        This is a graded assignment
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allow_late" id="edit_allow_late">
                                    <label class="form-check-label" for="edit_allow_late">
                                        Allow late submissions
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allow_resubmission" id="edit_allow_resubmission">
                                    <label class="form-check-label" for="edit_allow_resubmission">
                                        Allow resubmission
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="require_late_approval" id="edit_require_late_approval">
                                    <label class="form-check-label" for="edit_require_late_approval">
                                        Require faculty approval for late submissions
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_assignment_files" class="form-label">Additional Assignment Files (Optional)</label>
                        <input type="file" class="form-control" name="assignment_files[]" id="edit_assignment_files" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif">
                        <div class="form-text">You can select multiple files. These will be added to existing files. Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, Images (Max 50MB total)</div>
                    </div>
                    
                    <div id="current_files_info" class="alert alert-info" style="display: none;">
                        <strong>Current Files:</strong>
                        <ul id="current_files_list"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_assignment" class="btn btn-primary">Update Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Assignment Files Modal -->
<div class="modal fade" id="viewAssignmentFilesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assignment Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="assignmentFilesList">
                    <!-- Files will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this assignment? This will also delete all student submissions and cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="assignment_id" id="deleteAssignmentId">
                    <button type="submit" name="delete_assignment" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteAssignment(assignmentId) {
    document.getElementById('deleteAssignmentId').value = assignmentId;
    new bootstrap.Modal(document.getElementById('deleteAssignmentModal')).show();
}

function formatFileSize(bytes) {
    if (bytes >= 1073741824) {
        return (bytes / 1073741824).toFixed(2) + ' GB';
    } else if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return bytes + ' bytes';
    }
}

function viewAssignmentFiles(assignmentId) {
    // Fetch assignment files via AJAX
    fetch(`../api/get_assignment.php?id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const filesList = document.getElementById('assignmentFilesList');
                
                if (data.files && data.files.length > 0) {
                    let filesHtml = '<div class="list-group">';
                    data.files.forEach(file => {
                        const fileExtension = file.file_name.split('.').pop().toLowerCase();
                        const canPreview = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'].includes(fileExtension);
                        
                        // Check if file exists (we'll do this on the server side)
                        filesHtml += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">${file.file_name}</h6>
                                        <small class="text-muted">${file.file_type} - ${formatFileSize(file.file_size)}</small>
                                    </div>
                                    <div>
                                        ${canPreview ? `<a href="../${file.file_path}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>` : ''}
                                        <a href="../${file.file_path}" class="btn btn-sm btn-outline-secondary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    filesHtml += '</div>';
                    filesList.innerHTML = filesHtml;
                } else {
                    filesList.innerHTML = '<div class="alert alert-info">No files attached to this assignment.</div>';
                }
                
                // Show the modal
                new bootstrap.Modal(document.getElementById('viewAssignmentFilesModal')).show();
            } else {
                alert('Failed to load assignment files: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load assignment files. Please try again.');
        });
}

function editAssignment(assignmentId) {
    // Fetch assignment data via AJAX
    fetch(`../api/get_assignment.php?id=${assignmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const assignment = data.assignment;
                
                // Populate the edit form
                document.getElementById('edit_assignment_id').value = assignment.id;
                document.getElementById('edit_class_id').value = assignment.class_id;
                document.getElementById('edit_title').value = assignment.title;
                document.getElementById('edit_description').value = assignment.description;
                document.getElementById('edit_max_points').value = assignment.max_points;
                
                // Set due date and time
                const dueDate = new Date(assignment.due_date);
                document.getElementById('edit_due_date').value = dueDate.toISOString().split('T')[0];
                document.getElementById('edit_due_time').value = dueDate.toTimeString().slice(0, 5);
                
                // Set checkboxes
                document.getElementById('edit_is_graded').checked = assignment.is_graded == 1;
                document.getElementById('edit_allow_late').checked = assignment.allow_late == 1;
                document.getElementById('edit_allow_resubmission').checked = assignment.allow_resubmission == 1;
                
                // Show current files if they exist
                const currentFilesInfo = document.getElementById('current_files_info');
                const currentFilesList = document.getElementById('current_files_list');
                
                if (data.files && data.files.length > 0) {
                    currentFilesList.innerHTML = '';
                    data.files.forEach(file => {
                        const li = document.createElement('li');
                        li.innerHTML = `<a href="../${file.file_path}" target="_blank">${file.file_name}</a>`;
                        currentFilesList.appendChild(li);
                    });
                    currentFilesInfo.style.display = 'block';
                } else {
                    currentFilesInfo.style.display = 'none';
                }
                
                // Show the modal
                new bootstrap.Modal(document.getElementById('editAssignmentModal')).show();
            } else {
                alert('Failed to load assignment data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load assignment data. Please try again.');
        });
}

// Set default due date to tomorrow
document.addEventListener('DOMContentLoaded', function() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('due_date').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('due_time').value = '23:59';
    
    // Add validation for due date and time
    const dueDateInput = document.getElementById('due_date');
    const dueTimeInput = document.getElementById('due_time');
    const createForm = document.getElementById('createAssignmentForm');
    
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const selectedDate = dueDateInput.value;
            const selectedTime = dueTimeInput.value;
            
            if (selectedDate && selectedTime) {
                const selectedDateTime = new Date(selectedDate + ' ' + selectedTime);
                const now = new Date();
                
                if (selectedDateTime <= now) {
                    e.preventDefault();
                    alert('Due date and time must be in the future. Please select a future date and time.');
                    return false;
                }
            }
        });
    }
    
    // Add validation for edit form as well
    const editForm = document.getElementById('editAssignmentForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const editDueDate = document.getElementById('edit_due_date');
            const editDueTime = document.getElementById('edit_due_time');
            
            if (editDueDate && editDueTime && editDueDate.value && editDueTime.value) {
                const selectedDateTime = new Date(editDueDate.value + ' ' + editDueTime.value);
                const now = new Date();
                
                if (selectedDateTime <= now) {
                    e.preventDefault();
                    alert('Due date and time must be in the future. Please select a future date and time.');
                    return false;
                }
            }
        });
    }
});

// File upload validation
document.getElementById('assignment_files').addEventListener('change', function(e) {
    const files = e.target.files;
    let totalSize = 0;
    
    for (let i = 0; i < files.length; i++) {
        totalSize += files[i].size;
    }
    
    const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
    if (totalSizeMB > 50) {
        alert('Total file size must be less than 50MB');
        this.value = '';
    }
});

// Edit file upload validation
document.getElementById('edit_assignment_files').addEventListener('change', function(e) {
    const files = e.target.files;
    let totalSize = 0;
    
    for (let i = 0; i < files.length; i++) {
        totalSize += files[i].size;
    }
    
    const totalSizeMB = (totalSize / 1024 / 1024).toFixed(2);
    if (totalSizeMB > 50) {
        alert('Total file size must be less than 50MB');
        this.value = '';
    }
});

// Analytics enhancement functions
function refreshAnalytics() {
    // Reload the page to refresh analytics data
    window.location.reload();
}

function toggleStudentBreakdown(assignmentId) {
    const breakdown = document.getElementById('studentBreakdown' + assignmentId);
    const button = event.target;
    
    if (breakdown.classList.contains('show')) {
        button.innerHTML = '<i class="fas fa-users"></i> View Student Breakdown';
    } else {
        button.innerHTML = '<i class="fas fa-users"></i> Hide Student Breakdown';
    }
}

// Add tooltips to progress bars
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips for progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const percentage = bar.style.width;
        const title = bar.getAttribute('title') || '';
        bar.setAttribute('title', `${title} ${percentage}`);
    });
    
    // Add click handlers for student breakdown buttons
    const breakdownButtons = document.querySelectorAll('[data-bs-toggle="collapse"]');
    breakdownButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-bs-target');
            const assignmentId = targetId.replace('studentBreakdown', '');
            
            // Update button text
            if (this.innerHTML.includes('View')) {
                this.innerHTML = '<i class="fas fa-users"></i> Hide Student Breakdown';
            } else {
                this.innerHTML = '<i class="fas fa-users"></i> View Student Breakdown';
            }
        });
    });
});

// Auto-refresh analytics every 5 minutes
setInterval(function() {
    // Only refresh if the page is visible and user is on assignments page
    if (!document.hidden && window.location.pathname.includes('assignments.php')) {
        // You can implement a more sophisticated refresh here
        // For now, we'll just update timestamps
        const timestamps = document.querySelectorAll('.analytics-timestamp');
        timestamps.forEach(timestamp => {
            timestamp.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
        });
    }
}, 300000); // 5 minutes
</script>

<?php include '../includes/footer.php'; ?>
