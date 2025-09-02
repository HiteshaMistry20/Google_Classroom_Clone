<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get dashboard statistics
try {
    // Total users by role
    $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stmt->execute();
    $users_by_role = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_users = array_sum($users_by_role);
    $total_teachers = $users_by_role['teacher'] ?? 0;
    $total_students = $users_by_role['student'] ?? 0;
    
    // Total classes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM classes WHERE status = 'active'");
    $stmt->execute();
    $total_classes = $stmt->fetch()['total'];
    
    // Total assignments
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM assignments");
    $stmt->execute();
    $total_assignments = $stmt->fetch()['total'];
    
    // Pending teacher approvals
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'teacher' AND status = 'pending'");
    $stmt->execute();
    $pending_teachers = $stmt->fetch()['total'];
    
    // Recent activities
    $stmt = $pdo->prepare("
        SELECT 'user' as type, name, created_at, role FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'class' as type, name, created_at, '' as role FROM classes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'assignment' as type, title as name, created_at, '' as role FROM assignments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
    // System health metrics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM submissions WHERE status = 'submitted'");
    $stmt->execute();
    $pending_submissions = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM materials");
    $stmt->execute();
    $total_materials = $stmt->fetch()['total'];
    
    // Recent users
    $stmt = $pdo->prepare("SELECT name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();
    
    // Recent classes
    $stmt = $pdo->prepare("
        SELECT c.name, c.subject, u.name as teacher_name, c.created_at 
        FROM classes c 
        JOIN users u ON c.teacher_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_classes = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $total_users = $total_teachers = $total_students = $total_classes = $total_assignments = $pending_teachers = 0;
    $pending_submissions = $total_materials = 0;
    $recent_activities = $recent_users = $recent_classes = [];
}
?>

<?php $page_title = "Admin Dashboard"; ?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid admin-dashboard">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Enhanced Welcome Section -->
            <div class="admin-welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1>Welcome back, <?= htmlspecialchars($user_name) ?>! 👋</h1>
                        <p>Here's what's happening in your learning management system today.</p>
                    </div>
                    <div class="d-none d-md-block">
                        <button type="button" class="btn btn-light" onclick="window.location.href='reports.php'">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card primary">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon primary me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $total_users ?></div>
                                <div class="admin-stat-label">Total Users</div>
                                <div class="admin-stat-subtitle">
                                    <?= $total_teachers ?> Teachers • <?= $total_students ?> Students
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card success">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon success me-3">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $total_classes ?></div>
                                <div class="admin-stat-label">Active Classes</div>
                                <div class="admin-stat-subtitle">Currently running</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card info">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon info me-3">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $total_assignments ?></div>
                                <div class="admin-stat-label">Assignments</div>
                                <div class="admin-stat-subtitle"><?= $pending_submissions ?> pending submissions</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card warning">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon warning me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $pending_teachers ?></div>
                                <div class="admin-stat-label">Pending Approvals</div>
                                <div class="admin-stat-subtitle">Teacher registrations</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Quick Actions -->
            <div class="admin-quick-actions">
                <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="users.php" class="quick-action-btn">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
                            <small>View and manage all users</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="classes.php" class="quick-action-btn">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Class Management</span>
                            <small>Oversee all classes</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports.php" class="quick-action-btn">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports & Analytics</span>
                            <small>System insights</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="users.php?filter=pending" class="quick-action-btn">
                            <i class="fas fa-user-clock"></i>
                            <span>Pending Approvals</span>
                            <small>Review teacher requests</small>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="content_moderation.php" class="quick-action-btn">
                            <i class="fas fa-shield-alt"></i>
                            <span>Content Moderation</span>
                            <small>Review flagged content</small>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Row -->
            <div class="row">
                <!-- Enhanced Recent Activity -->
                <div class="col-xl-8 col-lg-7">
                    <div class="admin-table-container">
                        <div class="admin-table-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="p-3">
                            <?php if (empty($recent_activities)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <div class="admin-activity-feed">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="admin-activity-item">
                                            <div class="d-flex align-items-center">
                                                <div class="activity-icon <?= $activity['type'] ?> me-3">
                                                    <i class="fas fa-<?= $activity['type'] === 'user' ? 'user' : ($activity['type'] === 'class' ? 'chalkboard' : 'tasks') ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1">
                                                                New <?= ucfirst($activity['type']) ?>
                                                                <?php if ($activity['type'] === 'user' && $activity['role']): ?>
                                                                    <span class="user-role-badge <?= $activity['role'] ?> ms-2"><?= ucfirst($activity['role']) ?></span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <p class="mb-0 text-muted"><?= htmlspecialchars($activity['name']) ?></p>
                                                        </div>
                                                        <small class="text-muted"><?= formatDate($activity['created_at']) ?></small>
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

                <!-- Enhanced Recent Users & Classes -->
                <div class="col-xl-4 col-lg-5">
                    <!-- Recent Users -->
                    <div class="admin-table-container mb-4">
                        <div class="admin-table-header">
                            <h5><i class="fas fa-users me-2"></i>Recent Users</h5>
                        </div>
                        <div class="p-3">
                            <?php if (empty($recent_users)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No recent users</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <div class="admin-user-card">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </div>
                                            <div class="user-info flex-grow-1">
                                                <h6><?= htmlspecialchars($user['name']) ?></h6>
                                                <p><?= htmlspecialchars($user['email']) ?></p>
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="user-role-badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                                                    <small class="text-muted"><?= formatDate($user['created_at']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Classes -->
                    <div class="admin-table-container">
                        <div class="admin-table-header">
                            <h5><i class="fas fa-chalkboard me-2"></i>Recent Classes</h5>
                        </div>
                        <div class="p-3">
                            <?php if (empty($recent_classes)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-chalkboard-teacher fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No recent classes</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_classes as $class): ?>
                                    <div class="admin-user-card">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3" style="background: linear-gradient(135deg, #34a853, #4caf50);">
                                                <i class="fas fa-chalkboard"></i>
                                            </div>
                                            <div class="user-info flex-grow-1">
                                                <h6><?= htmlspecialchars($class['name']) ?></h6>
                                                <p><?= htmlspecialchars($class['subject']) ?></p>
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <small class="text-muted">by <?= htmlspecialchars($class['teacher_name']) ?></small>
                                                    <small class="text-muted"><?= formatDate($class['created_at']) ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
