<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a student
requireLogin();
requireRole('student');

$student_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($assignment_id <= 0) {
    setFlashMessage('error', 'Invalid assignment ID.');
    header('Location: assignments.php');
    exit();
}

// Get assignment details and verify student is enrolled
$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name, c.subject, u.name as teacher_name,
           (SELECT COUNT(*) FROM assignment_files af WHERE af.assignment_id = a.id) as file_count
    FROM assignments a 
    JOIN classes c ON a.class_id = c.id 
    JOIN users u ON a.teacher_id = u.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE a.id = ? AND ce.student_id = ? AND c.status = 'active'
");
$stmt->execute([$assignment_id, $student_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or you are not enrolled in this class.');
    header('Location: assignments.php');
    exit();
}

// Get assignment files
$stmt = $pdo->prepare("SELECT * FROM assignment_files WHERE assignment_id = ? ORDER BY file_name");
$stmt->execute([$assignment_id]);
$assignment_files = $stmt->fetchAll();

// Get student's submission
$stmt = $pdo->prepare("
    SELECT s.*, s.allow_resubmission,
           (SELECT COUNT(*) FROM submission_files sf WHERE sf.submission_id = s.id) as file_count
    FROM submissions s 
    WHERE s.assignment_id = ? AND s.student_id = ?
");
$stmt->execute([$assignment_id, $student_id]);
$submission = $stmt->fetch();

// Get late submission request if exists
$late_request = null;
$stmt = $pdo->prepare("
    SELECT * FROM late_submission_requests 
    WHERE assignment_id = ? AND student_id = ?
");
$stmt->execute([$assignment_id, $student_id]);
$late_request = $stmt->fetch();

// Get submission files if exists
$submission_files = [];
if ($submission && isset($submission['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM submission_files WHERE submission_id = ? ORDER BY file_name");
    $stmt->execute([$submission['id']]);
    $submission_files = $stmt->fetchAll();
}

// Calculate if assignment is overdue
if ($assignment) {
    $due_timestamp = strtotime($assignment['due_date']);
    $current_timestamp = time();
    $is_overdue = $due_timestamp < $current_timestamp;
    
    // Debug information (remove this in production)
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-info'>";
        echo "<strong>Debug Info:</strong><br>";
        echo "Due Date: " . $assignment['due_date'] . " (Timestamp: " . $due_timestamp . ")<br>";
        echo "Current Time: " . date('Y-m-d H:i:s') . " (Timestamp: " . $current_timestamp . ")<br>";
        echo "Is Overdue: " . ($is_overdue ? 'Yes' : 'No') . "<br>";
        echo "Allow Late: " . ($assignment['allow_late'] ? 'Yes' : 'No') . "<br>";
        echo "Require Approval: " . ($assignment['require_late_approval'] ? 'Yes' : 'No') . "<br>";
        echo "</div>";
    }
} else {
    $is_overdue = false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment) {
    if (isset($_POST['submit_assignment'])) {
        // Check if assignment is still active
        if ($assignment['status'] !== 'active') {
            setFlashMessage('error', 'This assignment is no longer active and cannot be submitted.');
            header('Location: assignment_details.php?id=' . $assignment_id);
            exit();
        }
        
        // Check if due date has passed and handle late submission logic
        
        if ($is_overdue) {
            if (!$assignment['allow_late']) {
                setFlashMessage('error', 'Assignment submission is closed. The due date has passed and late submissions are not allowed. Due date: ' . formatDateTime($assignment['due_date']) . ' (Current time: ' . formatDateTime(date('Y-m-d H:i:s')) . ')');
                header('Location: assignment_details.php?id=' . $assignment_id);
                exit();
            } elseif ($assignment['require_late_approval']) {
                // Check if student has already requested late submission approval
                $stmt = $pdo->prepare("SELECT * FROM late_submission_requests WHERE assignment_id = ? AND student_id = ?");
                $stmt->execute([$assignment_id, $student_id]);
                $late_request = $stmt->fetch();
                
                if (!$late_request) {
                    setFlashMessage('error', 'This assignment requires faculty approval for late submissions. Please request approval first. Due date: ' . formatDateTime($assignment['due_date']));
                    header('Location: assignment_details.php?id=' . $assignment_id);
                    exit();
                } elseif ($late_request['status'] === 'pending') {
                    setFlashMessage('error', 'Your late submission request is pending faculty approval. Please wait for approval before submitting.');
                    header('Location: assignment_details.php?id=' . $assignment_id);
                    exit();
                } elseif ($late_request['status'] === 'rejected') {
                    setFlashMessage('error', 'Your late submission request has been rejected by the faculty. Due date: ' . formatDateTime($assignment['due_date']));
                    header('Location: assignment_details.php?id=' . $assignment_id);
                    exit();
                } elseif ($late_request['status'] === 'approved') {
                    setFlashMessage('success', 'Your late submission request has been approved. You can now submit your assignment.');
                }
            } else {
                setFlashMessage('warning', 'This assignment is overdue, but late submissions are allowed. Due date: ' . formatDateTime($assignment['due_date']) . ' (Current time: ' . formatDateTime(date('Y-m-d H:i:s')) . ')');
            }
        }
        
        // Check if already submitted and resubmission is not allowed
        if ($submission && !$assignment['allow_resubmission'] && !$submission['allow_resubmission']) {
            setFlashMessage('error', 'You have already submitted this assignment and resubmission is not allowed.');
            header('Location: assignment_details.php?id=' . $assignment_id);
            exit();
        }
        
        // If resubmission is allowed (either globally or per-submission) and already submitted, update existing submission
        if ($submission && ($assignment['allow_resubmission'] || $submission['allow_resubmission'])) {
            // Delete old submission files
            $stmt = $pdo->prepare("DELETE FROM submission_files WHERE submission_id = ?");
            $stmt->execute([$submission['id']]);
            
            // Update submission timestamp
            $stmt = $pdo->prepare("UPDATE submissions SET submitted_at = NOW(), is_late = ? WHERE id = ?");
            $is_late = strtotime($assignment['due_date']) < time();
            $stmt->execute([$is_late ? 1 : 0, $submission['id']]);
            
            $submission_id = $submission['id'];
            setFlashMessage('success', 'Assignment resubmitted successfully!' . ($is_late ? ' (Submitted late)' : ''));
        } else {
            // Create new submission
            $is_late = strtotime($assignment['due_date']) < time();
            $status = $is_late ? 'late' : 'submitted';
            
            // Insert submission
            $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, is_late, status) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$assignment_id, $student_id, $is_late ? 1 : 0, $status])) {
                $submission_id = $pdo->lastInsertId();
                setFlashMessage('success', 'Assignment submitted successfully!' . ($is_late ? ' (Submitted late)' : ''));
            } else {
                setFlashMessage('error', 'Failed to submit assignment. Please try again.');
                header('Location: assignment_details.php?id=' . $assignment_id);
                exit();
            }
        }
        
        // Handle file uploads
        if (isset($_FILES['submission_files']) && !empty($_FILES['submission_files']['name'][0])) {
            $upload_dir = '../uploads/submissions/';
            foreach ($_FILES['submission_files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['submission_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['submission_files']['name'][$key],
                        'type' => $_FILES['submission_files']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['submission_files']['error'][$key],
                        'size' => $_FILES['submission_files']['size'][$key]
                    ];
                    
                    $upload_result = uploadFile($file, $upload_dir);
                    if ($upload_result) {
                        $stmt = $pdo->prepare("INSERT INTO submission_files (submission_id, file_path, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$submission_id, $upload_result['file_path'], $upload_result['file_name'], $upload_result['file_type'], $upload_result['file_size']]);
                    }
                }
            }
        }
        
        // Send comprehensive notifications for assignment submission
        notifyAssignmentSubmitted($pdo, $submission_id);
        
        header('Location: assignment_details.php?id=' . $assignment_id);
        exit();
    }
    
    if (isset($_POST['request_late_submission'])) {
        $reason = sanitizeInput($_POST['reason']);
        
        if (empty($reason)) {
            setFlashMessage('error', 'Please provide a reason for your late submission request.');
            header('Location: assignment_details.php?id=' . $assignment_id);
            exit();
        }
        
        // Check if request already exists
        if ($late_request) {
            setFlashMessage('error', 'You have already submitted a late submission request for this assignment.');
            header('Location: assignment_details.php?id=' . $assignment_id);
            exit();
        }
        
        // Insert late submission request
        $stmt = $pdo->prepare("INSERT INTO late_submission_requests (assignment_id, student_id, reason) VALUES (?, ?, ?)");
        if ($stmt->execute([$assignment_id, $student_id, $reason])) {
            setFlashMessage('success', 'Your late submission request has been submitted. Please wait for faculty approval.');
            
            // Send notification to teacher
            notifyLateSubmissionRequest($pdo, $assignment_id, $student_id);
        } else {
            setFlashMessage('error', 'Failed to submit late submission request. Please try again.');
        }
        
        header('Location: assignment_details.php?id=' . $assignment_id);
        exit();
    }
}

$page_title = "Assignment Details";
include '../includes/header.php';

// Ensure assignment exists before rendering the page
if (!$assignment) {
    echo '<div class="container mt-4">';
    echo '<div class="alert alert-danger">';
    echo '<h4>Assignment Not Found</h4>';
    echo '<p>The assignment you are looking for does not exist or you are not enrolled in this class.</p>';
    echo '<a href="assignments.php" class="btn btn-primary">Back to Assignments</a>';
    echo '</div>';
    echo '</div>';
    include '../includes/footer.php';
    exit();
}
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Assignment Details</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="assignments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Assignments
                    </a>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Assignment Status Alert -->
            <?php if ($is_overdue): ?>
                <div class="alert <?= $assignment['allow_late'] ? 'alert-warning' : 'alert-danger' ?> mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3" style="font-size: 1.5rem;"></i>
                        <div>
                            <h5 class="mb-1">
                                <?php if (!$assignment['allow_late']): ?>
                                    Assignment Overdue - Submissions Closed
                                <?php elseif ($assignment['require_late_approval']): ?>
                                    Assignment Overdue - Faculty Approval Required
                                <?php else: ?>
                                    Assignment Overdue - Late Submissions Allowed
                                <?php endif; ?>
                            </h5>
                            <p class="mb-0">
                                <strong>Due Date:</strong> <?= formatDateTime($assignment['due_date']) ?>
                                <?php if (!$assignment['allow_late']): ?>
                                    <br><strong>Status:</strong> No late submissions are allowed for this assignment.
                                <?php elseif ($assignment['require_late_approval']): ?>
                                    <br><strong>Status:</strong> Late submissions require faculty approval.
                                <?php else: ?>
                                    <br><strong>Status:</strong> You can still submit this assignment, but it will be marked as late.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Assignment Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?= htmlspecialchars($assignment['title']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Description:</h6>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($assignment['description'])) ?></p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Class Information:</h6>
                                    <p><strong>Class:</strong> <?= htmlspecialchars($assignment['class_name']) ?></p>
                                    <p><strong>Subject:</strong> <?= htmlspecialchars($assignment['subject']) ?></p>
                                    <p><strong>Teacher:</strong> <?= htmlspecialchars($assignment['teacher_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Assignment Details:</h6>
                                    <p><strong>Due Date:</strong> 
                                        <span class="<?= strtotime($assignment['due_date']) < time() ? 'text-danger' : '' ?>">
                                            <?= formatDate($assignment['due_date']) ?>
                                        </span>
                                    </p>
                                    <p><strong>Points:</strong> <?= $assignment['max_points'] ?> <?= $assignment['is_graded'] ? '(Graded)' : '(Ungraded)' ?></p>
                                    <p><strong>Status:</strong> 
                                        <?php if (strtotime($assignment['due_date']) < time()): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6>Settings:</h6>
                            <ul class="list-unstyled">
                                <?php if ($assignment['allow_late']): ?>
                                    <li><i class="fas fa-check text-success"></i> Late submissions allowed</li>
                                <?php else: ?>
                                    <li><i class="fas fa-times text-danger"></i> No late submissions</li>
                                <?php endif; ?>
                                <?php if ($assignment['allow_resubmission']): ?>
                                    <li><i class="fas fa-check text-success"></i> Resubmission allowed</li>
                                <?php else: ?>
                                    <li><i class="fas fa-times text-danger"></i> No resubmission</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignment Files -->
            <?php if (!empty($assignment_files)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Assignment Files (<?= count($assignment_files) ?>)</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($assignment_files as $file): ?>
                                <?php if (!empty($file['file_path']) && !empty($file['file_name'])): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-file"></i>
                                            <?= htmlspecialchars($file['file_name']) ?>
                                            <small class="text-muted">(<?= formatFileSize($file['file_size']) ?>)</small>
                                        </div>
                                        <div class="btn-group" role="group">
                                            <?php
                                            // Check if file can be viewed in browser
                                            $viewable_extensions = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                            $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                            $can_view = in_array($file_extension, $viewable_extensions);
                                            ?>
                                            <?php if ($can_view): ?>
                                                <a href="../<?= htmlspecialchars($file['file_path']) ?>" class="btn btn-sm btn-outline-info" target="_blank" title="View file">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                            <a href="../<?= htmlspecialchars($file['file_path']) ?>" class="btn btn-sm btn-outline-primary" download title="Download file">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Submission Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Your Submission</h6>
                </div>
                <div class="card-body">
                    <?php if ($submission && isset($submission['status'])): ?>
                        <div class="alert alert-info">
                            <h6>Submission Status: 
                                <?php if ($submission['status'] === 'graded'): ?>
                                    <span class="badge bg-success">Graded</span>
                                <?php elseif ($submission['status'] === 'late'): ?>
                                    <span class="badge bg-warning">Submitted Late</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Submitted</span>
                                <?php endif; ?>
                            </h6>
                            <p><strong>Submitted:</strong> <?= formatDate($submission['submitted_at']) ?></p>
                            <?php if (isset($submission['is_late']) && $submission['is_late']): ?>
                                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> This assignment was submitted after the due date.</p>
                            <?php endif; ?>
                            <?php if (isset($submission['grade']) && $submission['grade'] !== null): ?>
                                <p><strong>Grade:</strong> <?= $submission['grade'] ?>/<?= $assignment['max_points'] ?></p>
                            <?php endif; ?>
                            <?php if (isset($submission['feedback']) && $submission['feedback']): ?>
                                <p><strong>Feedback:</strong> <?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Submission Files -->
                        <?php if (!empty($submission_files)): ?>
                            <h6>Your Submitted Files:</h6>
                            <div class="list-group">
                                <?php foreach ($submission_files as $file): ?>
                                    <?php if (!empty($file['file_path']) && !empty($file['file_name'])): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-file"></i>
                                                <?= htmlspecialchars($file['file_name']) ?>
                                                <small class="text-muted">(<?= formatFileSize($file['file_size']) ?>)</small>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <?php
                                                // Check if file can be viewed in browser
                                                $viewable_extensions = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                                $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                                $can_view = in_array($file_extension, $viewable_extensions);
                                                ?>
                                                <?php 
                                                // Clean up the file path - remove any relative path components
                                                $cleanPath = basename($file['file_path']);
                                                $fileUrl = '../uploads/submissions/' . $cleanPath;
                                                ?>
                                                <?php if ($can_view): ?>
                                                    <a href="<?= htmlspecialchars($fileUrl) ?>" class="btn btn-sm btn-outline-info" target="_blank" title="View file">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php endif; ?>
                                                <a href="<?= htmlspecialchars($fileUrl) ?>" class="btn btn-sm btn-outline-secondary" download title="Download file">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Comments Section -->
                        <div class="mt-4">
                            <h6>Comments</h6>
                            <div class="row">
                                <!-- Public Comments -->
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><i class="fas fa-comments"></i> Public Comments</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="public-comments-list" class="comments-list">
                                                <!-- Public comments will be loaded here -->
                                            </div>
                                            <div class="mt-3">
                                                <textarea class="form-control" id="public-comment-input" rows="2" placeholder="Add a public comment..."></textarea>
                                                <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addComment('public')">
                                                    <i class="fas fa-paper-plane"></i> Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Private Comments -->
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-secondary text-white">
                                            <h6 class="mb-0"><i class="fas fa-lock"></i> Private Comments</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="private-comments-list" class="comments-list">
                                                <!-- Private comments will be loaded here -->
                                            </div>
                                            <div class="mt-3">
                                                <textarea class="form-control" id="private-comment-input" rows="2" placeholder="Add a private comment..."></textarea>
                                                <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addComment('private')">
                                                    <i class="fas fa-paper-plane"></i> Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h6>No Submission Yet</h6>
                            <p>You haven't submitted this assignment yet.</p>
                            <?php if ($is_overdue && !$assignment['allow_late']): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Submission Closed:</strong> The due date has passed and late submissions are not allowed.
                                    <br><strong>Due date:</strong> <?= formatDateTime($assignment['due_date']) ?>
                                </div>
                            <?php elseif ($is_overdue && $assignment['require_late_approval']): ?>
                                <!-- Late Submission Request Section -->
                                <?php if ($late_request): ?>
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-clock"></i> Late Submission Request Status</h6>
                                        <p><strong>Requested:</strong> <?= formatDateTime($late_request['requested_at']) ?></p>
                                        <p><strong>Reason:</strong> <?= htmlspecialchars($late_request['reason']) ?></p>
                                        <p><strong>Status:</strong> 
                                            <?php if ($late_request['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">Pending Approval</span>
                                            <?php elseif ($late_request['status'] === 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                                <br><button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#submitAssignmentModal">
                                                    <i class="fas fa-upload"></i> Submit Assignment (Late)
                                                </button>
                                            <?php elseif ($late_request['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                                <?php if ($late_request['teacher_response']): ?>
                                                    <br><strong>Teacher Response:</strong> <?= htmlspecialchars($late_request['teacher_response']) ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-exclamation-triangle"></i> Faculty Approval Required</h6>
                                        <p>This assignment requires faculty approval for late submissions.</p>
                                        <p><strong>Due date:</strong> <?= formatDateTime($assignment['due_date']) ?></p>
                                        <?php if ($is_overdue): ?>
                                            <p class="text-danger"><strong>Note:</strong> This assignment is now overdue.</p>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#requestLateSubmissionModal">
                                            <i class="fas fa-paper-plane"></i> Request Late Submission Approval
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($is_overdue && $assignment['allow_late'] && !$assignment['require_late_approval']): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Late Submission:</strong> This assignment is overdue, but late submissions are allowed.
                                    <br><strong>Due date:</strong> <?= formatDateTime($assignment['due_date']) ?>
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitAssignmentModal">
                                    <i class="fas fa-upload"></i> Submit Assignment (Late)
                                </button>
                            <?php elseif (!$is_overdue): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitAssignmentModal">
                                    <i class="fas fa-upload"></i> Submit Assignment
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Resubmission Option -->
                    <?php 
                    $can_resubmit_display = false;
                    if ($submission && ($assignment['allow_resubmission'] || (isset($submission['allow_resubmission']) && $submission['allow_resubmission']))) {
                        if (!$is_overdue) {
                            $can_resubmit_display = true;
                        } elseif ($assignment['allow_late']) {
                            if ($assignment['require_late_approval']) {
                                if ($late_request && $late_request['status'] === 'approved') {
                                    $can_resubmit_display = true;
                                }
                            } else {
                                $can_resubmit_display = true;
                            }
                        }
                    }
                    ?>
                    <?php if ($can_resubmit_display): ?>
                        <div class="alert alert-info mt-3">
                            <h6><i class="fas fa-redo"></i> Resubmission Allowed</h6>
                            <p>
                                <?php if (isset($submission['allow_resubmission']) && $submission['allow_resubmission']): ?>
                                    Your teacher has specifically enabled resubmission for your submission. You can submit updated work.
                                <?php else: ?>
                                    Your teacher allows resubmissions for this assignment. You can submit updated work.
                                <?php endif; ?>
                            </p>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resubmitAssignmentModal">
                                <i class="fas fa-redo"></i> Resubmit Assignment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Submit Assignment Modal -->
<?php 
// Determine if student can submit based on assignment status and settings
$can_submit = false;

if (!$is_overdue) {
    // Assignment is not overdue - can always submit
    $can_submit = true;
} elseif ($assignment['allow_late']) {
    // Assignment is overdue but late submissions are allowed
    if ($assignment['require_late_approval']) {
        // Need to check if late submission request is approved
        if ($late_request && $late_request['status'] === 'approved') {
            $can_submit = true;
        }
    } else {
        // Late submissions allowed without approval
        $can_submit = true;
    }
}
?>
<?php if (!$submission && $can_submit): ?>
<div class="modal fade" id="submitAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6>Assignment: <?= htmlspecialchars($assignment['title']) ?></h6>
                        <p><strong>Due Date:</strong> <?= formatDateTime($assignment['due_date']) ?></p>
                        <?php if (strtotime($assignment['due_date']) < time()): ?>
                            <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> This assignment is overdue, but late submissions are allowed.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="submission_files" class="form-label">Upload Files *</label>
                        <input type="file" class="form-control" name="submission_files[]" id="submission_files" multiple required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar">
                        <div class="form-text">You can select multiple files. Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, Images, Archives (Max 50MB total)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_assignment" class="btn btn-primary">Submit Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Resubmit Assignment Modal -->
<?php 
$can_resubmit = ($assignment['allow_resubmission'] || (isset($submission['allow_resubmission']) && $submission['allow_resubmission'])) && $can_submit;
?>
<?php if ($submission && $can_resubmit): ?>
<div class="modal fade" id="resubmitAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resubmit Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Resubmission Notice</h6>
                        <p><strong>Assignment:</strong> <?= htmlspecialchars($assignment['title']) ?></p>
                        <p><strong>Due Date:</strong> <?= formatDateTime($assignment['due_date']) ?></p>
                        <p class="mb-0"><strong>Warning:</strong> Resubmitting will replace your current submission. Your previous files will be deleted.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="resubmission_files" class="form-label">Upload New Files *</label>
                        <input type="file" class="form-control" name="submission_files[]" id="resubmission_files" multiple required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar">
                        <div class="form-text">You can select multiple files. Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, Images, Archives (Max 50MB total)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_assignment" class="btn btn-warning">Resubmit Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Request Late Submission Modal -->
<?php if ($assignment['require_late_approval'] && !$late_request): ?>
<div class="modal fade" id="requestLateSubmissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Late Submission Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Late Submission Request</h6>
                        <p><strong>Assignment:</strong> <?= htmlspecialchars($assignment['title']) ?></p>
                        <p><strong>Due Date:</strong> <?= formatDateTime($assignment['due_date']) ?></p>
                        <p class="mb-0"><strong>Current Time:</strong> <?= formatDateTime(date('Y-m-d H:i:s')) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Late Submission *</label>
                        <textarea class="form-control" name="reason" id="reason" rows="4" required 
                                  placeholder="Please provide a detailed reason for your late submission request..."></textarea>
                        <div class="form-text">Be specific about why you need additional time. This will help your teacher make a decision.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="request_late_submission" class="btn btn-warning">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Assignment validation
document.addEventListener('DOMContentLoaded', function() {
    // Check if assignment is overdue and late submissions are not allowed
    <?php if ($is_overdue && !$assignment['allow_late']): ?>
    // Disable all submit buttons if assignment is overdue and late submissions not allowed
    const submitButtons = document.querySelectorAll('button[name="submit_assignment"]');
    submitButtons.forEach(button => {
        button.disabled = true;
        button.title = 'Assignment is overdue and late submissions are not allowed';
    });
    
    // Show warning message
    const warningDiv = document.createElement('div');
    warningDiv.className = 'alert alert-danger';
    warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Submission Closed:</strong> This assignment is overdue and late submissions are not allowed.';
    
    // Insert warning before the first form
    const firstForm = document.querySelector('form');
    if (firstForm) {
        firstForm.parentNode.insertBefore(warningDiv, firstForm);
    }
    <?php endif; ?>
    
    // File upload validation
    const fileInputs = ['submission_files', 'resubmission_files'];
    
    fileInputs.forEach(inputId => {
        const fileInput = document.getElementById(inputId);
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
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
        }
    });
    
    // Load comments if submission exists
    <?php if ($submission): ?>
    loadComments();
    <?php endif; ?>
});

// Comments functionality
let currentSubmissionId = <?= ($submission && isset($submission['id'])) ? $submission['id'] : 'null' ?>;

function loadComments() {
    if (!currentSubmissionId) return;
    
    fetch(`../api/submission_comments.php?submission_id=${currentSubmissionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayComments(data.comments);
            } else {
                console.error('Failed to load comments:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading comments:', error);
        });
}

function displayComments(comments) {
    const publicCommentsList = document.getElementById('public-comments-list');
    const privateCommentsList = document.getElementById('private-comments-list');
    
    // Clear existing comments
    publicCommentsList.innerHTML = '';
    privateCommentsList.innerHTML = '';
    
    // Separate comments by type
    const publicComments = comments.filter(comment => comment.comment_type === 'public');
    const privateComments = comments.filter(comment => comment.comment_type === 'private');
    
    // Display public comments
    if (publicComments.length === 0) {
        publicCommentsList.innerHTML = '<p class="text-muted">No public comments yet.</p>';
    } else {
        publicComments.forEach(comment => {
            publicCommentsList.appendChild(createCommentElement(comment));
        });
    }
    
    // Display private comments
    if (privateComments.length === 0) {
        privateCommentsList.innerHTML = '<p class="text-muted">No private comments yet.</p>';
    } else {
        privateComments.forEach(comment => {
            privateCommentsList.appendChild(createCommentElement(comment));
        });
    }
}

function createCommentElement(comment) {
    const commentDiv = document.createElement('div');
    commentDiv.className = 'comment-item mb-3 p-3 border rounded';
    commentDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-2">
                    <strong class="me-2">${comment.user_name}</strong>
                    <small class="text-muted">${formatDate(comment.created_at)}</small>
                    ${comment.comment_type === 'private' ? '<span class="badge bg-secondary ms-2">Private</span>' : ''}
                </div>
                <p class="mb-0">${escapeHtml(comment.content)}</p>
            </div>
            ${comment.user_id == <?= $_SESSION['user_id'] ?> ? `
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="deleteComment(${comment.id})">
                    <i class="fas fa-trash"></i>
                </button>
            ` : ''}
        </div>
    `;
    return commentDiv;
}

function addComment(type) {
    const inputId = type === 'public' ? 'public-comment-input' : 'private-comment-input';
    const input = document.getElementById(inputId);
    const content = input.value.trim();
    
    if (!content) {
        alert('Please enter a comment.');
        return;
    }
    
    if (!currentSubmissionId) {
        alert('No submission found.');
        return;
    }
    
    const commentData = {
        submission_id: currentSubmissionId,
        comment_type: type,
        content: content
    };
    
    fetch('../api/submission_comments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(commentData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadComments(); // Reload comments to show the new one
        } else {
            alert('Failed to add comment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error adding comment:', error);
        alert('Failed to add comment. Please try again.');
    });
}

function deleteComment(commentId) {
    if (!confirm('Are you sure you want to delete this comment?')) {
        return;
    }
    
    fetch(`../api/submission_comments.php?id=${commentId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadComments(); // Reload comments
        } else {
            alert('Failed to delete comment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting comment:', error);
        alert('Failed to delete comment. Please try again.');
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Handle Enter key in comment inputs
document.addEventListener('DOMContentLoaded', function() {
    const publicInput = document.getElementById('public-comment-input');
    const privateInput = document.getElementById('private-comment-input');
    
    if (publicInput) {
        publicInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                addComment('public');
            }
        });
    }
    
    if (privateInput) {
        privateInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                addComment('private');
            }
        });
    }
});
</script>

<style>
.comments-list {
    max-height: 300px;
    overflow-y: auto;
}

.comment-item {
    background-color: #f8f9fa;
    border-left: 3px solid #007bff !important;
}

.comment-item .badge {
    font-size: 0.7em;
}

.comment-item p {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.card-header h6 {
    font-size: 0.9rem;
    margin-bottom: 0;
}

#public-comment-input, #private-comment-input {
    resize: vertical;
    min-height: 60px;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<?php include '../includes/footer.php'; ?>
