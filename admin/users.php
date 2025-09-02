<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../frontend/login.html');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $target_user_id = $_POST['user_id'] ?? null;
        
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    $success_message = "User activated successfully!";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    $success_message = "User deactivated successfully!";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
                    $stmt->execute([$target_user_id, $user_id]);
                    $success_message = "User deleted successfully!";
                    break;
                    
                case 'change_role':
                    $new_role = $_POST['new_role'];
                    if (in_array($new_role, ['admin', 'teacher', 'student'])) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND id != ?");
                        $stmt->execute([$new_role, $target_user_id, $user_id]);
                        $success_message = "User role updated successfully!";
                    }
                    break;
            }
        } catch (Exception $e) {
            error_log("User action error: " . $e->getMessage());
            $error_message = "Failed to perform action. Please try again.";
        }
    }
}

// Get users list with statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            CASE 
                WHEN u.role = 'student' THEN (SELECT COUNT(*) FROM class_enrollments WHERE student_id = u.id)
                WHEN u.role = 'teacher' THEN (SELECT COUNT(*) FROM classes WHERE teacher_id = u.id)
                ELSE 0
            END as activity_count
        FROM users u
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Users fetch error: " . $e->getMessage());
    $error_message = "Failed to load users. Please try again.";
    $users = [];
}

// Get statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
            COUNT(CASE WHEN role = 'teacher' THEN 1 END) as teacher_count,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count
        FROM users
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'admin_count' => 0,
        'teacher_count' => 0,
        'student_count' => 0
    ];
}
?>

<?php $page_title = "User Management"; ?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid admin-dashboard">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Enhanced Welcome Section -->
            <div class="admin-welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1>User Management 👥</h1>
                        <p>Manage all users in your learning management system</p>
                    </div>
                    <div class="d-none d-md-block">
                        <button type="button" class="btn btn-light" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </button>
                    </div>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Enhanced Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card primary">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon primary me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?php echo $stats['total_users']; ?></div>
                                <div class="admin-stat-label">Total Users</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card success">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon success me-3">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?php echo $stats['active_users']; ?></div>
                                <div class="admin-stat-label">Active Users</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card warning">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon warning me-3">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?php echo $stats['teacher_count']; ?></div>
                                <div class="admin-stat-label">Teachers</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card info">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon info me-3">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?php echo $stats['student_count']; ?></div>
                                <div class="admin-stat-label">Students</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Users Table -->
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users me-2"></i>User Management</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="admin-search-box" placeholder="Search users..." id="userSearch">
                            <select class="admin-filter-dropdown" id="roleFilter">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Activity</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <?php if ($user['id'] == $user_id): ?>
                                                    <span class="badge bg-primary">You</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo htmlspecialchars($user['email']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['role'])): ?>
                                            <span class="user-role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="user-status-badge <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo $user['activity_count']; ?> activities</span>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?php echo formatDate($user['created_at']); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($user['id'] != $user_id): ?>
                                                <?php if ($user['status'] === 'pending'): ?>
                                                    <button type="button" class="admin-action-btn btn-sm success" onclick="approveUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <button type="button" class="admin-action-btn btn-sm warning" onclick="deactivateUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="admin-action-btn btn-sm success" onclick="activateUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="admin-action-btn btn-sm danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Current User</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// User search and filter functionality
document.getElementById('userSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const name = row.querySelector('td:first-child').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

document.getElementById('roleFilter').addEventListener('change', function() {
    const selectedRole = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const role = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        
        if (!selectedRole || role.includes(selectedRole)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// User action functions
function approveUser(userId) {
    if (confirm('Approve this user?')) {
        submitUserAction('activate', userId);
    }
}

function activateUser(userId) {
    if (confirm('Activate this user?')) {
        submitUserAction('activate', userId);
    }
}

function deactivateUser(userId) {
    if (confirm('Deactivate this user?')) {
        submitUserAction('deactivate', userId);
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        submitUserAction('delete', userId);
    }
}

function submitUserAction(action, userId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="${action}">
        <input type="hidden" name="user_id" value="${userId}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script> 