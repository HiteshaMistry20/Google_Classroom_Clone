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

// Get user settings
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get notification settings (create if not exists)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_settings (user_id, email_notifications, browser_notifications)
        VALUES (?, 1, 1)
    ");
    $stmt->execute([$user_id]);

    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();

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
    } elseif (isset($_POST['update_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $browser_notifications = isset($_POST['browser_notifications']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE user_settings 
                SET email_notifications = ?, browser_notifications = ?
                WHERE user_id = ?
            ");
            if ($stmt->execute([$email_notifications, $browser_notifications, $user_id])) {
                $success_message = "Notification settings updated successfully!";
                $settings['email_notifications'] = $email_notifications;
                $settings['browser_notifications'] = $browser_notifications;
            } else {
                $error_message = "Failed to update notification settings.";
            }
        } catch (Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $error_message = "An error occurred while updating settings.";
        }
    }
}

$page_title = "Settings";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-cog"></i> Settings</h1>
            </div>

            <?php displayFlashMessage(); ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user"></i> Profile Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label"><strong>Name:</strong></label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($user['name']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Email:</strong></label>
                                <p class="form-control-plaintext"><?= htmlspecialchars($user['email']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Role:</strong></label>
                                <p class="form-control-plaintext"><?= ucfirst($user['role']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><strong>Joined:</strong></label>
                                <p class="form-control-plaintext"><?= formatDate($user['created_at']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-key"></i> Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="update_password" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bell"></i> Notification Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                               <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            Email Notifications
                                        </label>
                                    </div>
                                    <small class="text-muted">Receive notifications via email</small>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="browser_notifications" name="browser_notifications" 
                                               <?= $settings['browser_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="browser_notifications">
                                            Browser Notifications
                                        </label>
                                    </div>
                                    <small class="text-muted">Receive notifications in your browser</small>
                                </div>
                                <button type="submit" name="update_notifications" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Account Actions -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-shield-alt"></i> Account Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="../profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-edit"></i> Edit Profile
                                </a>
                                <a href="../logout.php" class="btn btn-outline-danger" 
                                   onclick="return confirm('Are you sure you want to logout?')">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 