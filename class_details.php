<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: frontend/login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get class ID from URL
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$class_id) {
    if ($user_role === 'admin') {
        header('Location: admin/classes.php');
    } elseif ($user_role === 'teacher') {
        header('Location: teacher/classes.php');
    } else {
        header('Location: student/my_classes.php');
    }
    exit();
}

try {
    // Get class details
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name, u.email as teacher_email
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$class_id]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        throw new Exception("Class not found");
    }

    // Check if user has access to this class
    if ($user_role === 'admin') {
        // Admins have access to all classes
    } elseif ($user_role === 'teacher' && $class['teacher_id'] != $user_id) {
        throw new Exception("Access denied");
    } elseif ($user_role === 'student') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$class_id, $user_id]);
        if (!$stmt->fetchColumn()) {
            throw new Exception("You are not enrolled in this class");
        }
    }

    // Get class statistics
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM class_enrollments WHERE class_id = ?) as student_count,
            (SELECT COUNT(*) FROM assignments WHERE class_id = ?) as assignment_count,
            (SELECT COUNT(*) FROM materials WHERE class_id = ?) as material_count,
            (SELECT COUNT(*) FROM announcements WHERE class_id = ?) as announcement_count
    ");
    $stmt->execute([$class_id, $class_id, $class_id, $class_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, ce.enrolled_at
        FROM class_enrollments ce
        JOIN users u ON ce.student_id = u.id
        WHERE ce.class_id = ?
        ORDER BY u.name
    ");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent assignments
    $stmt = $pdo->prepare("
        SELECT a.*, 
               (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as submission_count
        FROM assignments a
        WHERE a.class_id = ?
        ORDER BY a.due_date DESC
        LIMIT 5
    ");
    $stmt->execute([$class_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent materials
    $stmt = $pdo->prepare("
        SELECT *
        FROM materials
        WHERE class_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$class_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent announcements
    $stmt = $pdo->prepare("
        SELECT *
        FROM announcements
        WHERE class_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$class_id]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent attendance (for teachers and admins)
    $recent_attendance = [];
    if ($user_role === 'teacher' || $user_role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT a.*, u.name as student_name
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            WHERE a.class_id = ?
            ORDER BY a.attendance_date DESC, u.name
            LIMIT 10
        ");
        $stmt->execute([$class_id]);
        $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$page_title = $class['name'] ?? "Class Details";
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
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="authenticated">
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main>
            <div class="content-area">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php else: ?>
                    <!-- Class Header -->
                    <div class="class-header mb-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="class-icon me-3">
                                        <i class="fas fa-graduation-cap"></i>
                                    </div>
                                    <div>
                                        <h1 class="class-title"><?php echo htmlspecialchars($class['name']); ?></h1>
                                        <p class="class-subtitle">
                                            <i class="fas fa-book me-2"></i>
                                            <?php echo htmlspecialchars($class['subject']); ?> • Section <?php echo htmlspecialchars($class['section']); ?>
                                        </p>
                                    </div>
                                </div>
                                <p class="class-teacher">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>
                                    Taught by <?php echo htmlspecialchars($class['teacher_name']); ?>
                                </p>
                                <?php if ($class['description']): ?>
                                    <p class="class-description"><?php echo htmlspecialchars($class['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="class-actions">
                                <span class="class-code-badge">
                                    <i class="fas fa-key me-2"></i>
                                    Class Code: <?php echo $class['class_code']; ?>
                                </span>
                                <?php if ($user_role === 'teacher'): ?>
                                    <a href="teacher/classes.php?action=edit&id=<?php echo $class['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i>Edit Class
                                    </a>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <a href="admin/classes.php?action=edit&id=<?php echo $class['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i>Edit Class
                                    </a>
                                    <a href="admin/classes.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Classes
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="stats-grid mb-4">
                        <div class="stat-card">
                            <div class="stat-icon text-primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['student_count']; ?></div>
                            <div class="stat-label">Students</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon text-success">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['assignment_count']; ?></div>
                            <div class="stat-label">Assignments</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon text-info">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['material_count']; ?></div>
                            <div class="stat-label">Materials</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon text-warning">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['announcement_count']; ?></div>
                            <div class="stat-label">Announcements</div>
                        </div>
                    </div>

                    <!-- Main Content Grid -->
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-lg-8">
                            <!-- Recent Assignments -->
                            <div class="content-section mb-4">
                                <div class="section-header">
                                    <h5><i class="fas fa-tasks me-2"></i>Recent Assignments</h5>
                                    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                        <a href="teacher/assignments.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i>Create Assignment
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="section-content">
                                    <?php if (empty($assignments)): ?>
                                        <p class="text-muted">No assignments yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <div class="assignment-item">
                                                <div class="assignment-info">
                                                    <h6><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                                    <div class="assignment-meta">
                                                        <span class="due-date">
                                                            <i class="fas fa-calendar-alt me-1"></i>
                                                            Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                        </span>
                                                        <span class="submissions">
                                                            <i class="fas fa-file-upload me-1"></i>
                                                            <?php echo $assignment['submission_count']; ?> submissions
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="assignment-actions">
                                                    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                                        <a href="teacher/assignments.php?action=view&id=<?php echo $assignment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="student/assignments.php?action=view&id=<?php echo $assignment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Recent Materials -->
                            <div class="content-section mb-4">
                                <div class="section-header">
                                    <h5><i class="fas fa-file-alt me-2"></i>Recent Materials</h5>
                                    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                        <a href="teacher/materials.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i>Add Material
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="section-content">
                                    <?php if (empty($materials)): ?>
                                        <p class="text-muted">No materials yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($materials as $material): ?>
                                            <div class="material-item">
                                                <div class="material-icon">
                                                    <i class="fas fa-file"></i>
                                                </div>
                                                <div class="material-info">
                                                    <h6><?php echo htmlspecialchars($material['title']); ?></h6>
                                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($material['description']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M d, Y', strtotime($material['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="material-actions">
                                                    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                                        <a href="teacher/materials.php?action=view&id=<?php echo $material['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="student/materials.php?action=view&id=<?php echo $material['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-eye me-1"></i>View
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-lg-4">
                            <!-- Class Information -->
                            <div class="content-section mb-4">
                                <div class="section-header">
                                    <h5><i class="fas fa-info-circle me-2"></i>Class Information</h5>
                                </div>
                                <div class="section-content">
                                    <div class="info-item">
                                        <strong>Class Code:</strong>
                                        <span class="class-code"><?php echo $class['class_code']; ?></span>
                                        <button class="btn btn-sm btn-outline-secondary copy-btn" data-copy="<?php echo $class['class_code']; ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <div class="info-item">
                                        <strong>Subject:</strong>
                                        <span><?php echo htmlspecialchars($class['subject']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Section:</strong>
                                        <span><?php echo htmlspecialchars($class['section']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Teacher:</strong>
                                        <span><?php echo htmlspecialchars($class['teacher_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Created:</strong>
                                        <span><?php echo date('M d, Y', strtotime($class['created_at'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Status:</strong>
                                        <span class="badge badge-<?php echo $class['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($class['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Announcements -->
                            <div class="content-section mb-4">
                                <div class="section-header">
                                    <h5><i class="fas fa-bullhorn me-2"></i>Recent Announcements</h5>
                                    <?php if ($user_role === 'teacher' || $user_role === 'admin'): ?>
                                        <a href="teacher/announcements.php?class_id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i>New
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="section-content">
                                    <?php if (empty($announcements)): ?>
                                        <p class="text-muted">No announcements yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="announcement-item">
                                                <h6><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                <p class="text-muted"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Enrolled Students -->
                            <div class="content-section">
                                <div class="section-header">
                                    <h5><i class="fas fa-users me-2"></i>Enrolled Students (<?php echo count($students); ?>)</h5>
                                </div>
                                <div class="section-content">
                                    <?php if (empty($students)): ?>
                                        <p class="text-muted">No students enrolled yet.</p>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <div class="student-item">
                                                <div class="student-avatar">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div class="student-info">
                                                    <h6><?php echo htmlspecialchars($student['name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Copy class code functionality
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const textToCopy = this.getAttribute('data-copy');
                navigator.clipboard.writeText(textToCopy).then(() => {
                    // Show success feedback
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-secondary');
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html> 