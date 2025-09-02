<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
try {
    // Total classes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM classes WHERE teacher_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $total_classes = $stmt->fetch()['total'];
    
    // Total students
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id) as total 
        FROM class_enrollments e 
        JOIN classes c ON e.class_id = c.id 
        WHERE c.teacher_id = ?
    ");
    $stmt->execute([$user_id]);
    $total_students = $stmt->fetch()['total'];
    
    // Total assignments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM assignments WHERE teacher_id = ?");
    $stmt->execute([$user_id]);
    $total_assignments = $stmt->fetch()['total'];
    
    // Pending submissions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM submissions s 
        JOIN assignments a ON s.assignment_id = a.id 
        WHERE a.teacher_id = ? AND s.status = 'submitted'
    ");
    $stmt->execute([$user_id]);
    $pending_submissions = $stmt->fetch()['total'];
    
    // Recent classes
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count
        FROM classes c 
        WHERE c.teacher_id = ? AND c.status = 'active'
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_classes = $stmt->fetchAll();
    
    // Recent assignments
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as class_name,
               (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
        FROM assignments a 
        JOIN classes c ON a.class_id = c.id
        WHERE a.teacher_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_assignments = $stmt->fetchAll();
    
    // Get students list
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.profile_image,
               GROUP_CONCAT(c.name SEPARATOR ', ') as enrolled_classes,
               COUNT(DISTINCT c.id) as class_count
        FROM users u
        JOIN class_enrollments e ON u.id = e.student_id
        JOIN classes c ON e.class_id = c.id
        WHERE c.teacher_id = ? AND u.role = 'student' AND u.status = 'active'
        GROUP BY u.id, u.name, u.email, u.profile_image
        ORDER BY u.name
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $students_list = $stmt->fetchAll();
    
    // Handle null enrolled_classes
    foreach ($students_list as &$student) {
        if (empty($student['enrolled_classes'])) {
            $student['enrolled_classes'] = 'No classes';
        }
    }
    
    // Get pending reviews
    $stmt = $pdo->prepare("
        SELECT s.*, a.title as assignment_title, a.due_date,
               c.name as class_name, c.class_code,
               u.name as student_name, u.email as student_email,
               (SELECT COUNT(*) FROM submission_files WHERE submission_id = s.id) as file_count
        FROM submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        JOIN users u ON s.student_id = u.id
        WHERE a.teacher_id = ? AND s.status = 'submitted'
        ORDER BY s.submitted_at ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $pending_reviews = $stmt->fetchAll();
    
    // Handle null values in pending reviews
    foreach ($pending_reviews as &$review) {
        if (empty($review['assignment_title'])) {
            $review['assignment_title'] = 'Untitled Assignment';
        }
        if (empty($review['student_name'])) {
            $review['student_name'] = 'Unknown Student';
        }
        if (empty($review['class_name'])) {
            $review['class_name'] = 'Unknown Class';
        }
    }
    
    // Get assignments with missing submissions
    $stmt = $pdo->prepare("
        SELECT a.id as assignment_id, a.title as assignment_title, a.due_date,
               c.name as class_name, c.id as class_id,
               (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submitted_count,
               (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as total_students,
               (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) - 
               (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as missing_count
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.teacher_id = ? AND a.status = 'active'
        HAVING missing_count > 0
        ORDER BY a.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $assignments_with_missing = $stmt->fetchAll();
    
    // Get students who haven't submitted specific assignments
    $missing_submissions_list = [];
    foreach ($assignments_with_missing as $assignment) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.profile_image,
                   a.title as assignment_title, a.due_date,
                   c.name as class_name
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            JOIN classes c ON ce.class_id = c.id
            JOIN assignments a ON c.id = a.class_id
            WHERE c.teacher_id = ? 
              AND a.id = ? 
              AND u.role = 'student' 
              AND u.status = 'active'
              AND NOT EXISTS (
                  SELECT 1 FROM submissions s 
                  WHERE s.assignment_id = a.id AND s.student_id = u.id
              )
            ORDER BY u.name
            LIMIT 5
        ");
        $stmt->execute([$user_id, $assignment['assignment_id']]);
        $missing_students = $stmt->fetchAll();
        
        foreach ($missing_students as $student) {
            $missing_submissions_list[] = $student;
        }
    }
    
    // Limit the missing submissions list to avoid too many items
    $missing_submissions_list = array_slice($missing_submissions_list, 0, 10);
    
} catch (Exception $e) {
    error_log("Teacher dashboard error: " . $e->getMessage());
    $total_classes = $total_students = $total_assignments = $pending_submissions = 0;
    $recent_classes = $recent_assignments = $students_list = $pending_reviews = $missing_submissions_list = [];
}
?>

<?php $page_title = "Teacher Dashboard"; ?>
<?php include '../includes/header.php'; ?>
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main>
            <div class="content-area">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon text-primary">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_classes; ?></div>
                        <div class="stat-label">My Classes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon text-warning">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_assignments; ?></div>
                        <div class="stat-label">Assignments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon text-danger">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $pending_submissions; ?></div>
                        <div class="stat-label">Pending Reviews</div>
                    </div>
                </div>

                <!-- Submission Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Submission Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary mb-1"><?php echo $pending_submissions; ?></h4>
                                    <p class="text-muted mb-0">Pending Reviews</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-danger mb-1"><?php echo count($missing_submissions_list); ?></h4>
                                    <p class="text-muted mb-0">Missing Submissions</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success mb-1"><?php echo $total_students; ?></h4>
                                    <p class="text-muted mb-0">Total Students</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-warning mb-1"><?php echo $total_assignments; ?></h4>
                                    <p class="text-muted mb-0">Total Assignments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="classes.php?action=create" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-plus"></i> Create Class
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="materials.php?action=create" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-upload"></i> Upload Material
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="assignments.php?action=create" class="btn btn-warning w-100 mb-2">
                                    <i class="fas fa-plus"></i> New Assignment
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="messages.php?action=compose" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-envelope"></i> Send Message
                                </a>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <a href="classes.php" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-chalkboard"></i> View All Classes
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="assignments.php" class="btn btn-outline-warning w-100 mb-2">
                                    <i class="fas fa-tasks"></i> View Assignments
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="attendance.php" class="btn btn-outline-success w-100 mb-2">
                                    <i class="fas fa-calendar-check"></i> Take Attendance
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="notifications.php" class="btn btn-outline-info w-100 mb-2">
                                    <i class="fas fa-bell"></i> View Notifications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students List & Pending Reviews -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">My Students</h5>
                                <a href="classes.php" class="btn btn-sm btn-outline-success">View Classes</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($students_list)): ?>
                                    <p class="text-muted">No students enrolled yet</p>
                                <?php else: ?>
                                    <div class="students-list">
                                        <?php foreach ($students_list as $student): ?>
                                            <div class="student-item">
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-3">
                                                        <?php if ($student['profile_image']): ?>
                                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($student['profile_image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($student['name']); ?>" 
                                                                 class="rounded-circle" width="40" height="40">
                                                        <?php else: ?>
                                                            <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px; background-color: #007bff; color: white;">
                                                                <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="student-info flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h6>
                                                        <p class="text-muted mb-1 small"><?php echo htmlspecialchars($student['email']); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge badge-primary"><?php echo $student['class_count']; ?> classes</span>
                                                            <small class="text-muted"><?php echo htmlspecialchars(substr($student['enrolled_classes'], 0, 30)); ?><?php echo strlen($student['enrolled_classes']) > 30 ? '...' : ''; ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Pending Reviews</h5>
                                <a href="assignments.php" class="btn btn-sm btn-outline-danger">View Assignments</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_reviews)): ?>
                                    <p class="text-muted">No pending reviews</p>
                                <?php else: ?>
                                    <div class="pending-reviews-list">
                                        <?php foreach ($pending_reviews as $submission): ?>
                                            <div class="submission-item">
                                                <a href="view_submissions.php?assignment_id=<?php echo $submission['assignment_id']; ?>" 
                                                   class="text-decoration-none">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="submission-info flex-grow-1">
                                                            <h6 class="text-danger mb-1"><?php echo htmlspecialchars($submission['assignment_title']); ?></h6>
                                                            <p class="text-muted mb-1 small"><?php echo htmlspecialchars($submission['student_name']); ?> - <?php echo htmlspecialchars($submission['class_name']); ?></p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <span class="badge badge-warning"><?php echo $submission['file_count']; ?> files</span>
                                                                <small class="text-muted">Submitted: <?php echo formatDateTime($submission['submitted_at']); ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="submission-status">
                                                            <?php if ($submission['is_late']): ?>
                                                                <span class="badge badge-danger">Late</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-success">On Time</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Missing Submissions</h5>
                                <div>
                                    <a href="assignments.php" class="btn btn-sm btn-outline-warning me-2">View Assignments</a>
                                    <a href="messages.php?action=compose&type=reminder" class="btn btn-sm btn-outline-danger">Send Reminders</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($missing_submissions_list)): ?>
                                    <p class="text-muted">All assignments submitted</p>
                                <?php else: ?>
                                    <div class="missing-submissions-list">
                                        <?php foreach ($missing_submissions_list as $missing): ?>
                                            <div class="missing-item">
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-3">
                                                        <?php if ($missing['profile_image']): ?>
                                                            <img src="../uploads/profiles/<?php echo htmlspecialchars($missing['profile_image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($missing['name']); ?>" 
                                                                 class="rounded-circle" width="40" height="40">
                                                        <?php else: ?>
                                                            <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center" 
                                                                 style="width: 40px; height: 40px; background-color: #dc3545; color: white;">
                                                                <?php echo strtoupper(substr($missing['name'], 0, 1)); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="missing-info flex-grow-1">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($missing['name']); ?></h6>
                                                        <p class="text-muted mb-1 small"><?php echo htmlspecialchars($missing['assignment_title']); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="badge badge-danger">Missing</span>
                                                            <small class="text-muted"><?php echo htmlspecialchars($missing['class_name']); ?></small>
                                                        </div>
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

                <!-- Recent Classes & Assignments -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Classes</h5>
                                <a href="classes.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_classes)): ?>
                                    <p class="text-muted">No classes created yet</p>
                                <?php else: ?>
                                    <?php foreach ($recent_classes as $class): ?>
                                        <div class="class-item">
                                            <a href="class_details.php?id=<?php echo $class['id']; ?>" class="text-decoration-none">
                                                <h6 class="text-primary"><?php echo htmlspecialchars($class['name']); ?></h6>
                                                <p class="text-muted"><?php echo htmlspecialchars($class['subject']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge badge-primary"><?php echo $class['student_count']; ?> students</span>
                                                    <span class="class-code"><?php echo $class['class_code']; ?></span>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Assignments</h5>
                                <a href="assignments.php" class="btn btn-sm btn-outline-warning">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_assignments)): ?>
                                    <p class="text-muted">No assignments created yet</p>
                                <?php else: ?>
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                        <div class="assignment-item">
                                            <a href="view_submissions.php?assignment_id=<?php echo $assignment['id']; ?>" class="text-decoration-none">
                                                <h6 class="text-warning"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                <p class="text-muted"><?php echo htmlspecialchars($assignment['class_name']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="due-date">Due: <?php echo formatDateTime($assignment['due_date']); ?></span>
                                                    <span class="submission-count"><?php echo $assignment['submission_count']; ?> submissions</span>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    
    <style>
        .class-item, .assignment-item, .student-item, .submission-item, .missing-item {
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .class-item:hover, .assignment-item:hover, .student-item:hover, .submission-item:hover, .missing-item:hover {
            background-color: #f8f9fa;
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .class-item a, .assignment-item a, .submission-item a {
            display: block;
            color: inherit;
        }
        
        .class-item a:hover, .assignment-item a:hover, .submission-item a:hover {
            text-decoration: none;
        }
        
        .student-item {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
        }
        
        .submission-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .missing-item {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .avatar-placeholder {
            font-weight: bold;
            font-size: 16px;
        }
        
        .students-list, .pending-reviews-list, .missing-submissions-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .students-list::-webkit-scrollbar, .pending-reviews-list::-webkit-scrollbar, .missing-submissions-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .students-list::-webkit-scrollbar-track, .pending-reviews-list::-webkit-scrollbar-track, .missing-submissions-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .students-list::-webkit-scrollbar-thumb, .pending-reviews-list::-webkit-scrollbar-thumb, .missing-submissions-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .students-list::-webkit-scrollbar-thumb:hover, .pending-reviews-list::-webkit-scrollbar-thumb:hover, .missing-submissions-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        .btn {
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .col-md-4 {
                margin-bottom: 20px;
            }
            
            .students-list, .pending-reviews-list, .missing-submissions-list {
                max-height: 300px;
            }
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
