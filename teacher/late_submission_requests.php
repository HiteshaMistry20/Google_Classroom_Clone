<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request'])) {
        $request_id = (int)$_POST['request_id'];
        $response = sanitizeInput($_POST['teacher_response']);
        
        // Verify request ownership
        $stmt = $pdo->prepare("
            SELECT lsr.*, a.title as assignment_title, a.teacher_id, u.name as student_name
            FROM late_submission_requests lsr
            JOIN assignments a ON lsr.assignment_id = a.id
            JOIN users u ON lsr.student_id = u.id
            WHERE lsr.id = ? AND a.teacher_id = ?
        ");
        $stmt->execute([$request_id, $teacher_id]);
        $request = $stmt->fetch();
        
        if ($request) {
            $stmt = $pdo->prepare("
                UPDATE late_submission_requests 
                SET status = 'approved', teacher_response = ?, responded_at = NOW(), responded_by = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$response, $teacher_id, $request_id])) {
                setFlashMessage('success', 'Late submission request approved successfully!');
                
                // Send notification to student
                notifyLateSubmissionApproved($pdo, $request_id);
            } else {
                setFlashMessage('error', 'Failed to approve request. Please try again.');
            }
        } else {
            setFlashMessage('error', 'Request not found or access denied.');
        }
        
        header('Location: late_submission_requests.php');
        exit();
    }
    
    if (isset($_POST['reject_request'])) {
        $request_id = (int)$_POST['request_id'];
        $response = sanitizeInput($_POST['teacher_response']);
        
        // Verify request ownership
        $stmt = $pdo->prepare("
            SELECT lsr.*, a.title as assignment_title, a.teacher_id, u.name as student_name
            FROM late_submission_requests lsr
            JOIN assignments a ON lsr.assignment_id = a.id
            JOIN users u ON lsr.student_id = u.id
            WHERE lsr.id = ? AND a.teacher_id = ?
        ");
        $stmt->execute([$request_id, $teacher_id]);
        $request = $stmt->fetch();
        
        if ($request) {
            $stmt = $pdo->prepare("
                UPDATE late_submission_requests 
                SET status = 'rejected', teacher_response = ?, responded_at = NOW(), responded_by = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$response, $teacher_id, $request_id])) {
                setFlashMessage('success', 'Late submission request rejected.');
                
                // Send notification to student
                notifyLateSubmissionRejected($pdo, $request_id);
            } else {
                setFlashMessage('error', 'Failed to reject request. Please try again.');
            }
        } else {
            setFlashMessage('error', 'Request not found or access denied.');
        }
        
        header('Location: late_submission_requests.php');
        exit();
    }
}

// Get late submission requests for teacher's assignments
$stmt = $pdo->prepare("
    SELECT lsr.*, a.title as assignment_title, a.due_date, a.require_late_approval,
           c.name as class_name, u.name as student_name, u.email as student_email,
           DATEDIFF(NOW(), a.due_date) as days_late
    FROM late_submission_requests lsr
    JOIN assignments a ON lsr.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    JOIN users u ON lsr.student_id = u.id
    WHERE a.teacher_id = ?
    ORDER BY lsr.requested_at DESC
");
$stmt->execute([$teacher_id]);
$requests = $stmt->fetchAll();

$page_title = "Late Submission Requests";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Late Submission Requests</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="assignments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Assignments
                    </a>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?= count(array_filter($requests, function($r) { return $r['status'] === 'pending'; })) ?></h4>
                            <p class="mb-0">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?= count(array_filter($requests, function($r) { return $r['status'] === 'approved'; })) ?></h4>
                            <p class="mb-0">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?= count(array_filter($requests, function($r) { return $r['status'] === 'rejected'; })) ?></h4>
                            <p class="mb-0">Rejected</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?= count($requests) ?></h4>
                            <p class="mb-0">Total</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Late Submission Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-success">No Late Submission Requests</h5>
                            <p class="text-muted">All students have submitted their assignments on time!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Assignment</th>
                                        <th>Class</th>
                                        <th>Due Date</th>
                                        <th>Days Late</th>
                                        <th>Requested</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($request['student_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($request['student_email']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($request['assignment_title']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['reason']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($request['class_name']) ?></td>
                                            <td>
                                                <span class="<?= strtotime($request['due_date']) < time() ? 'text-danger' : '' ?>">
                                                    <?= formatDateTime($request['due_date']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?= $request['days_late'] ?> days</span>
                                            </td>
                                            <td><?= formatDateTime($request['requested_at']) ?></td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($request['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif ($request['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#approveRequestModal" 
                                                                data-request-id="<?= $request['id'] ?>"
                                                                data-student-name="<?= htmlspecialchars($request['student_name']) ?>"
                                                                data-assignment-title="<?= htmlspecialchars($request['assignment_title']) ?>">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#rejectRequestModal" 
                                                                data-request-id="<?= $request['id'] ?>"
                                                                data-student-name="<?= htmlspecialchars($request['student_name']) ?>"
                                                                data-assignment-title="<?= htmlspecialchars($request['assignment_title']) ?>">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">
                                                        <?php if ($request['teacher_response']): ?>
                                                            <strong>Response:</strong> <?= htmlspecialchars($request['teacher_response']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Approve Request Modal -->
<div class="modal fade" id="approveRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Late Submission Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="approveRequestId">
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> Approve Request</h6>
                        <p><strong>Student:</strong> <span id="approveStudentName"></span></p>
                        <p><strong>Assignment:</strong> <span id="approveAssignmentTitle"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="approveTeacherResponse" class="form-label">Response to Student (Optional)</label>
                        <textarea class="form-control" name="teacher_response" id="approveTeacherResponse" rows="3" 
                                  placeholder="You can provide a message to the student..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="approve_request" class="btn btn-success">Approve Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Request Modal -->
<div class="modal fade" id="rejectRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Late Submission Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times-circle"></i> Reject Request</h6>
                        <p><strong>Student:</strong> <span id="rejectStudentName"></span></p>
                        <p><strong>Assignment:</strong> <span id="rejectAssignmentTitle"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rejectTeacherResponse" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" name="teacher_response" id="rejectTeacherResponse" rows="3" required 
                                  placeholder="Please provide a reason for rejecting this request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject_request" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal event handlers
document.getElementById('approveRequestModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const requestId = button.getAttribute('data-request-id');
    const studentName = button.getAttribute('data-student-name');
    const assignmentTitle = button.getAttribute('data-assignment-title');
    
    document.getElementById('approveRequestId').value = requestId;
    document.getElementById('approveStudentName').textContent = studentName;
    document.getElementById('approveAssignmentTitle').textContent = assignmentTitle;
});

document.getElementById('rejectRequestModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    const requestId = button.getAttribute('data-request-id');
    const studentName = button.getAttribute('data-student-name');
    const assignmentTitle = button.getAttribute('data-assignment-title');
    
    document.getElementById('rejectRequestId').value = requestId;
    document.getElementById('rejectStudentName').textContent = studentName;
    document.getElementById('rejectAssignmentTitle').textContent = assignmentTitle;
});
</script>

<style>
.card.bg-warning, .card.bg-success, .card.bg-danger, .card.bg-info {
    transition: all 0.3s ease;
}

.card.bg-warning:hover, .card.bg-success:hover, .card.bg-danger:hover, .card.bg-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}
</style>

<?php include '../includes/footer.php'; ?>
