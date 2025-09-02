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

// Get user profile data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT e.class_id) as enrolled_classes,
               COUNT(DISTINCT s.assignment_id) as completed_assignments,
               AVG(s.grade) as average_grade
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN submissions s ON u.id = s.student_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get recent activity
    $stmt = $pdo->prepare("
        (SELECT 'enrollment' as type, c.name as title, e.enrolled_at as date
         FROM class_enrollments e
         JOIN classes c ON e.class_id = c.id
         WHERE e.student_id = ?)
        UNION ALL
        (SELECT 'submission' as type, a.title, s.submitted_at as date
         FROM submissions s
         JOIN assignments a ON s.assignment_id = a.id
         WHERE s.student_id = ?)
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $user_id]);
    $recent_activity = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error_message = "Failed to load profile data. Please try again later.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, $phone, $user_id])) {
            $_SESSION['user_name'] = $name;
            $success_message = "Profile updated successfully!";
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = array_merge($user, $stmt->fetch());
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = "An error occurred while updating profile.";
    }
}
?>

<?php $page_title = "My Profile"; ?>
<?php include '../includes/header.php'; ?>
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
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Content Area -->
            <div class="content-area">

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
                    <div class="alert alert-success" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
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
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-users"></i>
                                            <div class="number"><?php echo $user['enrolled_classes']; ?></div>
                                            <div class="label">Enrolled Classes</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-tasks"></i>
                                            <div class="number"><?php echo $user['completed_assignments']; ?></div>
                                            <div class="label">Completed Assignments</div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="stat-mini-card">
                                            <i class="fas fa-chart-line"></i>
                                            <div class="number"><?php echo round($user['average_grade'], 1); ?>%</div>
                                            <div class="label">Average Grade</div>
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
                                               value="<?php echo formatDate($user['created_at']); ?>" readonly>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="card-title mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activity)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-history fa-3x mb-3"></i>
                                        <p>No recent activity</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item d-flex align-items-center">
                                            <div class="activity-icon">
                                                <i class="fas <?php echo $activity['type'] === 'enrollment' ? 'fa-users' : 'fa-file-alt'; ?>"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo $activity['type'] === 'enrollment' ? 'Joined Class' : 'Submitted Assignment'; ?>
                                                </h6>
                                                <p class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo formatDate($activity['date']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html> 