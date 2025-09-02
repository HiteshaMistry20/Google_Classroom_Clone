<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get filter parameters
$log_type = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get activity logs
try {
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($log_type !== 'all') {
        $where_conditions[] = "type = ?";
        $params[] = $log_type;
    }
    
    $where_conditions[] = "DATE(created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // For now, we'll create a simulated log from recent activities
    // In a real system, you'd have a dedicated logs table
    $stmt = $pdo->prepare("
        SELECT 
            'user_login' as type,
            CONCAT(u.name, ' logged in') as message,
            u.name as user_name,
            u.role as user_role,
            u.created_at as created_at
        FROM users u 
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'class_created' as type,
            CONCAT('Class \"', c.name, '\" created by ', u.name) as message,
            u.name as user_name,
            u.role as user_role,
            c.created_at as created_at
        FROM classes c 
        JOIN users u ON c.teacher_id = u.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'assignment_created' as type,
            CONCAT('Assignment \"', a.title, '\" created in class \"', c.name, '\"') as message,
            u.name as user_name,
            u.role as user_role,
            a.created_at as created_at
        FROM assignments a 
        JOIN classes c ON a.class_id = c.id
        JOIN users u ON a.teacher_id = u.id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Logs error: " . $e->getMessage());
    $logs = [];
}

$page_title = "Activity Logs";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Activity Logs</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportLogs()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Filter Logs</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="type" class="form-label">Log Type</label>
                                    <select class="form-select" name="type" id="type">
                                        <option value="all" <?= $log_type === 'all' ? 'selected' : '' ?>>All Activities</option>
                                        <option value="user_login" <?= $log_type === 'user_login' ? 'selected' : '' ?>>User Logins</option>
                                        <option value="class_created" <?= $log_type === 'class_created' ? 'selected' : '' ?>>Class Creation</option>
                                        <option value="assignment_created" <?= $log_type === 'assignment_created' ? 'selected' : '' ?>>Assignment Creation</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="date_from" class="form-label">From Date</label>
                                    <input type="date" class="form-control" name="date_from" id="date_from" value="<?= $date_from ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="date_to" class="form-label">To Date</label>
                                    <input type="date" class="form-control" name="date_to" id="date_to" value="<?= $date_to ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Filter</button>
                                        <a href="logs.php" class="btn btn-outline-secondary">Clear</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Activity Logs (<?= count($logs) ?> entries)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No activity logs found for the selected criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped" id="logsTable">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Activity</th>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <small><?= formatDateTime($log['created_at']) ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="log-icon me-2">
                                                        <?php if ($log['type'] === 'user_login'): ?>
                                                            <i class="fas fa-sign-in-alt text-success"></i>
                                                        <?php elseif ($log['type'] === 'class_created'): ?>
                                                            <i class="fas fa-chalkboard text-primary"></i>
                                                        <?php elseif ($log['type'] === 'assignment_created'): ?>
                                                            <i class="fas fa-tasks text-info"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-info-circle text-secondary"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?= htmlspecialchars($log['message']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($log['user_name']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $log['user_role'] === 'teacher' ? 'primary' : ($log['user_role'] === 'student' ? 'success' : 'secondary') ?>">
                                                    <?= ucfirst($log['user_role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $log['type'] === 'user_login' ? 'success' : ($log['type'] === 'class_created' ? 'primary' : 'info') ?>">
                                                    <?= ucwords(str_replace('_', ' ', $log['type'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <i class="fas fa-sign-in-alt fa-2x text-success mb-2"></i>
                            <h5><?= count(array_filter($logs, function($log) { return $log['type'] === 'user_login'; })) ?></h5>
                            <p class="text-muted">User Logins</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <i class="fas fa-chalkboard fa-2x text-primary mb-2"></i>
                            <h5><?= count(array_filter($logs, function($log) { return $log['type'] === 'class_created'; })) ?></h5>
                            <p class="text-muted">Classes Created</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-body text-center">
                            <i class="fas fa-tasks fa-2x text-info mb-2"></i>
                            <h5><?= count(array_filter($logs, function($log) { return $log['type'] === 'assignment_created'; })) ?></h5>
                            <p class="text-muted">Assignments Created</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function exportLogs() {
    const type = document.getElementById('type').value;
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    
    const params = new URLSearchParams();
    if (type !== 'all') params.append('type', type);
    params.append('date_from', dateFrom);
    params.append('date_to', dateTo);
    
    // This would typically open an export URL
    alert('Export functionality coming soon!');
}

// Initialize DataTable
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#logsTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']], // Sort by timestamp
            responsive: true
        });
    }
});
</script>

<style>
.log-icon {
    width: 20px;
    text-align: center;
}

.border-left-primary {
    border-left: 4px solid #4e73df !important;
}

.border-left-success {
    border-left: 4px solid #1cc88a !important;
}

.border-left-info {
    border-left: 4px solid #36b9cc !important;
}

.border-left-warning {
    border-left: 4px solid #f6c23e !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-xs {
    font-size: 0.7rem;
}

.font-weight-bold {
    font-weight: 700 !important;
}

.text-uppercase {
    text-transform: uppercase !important;
}
</style>

<?php include '../includes/footer.php'; ?>
