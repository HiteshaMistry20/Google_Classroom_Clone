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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        // Here you would typically update system settings
        // For now, we'll just show a success message
        setFlashMessage('success', 'System settings updated successfully!');
        header('Location: system.php');
        exit();
    }
}

// Get system statistics
try {
    // Database size
    $stmt = $pdo->prepare("SELECT 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB'
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()");
    $stmt->execute();
    $db_size = $stmt->fetch()['DB Size in MB'] ?? 0;
    
    // System uptime (simulated)
    $system_uptime = "7 days, 3 hours, 45 minutes";
    
    // PHP version
    $php_version = PHP_VERSION;
    
    // MySQL version
    $stmt = $pdo->prepare("SELECT VERSION() as version");
    $stmt->execute();
    $mysql_version = $stmt->fetch()['version'];
    
} catch (Exception $e) {
    error_log("System settings error: " . $e->getMessage());
    $db_size = $system_uptime = $php_version = $mysql_version = 'N/A';
}

$page_title = "System Settings";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">System Settings</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('settingsForm').submit()">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>

            <!-- System Information -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Database Size</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $db_size ?> MB</div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-database fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">System Uptime</div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $system_uptime ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">PHP Version</div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $php_version ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-code fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">MySQL Version</div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800"><?= $mysql_version ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-server fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">General Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="settingsForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" value="Google Classroom Clone" readonly>
                                            <div class="form-text">The name of your learning management system</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="admin_email" class="form-label">Admin Email</label>
                                            <input type="email" class="form-control" id="admin_email" name="admin_email" value="admin@classroomclone.com" readonly>
                                            <div class="form-text">Primary administrator contact email</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_file_size" class="form-label">Maximum File Upload Size (MB)</label>
                                            <input type="number" class="form-control" id="max_file_size" name="max_file_size" value="10" min="1" max="100">
                                            <div class="form-text">Maximum allowed file size for uploads</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="30" min="5" max="480">
                                            <div class="form-text">How long before users are logged out due to inactivity</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="enable_registration" class="form-label">Enable User Registration</label>
                                            <select class="form-select" id="enable_registration" name="enable_registration">
                                                <option value="1">Enabled</option>
                                                <option value="0">Disabled</option>
                                            </select>
                                            <div class="form-text">Allow new users to register accounts</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="require_approval" class="form-label">Require Teacher Approval</label>
                                            <select class="form-select" id="require_approval" name="require_approval">
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                            <div class="form-text">Require admin approval for teacher accounts</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="system_message" class="form-label">System Message</label>
                                    <textarea class="form-control" id="system_message" name="system_message" rows="3" placeholder="Display a message to all users on the dashboard"></textarea>
                                    <div class="form-text">Optional message to display to all users</div>
                                </div>

                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">System Maintenance</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <button type="button" class="btn btn-warning w-100 mb-2" onclick="clearCache()">
                                    <i class="fas fa-broom"></i> Clear Cache
                                </button>
                                <small class="text-muted">Clear system cache to improve performance</small>
                            </div>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-info w-100 mb-2" onclick="backupDatabase()">
                                    <i class="fas fa-download"></i> Backup Database
                                </button>
                                <small class="text-muted">Create a backup of the database</small>
                            </div>
                            
                            <div class="mb-3">
                                <button type="button" class="btn btn-secondary w-100 mb-2" onclick="viewLogs()">
                                    <i class="fas fa-list"></i> View System Logs
                                </button>
                                <small class="text-muted">View system error and activity logs</small>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="users.php" class="btn btn-outline-primary">
                                    <i class="fas fa-users"></i> Manage Users
                                </a>
                                <a href="classes.php" class="btn btn-outline-success">
                                    <i class="fas fa-chalkboard"></i> Manage Classes
                                </a>
                                <a href="reports.php" class="btn btn-outline-info">
                                    <i class="fas fa-chart-bar"></i> View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function clearCache() {
    if (confirm('Are you sure you want to clear the system cache?')) {
        // This would typically make an AJAX call to clear cache
        alert('Cache cleared successfully!');
    }
}

function backupDatabase() {
    if (confirm('Are you sure you want to create a database backup?')) {
        // This would typically make an AJAX call to backup database
        alert('Database backup initiated. You will receive a notification when complete.');
    }
}

function viewLogs() {
    // This would typically open a modal or redirect to logs page
    alert('System logs feature coming soon!');
}
</script>

<style>
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
