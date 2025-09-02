<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if (!$assignment_id) {
    header('Location: assignments.php');
    exit();
}

// Verify assignment ownership
$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name, c.teacher_id 
    FROM assignments a 
    JOIN classes c ON a.class_id = c.id 
    WHERE a.id = ? AND c.teacher_id = ?
");
$stmt->execute([$assignment_id, $teacher_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setFlashMessage('error', 'Assignment not found or access denied.');
    header('Location: assignments.php');
    exit();
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $grade = (float)$_POST['grade'];
    $feedback = sanitizeInput($_POST['feedback']);
    $is_late = isset($_POST['is_late']) ? 1 : 0;
    $allow_resubmission = isset($_POST['allow_resubmission']) ? 1 : 0;
    
    if ($grade >= 0 && $grade <= $assignment['max_points']) {
        // Update submission with grade, feedback, and late status
        $stmt = $pdo->prepare("
            UPDATE submissions 
            SET grade = ?, feedback = ?, status = 'graded', is_late = ?
            WHERE id = ? AND assignment_id = ?
        ");
        if ($stmt->execute([$grade, $feedback, $is_late, $submission_id, $assignment_id])) {
            // Send comprehensive notifications for graded assignment
            notifyGradedAssignment($pdo, $submission_id);
            
            setFlashMessage('success', 'Grade submitted successfully!');
            
            // If resubmission is allowed, update assignment settings
            if ($allow_resubmission && $assignment['allow_resubmission']) {
                // This could trigger a notification to the student about resubmission opportunity
                setFlashMessage('info', 'Student can resubmit if needed.');
            }
        } else {
            setFlashMessage('error', 'Failed to submit grade. Please try again.');
        }
    } else {
        setFlashMessage('error', 'Invalid grade. Must be between 0 and ' . $assignment['max_points']);
    }
    
    header('Location: view_submissions.php?assignment_id=' . $assignment_id);
    exit();
}

// Handle resubmission permission toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_resubmission'])) {
    $submission_id = (int)$_POST['submission_id'];
    $enable_resubmission = isset($_POST['enable_resubmission']) ? 1 : 0;
    
    // Get submission details
    $stmt = $pdo->prepare("
        SELECT s.*, a.allow_resubmission as assignment_allow_resubmission 
        FROM submissions s 
        JOIN assignments a ON s.assignment_id = a.id 
        WHERE s.id = ? AND a.id = ?
    ");
    $stmt->execute([$submission_id, $assignment_id]);
    $submission_details = $stmt->fetch();
    
    if ($submission_details) {
        // Update submission to allow resubmission
        $stmt = $pdo->prepare("
            UPDATE submissions 
            SET allow_resubmission = ?, updated_at = NOW()
            WHERE id = ? AND assignment_id = ?
        ");
        if ($stmt->execute([$enable_resubmission, $submission_id, $assignment_id])) {
            if ($enable_resubmission) {
                setFlashMessage('success', 'Resubmission enabled for this student. They will be notified.');
                // Send notification to student about resubmission opportunity
                notifyResubmissionEnabled($pdo, $submission_id);
            } else {
                setFlashMessage('success', 'Resubmission disabled for this student.');
            }
        } else {
            setFlashMessage('error', 'Failed to update resubmission permission.');
        }
    } else {
        setFlashMessage('error', 'Submission not found.');
    }
    
    header('Location: view_submissions.php?assignment_id=' . $assignment_id);
    exit();
}

// Get submissions for this assignment
$stmt = $pdo->prepare("
    SELECT s.*, s.allow_resubmission, u.name as student_name, u.email as student_email,
           (SELECT COUNT(*) FROM submission_files WHERE submission_id = s.id) as file_count
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at ASC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

// Get all enrolled students to show who hasn't submitted
$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email
    FROM users u
    JOIN class_enrollments ce ON u.id = ce.student_id
    WHERE ce.class_id = ?
    ORDER BY u.name
");
$stmt->execute([$assignment['class_id']]);
$enrolled_students = $stmt->fetchAll();

// Create a list of students who haven't submitted
$submitted_student_ids = array_column($submissions, 'student_id');
$missing_submissions = array_filter($enrolled_students, function($student) use ($submitted_student_ids) {
    return !in_array($student['id'], $submitted_student_ids);
});

$page_title = "View Submissions - " . $assignment['title'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Google Classroom Clone</title>
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="authenticated">
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main>
            <div class="content-area">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($assignment['class_name']); ?></p>
                    </div>
                    <a href="assignments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Assignments
                    </a>
                </div>

                <?php displayFlashMessage(); ?>

                <!-- Assignment Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Assignment Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Due Date:</strong> <?php echo formatDateTime($assignment['due_date']); ?></p>
                                <p><strong>Max Points:</strong> <?php echo $assignment['max_points']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Submissions:</strong> <?php echo count($submissions); ?></p>
                                <p><strong>Status:</strong> 
                                    <?php if (strtotime($assignment['due_date']) < time()): ?>
                                        <span class="badge badge-danger">Past Due</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php if ($assignment['description']): ?>
                            <hr>
                            <p><strong>Description:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submissions Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1"><?= count($submissions) ?></h4>
                                <p class="mb-0">Submitted</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1"><?= count($missing_submissions) ?></h4>
                                <p class="mb-0">Missing</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1"><?= count(array_filter($submissions, function($s) { return $s['grade'] !== null; })) ?></h4>
                                <p class="mb-0">Graded</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h4 class="mb-1"><?= count(array_filter($submissions, function($s) { return $s['grade'] === null; })) ?></h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submissions Lists -->
                <div class="row">
                    <!-- Submitted Students -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2 text-success"></i>Submitted Students (<?= count($submissions) ?>)</h5>
                                <span class="badge bg-success"><?= count($submissions) ?></span>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($submissions)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No submissions yet</h5>
                                        <p class="text-muted">Students haven't submitted their work for this assignment.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="submitted-list">
                                        <?php foreach ($submissions as $submission): ?>
                                            <div class="submission-item mb-3 p-3 border rounded">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <div class="avatar me-3">
                                                                <i class="fas fa-user-circle fa-2x text-primary"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($submission['student_name']); ?></h6>
                                                                <small class="text-muted"><?php echo htmlspecialchars($submission['student_email']); ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <small class="text-muted">Submitted: <?php echo formatDateTime($submission['submitted_at']); ?></small>
                                                                <?php if (strtotime($submission['submitted_at']) > strtotime($assignment['due_date'])): ?>
                                                                    <span class="badge bg-danger ms-2">Late</span>
                                                                <?php endif; ?>
                                                                <?php if ($submission['file_count'] > 0): ?>
                                                                    <br><small class="text-muted"><?= $submission['file_count'] ?> file(s) attached</small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-6 text-end">
                                                                <?php if ($submission['grade'] !== null): ?>
                                                                    <span class="badge bg-success">Graded</span>
                                                                    <br><strong><?php echo $submission['grade']; ?>/<?php echo $assignment['max_points']; ?></strong>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning">Pending</span>
                                                                    <br><small class="text-muted">Not graded</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewSubmissionModal" 
                                                                data-submission-id="<?php echo $submission['id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars($submission['student_name']); ?>">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </button>
                                                        <button type="button" class="btn btn-outline-info" 
                                                                onclick="viewComments(<?php echo $submission['id']; ?>, '<?php echo htmlspecialchars($submission['student_name']); ?>')">
                                                            <i class="fas fa-comments me-1"></i>Comments
                                                        </button>
                                                        <?php if ($submission['grade'] === null): ?>
                                                            <button type="button" class="btn btn-outline-success" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#gradeModal" 
                                                                    data-submission-id="<?php echo $submission['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($submission['student_name']); ?>"
                                                                    data-max-points="<?php echo $assignment['max_points']; ?>"
                                                                    data-submitted-at="<?php echo $submission['submitted_at']; ?>"
                                                                    data-is-late="<?php echo (strtotime($submission['submitted_at']) > strtotime($assignment['due_date'])) ? 'true' : 'false'; ?>">
                                                                <i class="fas fa-check me-1"></i>Grade
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-warning" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#gradeModal" 
                                                                    data-submission-id="<?php echo $submission['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($submission['student_name']); ?>"
                                                                    data-max-points="<?php echo $assignment['max_points']; ?>"
                                                                    data-current-grade="<?php echo $submission['grade']; ?>"
                                                                    data-current-feedback="<?php echo htmlspecialchars($submission['feedback'] ?? ''); ?>"
                                                                    data-submitted-at="<?php echo $submission['submitted_at']; ?>"
                                                                    data-is-late="<?php echo (strtotime($submission['submitted_at']) > strtotime($assignment['due_date'])) ? 'true' : 'false'; ?>">
                                                                <i class="fas fa-edit me-1"></i>Edit Grade
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Resubmission Toggle Button -->
                                                        <?php if ($submission['grade'] !== null): ?>
                                                            <button type="button" class="btn <?= $submission['allow_resubmission'] ? 'btn-success' : 'btn-outline-secondary' ?>" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#resubmissionModal" 
                                                                    data-submission-id="<?php echo $submission['id']; ?>"
                                                                    data-student-name="<?php echo htmlspecialchars($submission['student_name']); ?>"
                                                                    data-allow-resubmission="<?php echo $submission['allow_resubmission']; ?>">
                                                                <i class="fas fa-redo me-1"></i><?= $submission['allow_resubmission'] ? 'Resubmission ON' : 'Enable Resubmission' ?>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Missing Students -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Missing Submissions (<?= count($missing_submissions) ?>)</h5>
                                <span class="badge bg-danger"><?= count($missing_submissions) ?></span>
                            </div>
                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($missing_submissions)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h5 class="text-success">All students have submitted!</h5>
                                        <p class="text-muted">Great job! Everyone has turned in their work.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="missing-list">
                                        <?php foreach ($missing_submissions as $student): ?>
                                            <div class="missing-item mb-3 p-3 border rounded bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <div class="avatar me-3">
                                                                <i class="fas fa-user-circle fa-2x text-danger"></i>
                                                            </div>
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h6>
                                                                <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <span class="badge bg-danger">Not Submitted</span>
                                                                <br><small class="text-muted">Due: <?php echo formatDateTime($assignment['due_date']); ?></small>
                                                            </div>
                                                            <div class="col-md-6 text-end">
                                                                <?php if (strtotime($assignment['due_date']) < time()): ?>
                                                                    <span class="badge bg-danger">Overdue</span>
                                                                    <br><small class="text-danger">Past due date</small>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning">Pending</span>
                                                                    <br><small class="text-muted">Still time to submit</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-secondary" disabled>
                                                            <i class="fas fa-clock me-1"></i>Waiting for Submission
                                                        </button>
                                                        <a href="messages.php?action=compose&type=reminder&student_id=<?php echo $student['id']; ?>&assignment_id=<?php echo $assignment_id; ?>" 
                                                           class="btn btn-outline-danger">
                                                            <i class="fas fa-bell me-1"></i>Send Reminder
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

    <!-- View Submission Modal -->
    <div class="modal fade" id="viewSubmissionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="submissionContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading submission...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Submission Modal -->
    <div class="modal fade" id="gradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Grade Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="submission_id" id="gradeSubmissionId">
                        <input type="hidden" name="grade_submission" value="1">
                        
                        <div class="mb-3">
                            <label for="grade" class="form-label">Grade (out of <span id="maxPoints"></span>)</label>
                            <input type="number" class="form-control" id="grade" name="grade" 
                                   min="0" max="" step="0.1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="4" 
                                      placeholder="Provide feedback to the student..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="isLate" name="is_late">
                                <label class="form-check-label" for="isLate">
                                    Mark as Late Submission
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allowResubmission" name="allow_resubmission">
                                <label class="form-check-label" for="allowResubmission">
                                    Allow Student to Resubmit
                                </label>
                                <small class="form-text text-muted">Student can submit updated work before final grading</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Comments Modal -->
    <div class="modal fade" id="commentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Comments - <span id="comments-student-name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
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
                    
                    <!-- All Comments View -->
                    <div class="mt-4">
                        <h6>All Comments</h6>
                        <div id="comments-list" class="comments-list">
                            <!-- All comments will be loaded here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
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
    
    /* New layout styling */
    .submission-item, .missing-item {
        transition: all 0.3s ease;
        border-left: 4px solid #007bff !important;
    }
    
    .submission-item:hover, .missing-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .missing-item {
        border-left-color: #dc3545 !important;
        background-color: #f8d7da !important;
    }
    
    .submission-item {
        border-left-color: #28a745 !important;
        background-color: #f8f9fa !important;
    }
    
    .submitted-list, .missing-list {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .submitted-list::-webkit-scrollbar, .missing-list::-webkit-scrollbar {
        width: 6px;
    }
    
    .submitted-list::-webkit-scrollbar-track, .missing-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .submitted-list::-webkit-scrollbar-thumb, .missing-list::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    
    .submitted-list::-webkit-scrollbar-thumb:hover, .missing-list::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    /* Overview cards styling */
    .card.bg-primary, .card.bg-danger, .card.bg-success, .card.bg-warning {
        transition: all 0.3s ease;
    }
    
    .card.bg-primary:hover, .card.bg-danger:hover, .card.bg-success:hover, .card.bg-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .col-md-6 {
            margin-bottom: 20px;
        }
        
        .submitted-list, .missing-list {
            max-height: 400px;
        }
    }
    </style>
    
    <!-- Resubmission Toggle Modal -->
    <div class="modal fade" id="resubmissionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Resubmission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="submission_id" id="resubmissionSubmissionId">
                        <input type="hidden" name="toggle_resubmission" value="1">
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Resubmission Management</h6>
                            <p class="mb-0">Student: <strong id="resubmissionStudentName"></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enableResubmission" name="enable_resubmission">
                                <label class="form-check-label" for="enableResubmission">
                                    Enable Resubmission for this Student
                                </label>
                                <small class="form-text text-muted">
                                    When enabled, the student will be notified and can submit updated work. 
                                    This will replace their current submission.
                                </small>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Important Notes</h6>
                            <ul class="mb-0">
                                <li>Resubmission will replace the student's current files</li>
                                <li>The student will be notified via email and in-app notification</li>
                                <li>You can disable resubmission at any time</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Resubmission Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Grade modal event handler
        document.getElementById('gradeModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const submissionId = button.getAttribute('data-submission-id');
            const studentName = button.getAttribute('data-student-name');
            const maxPoints = button.getAttribute('data-max-points');
            const currentGrade = button.getAttribute('data-current-grade');
            const currentFeedback = button.getAttribute('data-current-feedback');
            const isLate = button.getAttribute('data-is-late') === 'true';
            
            document.getElementById('gradeSubmissionId').value = submissionId;
            document.getElementById('maxPoints').textContent = maxPoints;
            document.getElementById('grade').max = maxPoints;
            
            // Reset checkboxes
            document.getElementById('isLate').checked = false;
            document.getElementById('allowResubmission').checked = false;
            
            if (currentGrade) {
                document.getElementById('grade').value = currentGrade;
                document.getElementById('feedback').value = currentFeedback;
            } else {
                document.getElementById('grade').value = '';
                document.getElementById('feedback').value = '';
            }
            
            // Check if submission is late based on due date
            const dueDate = new Date('<?= $assignment['due_date'] ?>');
            const submittedAt = new Date(button.getAttribute('data-submitted-at'));
            if (submittedAt > dueDate) {
                document.getElementById('isLate').checked = true;
            }
        });

        // View submission modal event handler
        document.getElementById('viewSubmissionModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const submissionId = button.getAttribute('data-submission-id');
            const studentName = button.getAttribute('data-student-name');
            
            // Update modal title
            document.querySelector('#viewSubmissionModal .modal-title').textContent = `View Submission - ${studentName}`;
            
            // Load submission content
            loadSubmissionContent(submissionId);
        });

        // Resubmission modal event handler
        document.getElementById('resubmissionModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const submissionId = button.getAttribute('data-submission-id');
            const studentName = button.getAttribute('data-student-name');
            const allowResubmission = button.getAttribute('data-allow-resubmission') === '1';
            
            document.getElementById('resubmissionSubmissionId').value = submissionId;
            document.getElementById('resubmissionStudentName').textContent = studentName;
            document.getElementById('enableResubmission').checked = allowResubmission;
        });

        // Function to load submission content
        function loadSubmissionContent(submissionId) {
            const contentDiv = document.getElementById('submissionContent');
            
            // Show loading state
            contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading submission...</p></div>';
            
            fetch(`../api/get_submission.php?submission_id=${submissionId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        const submission = data.submission;
                        contentDiv.innerHTML = generateSubmissionHTML(submission);
                    } else {
                        contentDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load submission'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading submission:', error);
                    contentDiv.innerHTML = `<div class="alert alert-danger">Error loading submission: ${error.message}</div>`;
                });
        }

        // Function to generate submission HTML
        function generateSubmissionHTML(submission) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> ${submission.student_name}</p>
                        <p><strong>Email:</strong> ${submission.student_email}</p>
                        <p><strong>Class:</strong> ${submission.class_name}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Submission Details</h6>
                        <p><strong>Submitted:</strong> ${formatDateTime(submission.submitted_at)}</p>
                        <p><strong>Due Date:</strong> ${formatDateTime(submission.due_date)}</p>
                        ${submission.is_late ? '<p><span class="badge bg-danger">Late Submission</span></p>' : ''}
                        ${submission.grade !== null ? `<p><strong>Grade:</strong> ${submission.grade}/${submission.max_points}</p>` : ''}
                    </div>
                </div>
            `;

            if (submission.feedback) {
                html += `
                    <hr>
                    <h6>Feedback</h6>
                    <div class="alert alert-info">
                        ${submission.feedback}
                    </div>
                `;
            }

            if (submission.files && submission.files.length > 0) {
                html += `
                    <hr>
                    <h6>Submitted Files</h6>
                    <div class="row">
                `;
                
                submission.files.forEach(file => {
                    html += `
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="${file.file_icon} fa-2x me-3"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">${file.file_name}</h6>
                                            <small class="text-muted">${file.file_size_formatted}</small>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="${file.view_url}" class="btn btn-sm btn-primary me-2" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="${file.download_url}" class="btn btn-sm btn-outline-secondary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += `</div>`;
            } else {
                html += `
                    <hr>
                    <h6>Submitted Files</h6>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No files were submitted with this assignment.
                    </div>
                `;
            }

            return html;
        }

        // Function to format date time
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString();
        }



        // Comments functionality
        let currentSubmissionId = null;
        let currentStudentName = '';

        function viewComments(submissionId, studentName) {
            currentSubmissionId = submissionId;
            currentStudentName = studentName;
            document.getElementById('comments-student-name').textContent = studentName;
            loadComments();
            new bootstrap.Modal(document.getElementById('commentsModal')).show();
        }

        function loadComments() {
            if (!currentSubmissionId) return;
            
            fetch(`../api/submission_comments.php?submission_id=${currentSubmissionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayComments(data.comments);
                    } else {
                        console.error('Failed to load comments:', data.message);
                        document.getElementById('comments-list').innerHTML = '<div class="alert alert-danger">Failed to load comments.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    document.getElementById('comments-list').innerHTML = '<div class="alert alert-danger">Error loading comments.</div>';
                });
        }

        function displayComments(comments) {
            const commentsList = document.getElementById('comments-list');
            
            if (comments.length === 0) {
                commentsList.innerHTML = '<div class="alert alert-info">No comments yet.</div>';
                return;
            }
            
            let html = '';
            comments.forEach(comment => {
                const isPrivate = comment.comment_type === 'private';
                const isOwnComment = comment.user_id == <?= $_SESSION['user_id'] ?>;
                
                html += `
                    <div class="comment-item mb-3 p-3 border rounded ${isPrivate ? 'border-secondary' : 'border-primary'}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <strong class="me-2">${comment.user_name}</strong>
                                    <small class="text-muted">${formatDateTime(comment.created_at)}</small>
                                    ${isPrivate ? '<span class="badge bg-secondary ms-2">Private</span>' : '<span class="badge bg-primary ms-2">Public</span>'}
                                    ${comment.user_role === 'teacher' ? '<span class="badge bg-info ms-1">Teacher</span>' : ''}
                                </div>
                                <p class="mb-0">${escapeHtml(comment.content)}</p>
                            </div>
                            ${isOwnComment ? `
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="deleteComment(${comment.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            
            commentsList.innerHTML = html;
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
</body>
</html> 