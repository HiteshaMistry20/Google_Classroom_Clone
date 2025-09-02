<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user settings
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Settings error: " . $e->getMessage());
    $error_message = "Failed to load settings. Please try again later.";
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (!password_verify($current_password, $user['password'])) {
            $error_message = "Current password is incorrect.";
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $user_id])) {
                    $success_message = "Password updated successfully!";
                } else {
                    $error_message = "Failed to update password. Please try again.";
                }
            } catch (Exception $e) {
                error_log("Password update error: " . $e->getMessage());
                $error_message = "An error occurred while updating password.";
            }
        }
    }
}

// Determine the return path based on user role
$return_path = $user_role . '/dashboard.php';

// Include header
$page_title = "Settings";
include 'includes/header.php';
?>

<style>
    .settings-nav {
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .settings-nav .nav-link {
        padding: 1rem 1.5rem;
        color: #666;
        border-left: 3px solid transparent;
    }
    
    .settings-nav .nav-link:hover {
        background: #f8f9fa;
        color: #4285f4;
    }
    
    .settings-nav .nav-link.active {
        background: #f0f7ff;
        color: #4285f4;
        border-left-color: #4285f4;
    }
    
    .settings-nav .nav-link i {
        width: 20px;
        margin-right: 10px;
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
                    <h1 class="h4 mb-0">Settings</h1>
                </div>
                <div class="ms-auto">
                    <a href="<?php echo $return_path; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
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
                    <!-- Settings Navigation -->
                    <div class="col-md-3 mb-4">
                        <div class="settings-nav">
                            <div class="nav flex-column" role="tablist">
                                <a class="nav-link active" data-bs-toggle="pill" href="#password">
                                    <i class="fas fa-lock"></i> Password
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Content -->
                    <div class="col-md-9">
                        <div class="tab-content">
                            <!-- Password Settings -->
                            <div class="tab-pane fade show active" id="password">
                                <div class="card">
                                    <div class="card-header bg-white">
                                        <h5 class="card-title mb-0">Change Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" 
                                                       name="current_password" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password</label>
                                                <input type="password" class="form-control" id="new_password" 
                                                       name="new_password" required>
                                                <div class="form-text">
                                                    Password must be at least 6 characters long.
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" 
                                                       name="confirm_password" required>
                                            </div>
                                            
                                            <button type="submit" name="update_password" class="btn btn-primary">
                                                Update Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>




                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html> 