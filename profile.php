<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Initialize variables
$user = null;
$stats = [];
$recent_activity = [];
$error_message = null;
$success_message = null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $user_id
        ]);
        $success_message = "Profile updated successfully!";
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = "Failed to update profile. Please try again.";
    }
}

// Get user profile data with role-specific statistics
try {
    // Base user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Initialize default stats
    $stats = [
        'enrolled_classes' => 0,
        'completed_assignments' => 0,
        'average_grade' => 0,
        'active_classes' => 0,
        'total_students' => 0,
        'total_assignments' => 0,
        'pending_reviews' => 0,
        'active_users' => 0,
        'active_teachers' => 0,
        'active_students' => 0,
        'active_classes' => 0
    ];

    // Role-specific statistics
    if ($user['role'] === 'student') {
        // Get student statistics
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE((SELECT COUNT(*) FROM class_enrollments WHERE student_id = ?), 0) as enrolled_classes,
                COALESCE((SELECT COUNT(*) FROM submissions s 
                 JOIN assignments a ON s.assignment_id = a.id 
                 WHERE s.student_id = ? AND s.status = 'graded'), 0) as completed_assignments,
                COALESCE((SELECT ROUND(AVG(grade), 1) 
                 FROM submissions 
                 WHERE student_id = ? AND grade IS NOT NULL), 0) as average_grade
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = array_merge($stats, $result);
        }
        
        // Get recent activity
        $stmt = $pdo->prepare("
            (SELECT 
                'enrollment' as type,
                c.name as title,
                e.enrolled_at as date,
                c.subject as extra_info
             FROM class_enrollments e
             JOIN classes c ON e.class_id = c.id
             WHERE e.student_id = ?
             ORDER BY e.enrolled_at DESC
             LIMIT 5)
            UNION ALL
            (SELECT 
                'submission' as type,
                a.title,
                s.submitted_at as date,
                CONCAT(c.name, ' - ', 
                    CASE 
                        WHEN s.grade IS NOT NULL THEN CONCAT('Grade: ', s.grade, '%')
                        WHEN s.status = 'submitted' THEN 'Pending Review'
                        ELSE s.status
                    END
                ) as extra_info
             FROM submissions s
             JOIN assignments a ON s.assignment_id = a.id
             JOIN classes c ON a.class_id = c.id
             WHERE s.student_id = ?
             ORDER BY s.submitted_at DESC
             LIMIT 5)
            ORDER BY date DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id]);
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($user['role'] === 'teacher') {
        // Get teacher statistics
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE((SELECT COUNT(*) FROM classes WHERE teacher_id = ? AND status = 'active'), 0) as active_classes,
                COALESCE((SELECT COUNT(DISTINCT e.student_id) 
                 FROM class_enrollments e 
                 JOIN classes c ON e.class_id = c.id 
                 WHERE c.teacher_id = ?), 0) as total_students,
                COALESCE((SELECT COUNT(*) FROM assignments a 
                 JOIN classes c ON a.class_id = c.id 
                 WHERE c.teacher_id = ? AND a.status = 'active'), 0) as total_assignments,
                COALESCE((SELECT COUNT(*) 
                 FROM submissions s 
                 JOIN assignments a ON s.assignment_id = a.id 
                 JOIN classes c ON a.class_id = c.id
                 WHERE c.teacher_id = ? AND s.status = 'submitted'), 0) as pending_reviews
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = array_merge($stats, $result);
        }
        
        // Get recent activity
        $stmt = $pdo->prepare("
            (SELECT 
                'class' as type,
                name as title,
                created_at as date,
                CONCAT('New class created - ', subject) as extra_info
             FROM classes 
             WHERE teacher_id = ? AND status = 'active'
             ORDER BY created_at DESC
             LIMIT 5)
            UNION ALL
            (SELECT 
                'assignment' as type,
                a.title,
                a.created_at as date,
                CONCAT(c.name, ' - Due: ', DATE_FORMAT(a.due_date, '%Y-%m-%d')) as extra_info
             FROM assignments a
             JOIN classes c ON a.class_id = c.id
             WHERE c.teacher_id = ? AND a.status = 'active'
             ORDER BY a.created_at DESC
             LIMIT 5)
            ORDER BY date DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id]);
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else { // Admin
        // Get admin statistics
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE((SELECT COUNT(*) FROM users WHERE status = 'active'), 0) as active_users,
                COALESCE((SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'), 0) as active_teachers,
                COALESCE((SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'), 0) as active_students,
                COALESCE((SELECT COUNT(*) FROM classes WHERE status = 'active'), 0) as active_classes
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = array_merge($stats, $result);
        }
        
        // Get recent activity
        $stmt = $pdo->prepare("
            SELECT 
                'user' as type,
                name as title,
                created_at as date,
                CONCAT('New ', role, ' account') as extra_info
            FROM users 
            WHERE role != 'admin' AND status = 'active'
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_message = "Failed to load profile data. Please try again later.";
}

// Include header
$page_title = "My Profile";
include 'includes/header.php';
?>

<style>
    .profile-header {
        background: linear-gradient(135deg, #4285f4, #34a853);
        padding: 3rem 0;
        color: white;
        margin-bottom: 2rem;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: #4285f4;
        margin: 0 auto 1rem;
        border: 4px solid rgba(255,255,255,0.3);
    }
    
    .activity-item {
        padding: 1rem;
        border-left: 3px solid #4285f4;
        background: white;
        margin-bottom: 1rem;
        border-radius: 0 8px 8px 0;
        transition: transform 0.2s;
    }
    
    .activity-item:hover {
        transform: translateX(5px);
    }
    
    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f0f7ff;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4285f4;
        margin-right: 1rem;
    }
    
    .stat-mini-card {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        border: 1px solid #e0e0e0;
    }
    
    .stat-mini-card .number {
        font-size: 1.5rem;
        font-weight: 600;
        color: #4285f4;
        margin: 0.5rem 0;
    }
</style>

<div class="dashboard">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="d-flex align-items-center">
                    <button class="btn btn-link menu-toggle d-md-none">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="h4 mb-0">My Profile</h1>
                </div>
                <div class="ms-auto">
                    <a href="<?php echo $user_role . '/dashboard.php'; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Profile Header -->
            <div class="profile-header text-center">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                </div>
                <h2 class="h3 mb-2"><?php echo htmlspecialchars($user['name']); ?></h2>
                <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Stats -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php if ($user['role'] === 'student'): ?>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-users"></i>
                                            <div class="number"><?php echo $stats['enrolled_classes']; ?></div>
                                            <div class="label">Enrolled Classes</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-tasks"></i>
                                            <div class="number"><?php echo $stats['completed_assignments']; ?></div>
                                            <div class="label">Completed Assignments</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-chart-line"></i>
                                            <div class="number"><?php echo $stats['average_grade']; ?>%</div>
                                            <div class="label">Average Grade</div>
                                        </div>
                                    </div>
                                    <?php elseif ($user['role'] === 'teacher'): ?>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                            <div class="number"><?php echo $stats['active_classes']; ?></div>
                                            <div class="label">Active Classes</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-users"></i>
                                            <div class="number"><?php echo $stats['total_students']; ?></div>
                                            <div class="label">Total Students</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-tasks"></i>
                                            <div class="number"><?php echo $stats['total_assignments']; ?></div>
                                            <div class="label">Total Assignments</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-clock"></i>
                                            <div class="number"><?php echo $stats['pending_reviews']; ?></div>
                                            <div class="label">Pending Reviews</div>
                                        </div>
                                    </div>
                                    <?php elseif ($user['role'] === 'admin'): ?>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-users"></i>
                                            <div class="number"><?php echo $stats['active_users']; ?></div>
                                            <div class="label">Active Users</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                            <div class="number"><?php echo $stats['active_teachers']; ?></div>
                                            <div class="label">Active Teachers</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-user-graduate"></i>
                                            <div class="number"><?php echo $stats['active_students']; ?></div>
                                            <div class="label">Active Students</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-school"></i>
                                            <div class="number"><?php echo $stats['active_classes']; ?></div>
                                            <div class="label">Active Classes</div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-user-circle"></i>
                                            <div class="number"><?php echo ucfirst($user['role']); ?></div>
                                            <div class="label">Account Type</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <input type="text" class="form-control" id="role" 
                                               value="<?php echo ucfirst($user['role']); ?>" readonly>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="joined" class="form-label">Joined Date</label>
                                        <input type="text" class="form-control" id="joined" 
                                               value="<?php echo date('M d, Y', strtotime($user['created_at'])); ?>" readonly>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <?php if (!empty($recent_activity)): ?>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="activity-list">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item d-flex align-items-center">
                                            <div class="activity-icon">
                                                <i class="fas <?php 
                                                    echo match($activity['type']) {
                                                        'enrollment' => 'fa-users',
                                                        'submission' => 'fa-file-alt',
                                                        'class' => 'fa-chalkboard-teacher',
                                                        'assignment' => 'fa-tasks',
                                                        'user' => 'fa-user-plus',
                                                        default => 'fa-circle'
                                                    };
                                                ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <?php 
                                                        echo match($activity['type']) {
                                                            'enrollment' => 'Joined Class',
                                                            'submission' => 'Submitted Assignment',
                                                            'class' => 'Created Class',
                                                            'assignment' => 'Created Assignment',
                                                            'user' => 'New User',
                                                            default => 'Activity'
                                                        };
                                                    ?>
                                                </h6>
                                                <p class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                                    <?php if (isset($activity['extra_info'])): ?>
                                                        - <?php echo htmlspecialchars($activity['extra_info']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                                 </div>
             </div>
         </main>
     </div>

     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     <script src="assets/js/app.js"></script>
 </body>
 </html> 