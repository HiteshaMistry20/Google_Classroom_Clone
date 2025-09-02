<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
try {
    // Total enrolled classes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM class_enrollments WHERE student_id = ?");
    $stmt->execute([$user_id]);
    $total_classes = $stmt->fetch()['total'];
    
    // Total assignments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM assignments a 
        JOIN class_enrollments e ON a.class_id = e.class_id 
        WHERE e.student_id = ?
    ");
    $stmt->execute([$user_id]);
    $total_assignments = $stmt->fetch()['total'];
    
    // Pending assignments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM assignments a 
        JOIN class_enrollments e ON a.class_id = e.class_id 
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
        WHERE e.student_id = ? AND s.id IS NULL AND a.due_date > NOW()
    ");
    $stmt->execute([$user_id, $user_id]);
    $pending_assignments = $stmt->fetch()['total'];
    
    // Average grade
    $stmt = $pdo->prepare("
        SELECT AVG(s.grade) as average_grade 
        FROM submissions s 
        WHERE s.student_id = ? AND s.grade IS NOT NULL
    ");
    $stmt->execute([$user_id]);
    $average_grade = $stmt->fetch()['average_grade'];
    $average_grade = $average_grade ? round($average_grade, 1) : 0;
    
    // Recent assignments with due dates
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as class_name, u.name as teacher_name,
               s.status as submission_status, s.grade, s.submitted_at,
               DATEDIFF(a.due_date, NOW()) as days_remaining
        FROM assignments a 
        JOIN class_enrollments e ON a.class_id = e.class_id
        JOIN classes c ON a.class_id = c.id
        JOIN users u ON a.teacher_id = u.id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
        WHERE e.student_id = ? AND (s.id IS NULL OR s.status != 'graded')
        ORDER BY a.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $user_id]);
    $recent_assignments = $stmt->fetchAll();
    
    // Recent grades with class performance
    $stmt = $pdo->prepare("
        SELECT s.*, a.title as assignment_title, c.name as class_name,
               (SELECT AVG(grade) FROM submissions WHERE assignment_id = s.assignment_id) as class_average
        FROM submissions s 
        JOIN assignments a ON s.assignment_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE s.student_id = ? AND s.grade IS NOT NULL
        ORDER BY s.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_grades = $stmt->fetchAll();
    
    // Get upcoming events (assignments, announcements, etc.)
    $stmt = $pdo->prepare("
        SELECT 'assignment' as type, a.title as title, a.due_date as date,
               c.name as class_name, NULL as description
        FROM assignments a
        JOIN class_enrollments e ON a.class_id = e.class_id
        JOIN classes c ON a.class_id = c.id
        WHERE e.student_id = ? AND a.due_date > NOW()
        UNION ALL
        SELECT 'announcement' as type, ann.title, ann.created_at as date,
               c.name as class_name, ann.content as description
        FROM announcements ann
        JOIN classes c ON ann.class_id = c.id
        JOIN class_enrollments e ON c.id = e.class_id
        WHERE e.student_id = ? AND ann.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY date ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $user_id]);
    $upcoming_events = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Student dashboard error: " . $e->getMessage());
    $total_classes = $total_assignments = $pending_assignments = $average_grade = 0;
    $recent_assignments = $recent_grades = $upcoming_events = [];
}
?>

<?php $page_title = "Student Dashboard"; ?>
<?php include '../includes/header.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .assignment-item, .grade-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .assignment-item:hover, .grade-item:hover {
            transform: translateX(5px);
        }
        
        .assignment-item h6, .grade-item h6 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .badge {
            padding: 0.5em 1em;
        }
        
        .upcoming-events {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .event-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-left: 4px solid #4285f4;
            background: white;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
            transition: transform 0.2s;
        }
        
        .event-item:hover {
            transform: translateX(5px);
        }
        
        .event-item.announcement {
            border-left-color: #34a853;
        }
        
        .event-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            color: #4285f4;
        }
        
        .event-item.announcement .event-icon {
            color: #34a853;
        }
        
        .progress {
            height: 8px;
            margin-top: 0.5rem;
        }
        
        .quick-actions .btn {
            position: relative;
            overflow: hidden;
        }
        
        .quick-actions .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255,255,255,0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .quick-actions .btn:hover::after {
            animation: ripple 1s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(40, 40);
                opacity: 0;
            }
        }
    </style>
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main>
            <div class="content-area">
                    <!-- Welcome Message -->
                    <div class="mb-4">
                        <h1 class="h3 mb-1">Welcome Back, <?php echo htmlspecialchars($user_name); ?>!</h1>
                        <p class="text-muted">Here's what's happening with your classes today.</p>
                    </div>
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_classes; ?></div>
                        <div class="stat-label">Enrolled Classes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon text-warning">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_assignments; ?></div>
                        <div class="stat-label">Total Assignments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon text-danger">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-number"><?php echo $pending_assignments; ?></div>
                        <div class="stat-label">Pending Assignments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-number"><?php echo $average_grade; ?>%</div>
                        <div class="stat-label">Average Grade</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row quick-actions">
                            <div class="col-md-3">
                                <a href="join_class.php" class="btn btn-primary w-100">
                                    <i class="fas fa-plus-circle"></i>
                                    Join New Class
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="assignments.php?filter=pending" class="btn btn-warning w-100">
                                    <i class="fas fa-tasks"></i>
                                    View Assignments
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="grades.php" class="btn btn-success w-100">
                                    <i class="fas fa-chart-line"></i>
                                    Check Grades
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="materials.php" class="btn btn-info w-100">
                                    <i class="fas fa-book"></i>
                                    Study Materials
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Assignments -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Assignments</h5>
                                <a href="assignments.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_assignments)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-tasks fa-3x mb-3"></i>
                                        <p>No pending assignments!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                        <div class="assignment-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                    <p class="text-muted mb-2">
                                                        <i class="fas fa-chalkboard-teacher me-1"></i>
                                                        <?php echo htmlspecialchars($assignment['class_name']); ?>
                                                    </p>
                                                </div>
                                                <?php
                                                    $badge_class = 'bg-warning';
                                                    $badge_text = 'Pending';
                                                    if ($assignment['submission_status']) {
                                                        $badge_class = 'bg-success';
                                                        $badge_text = 'Submitted';
                                                    } elseif ($assignment['days_remaining'] < 0) {
                                                        $badge_class = 'bg-danger';
                                                        $badge_text = 'Late';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Due: <?php echo formatDateTime($assignment['due_date']); ?>
                                                    <?php if ($assignment['days_remaining'] > 0): ?>
                                                        (<?php echo $assignment['days_remaining']; ?> days left)
                                                    <?php endif; ?>
                                                </small>
                                                <a href="assignments.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Grades -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Grades</h5>
                                <a href="grades.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_grades)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>No grades yet!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_grades as $grade): ?>
                                        <div class="grade-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($grade['assignment_title']); ?></h6>
                                                    <p class="text-muted mb-2">
                                                        <i class="fas fa-chalkboard-teacher me-1"></i>
                                                        <?php echo htmlspecialchars($grade['class_name']); ?>
                                                    </p>
                                                </div>
                                                <?php
                                                    $grade_class = 'bg-danger';
                                                    if ($grade['grade'] >= 90) {
                                                        $grade_class = 'bg-success';
                                                    } elseif ($grade['grade'] >= 70) {
                                                        $grade_class = 'bg-warning';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $grade_class; ?>">
                                                    <?php echo $grade['grade']; ?>%
                                                </span>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Class Average: <?php echo round($grade['class_average'], 1); ?>%</small>
                                                <div class="progress">
                                                    <div class="progress-bar <?php echo $grade_class; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $grade['grade']; ?>%" 
                                                         aria-valuenow="<?php echo $grade['grade']; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Submitted: <?php echo formatDate($grade['submitted_at']); ?>
                                                </small>
                                                <a href="assignments.php?id=<?php echo $grade['assignment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Upcoming Events</h5>
                    </div>
                    <div class="card-body upcoming-events">
                        <?php if (empty($upcoming_events)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-calendar fa-3x mb-3"></i>
                                <p>No upcoming events!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="event-item <?php echo $event['type']; ?>">
                                    <div class="event-icon">
                                        <i class="fas <?php echo $event['type'] === 'assignment' ? 'fa-tasks' : 'fa-bullhorn'; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                <p class="text-muted mb-0">
                                                    <i class="fas fa-chalkboard-teacher me-1"></i>
                                                    <?php echo htmlspecialchars($event['class_name']); ?>
                                                </p>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo formatDate($event['date']); ?>
                                            </small>
                                        </div>
                                        <?php if ($event['description']): ?>
                                            <p class="mb-0 mt-2"><?php echo htmlspecialchars(substr($event['description'], 0, 100)); ?>...</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
