<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $browser_notifications = isset($_POST['browser_notifications']) ? 1 : 0;
    $assignment_notifications = isset($_POST['assignment_notifications']) ? 1 : 0;
    $grade_notifications = isset($_POST['grade_notifications']) ? 1 : 0;
    $announcement_notifications = isset($_POST['announcement_notifications']) ? 1 : 0;
    $message_notifications = isset($_POST['message_notifications']) ? 1 : 0;
    
    try {
        // Update or insert user settings
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (
                user_id, email_notifications, browser_notifications,
                assignment_notifications, grade_notifications, 
                announcement_notifications, message_notifications
            ) VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            email_notifications = VALUES(email_notifications),
            browser_notifications = VALUES(browser_notifications),
            assignment_notifications = VALUES(assignment_notifications),
            grade_notifications = VALUES(grade_notifications),
            announcement_notifications = VALUES(announcement_notifications),
            message_notifications = VALUES(message_notifications)
        ");
        $stmt->execute([
            $user_id, $email_notifications, $browser_notifications,
            $assignment_notifications, $grade_notifications,
            $announcement_notifications, $message_notifications
        ]);
        
        setFlashMessage('success', 'Notification settings updated successfully!');
    } catch (Exception $e) {
        setFlashMessage('error', 'Failed to update settings. Please try again.');
    }
    
    header('Location: notification_settings.php');
    exit();
}

// Get current settings
try {
    $stmt = $pdo->prepare("
        SELECT email_notifications, browser_notifications,
               assignment_notifications, grade_notifications,
               announcement_notifications, message_notifications
        FROM user_settings WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Create default settings
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (
                user_id, email_notifications, browser_notifications,
                assignment_notifications, grade_notifications,
                announcement_notifications, message_notifications
            ) VALUES (?, 1, 1, 1, 1, 1, 1)
        ");
        $stmt->execute([$user_id]);
        $settings = [
            'email_notifications' => 1,
            'browser_notifications' => 1,
            'assignment_notifications' => 1,
            'grade_notifications' => 1,
            'announcement_notifications' => 1,
            'message_notifications' => 1
        ];
    }
} catch (Exception $e) {
    $settings = [
        'email_notifications' => 1,
        'browser_notifications' => 1,
        'assignment_notifications' => 1,
        'grade_notifications' => 1,
        'announcement_notifications' => 1,
        'message_notifications' => 1
    ];
}

$page_title = "Notification Settings";
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-bell"></i> Notification Settings
                </h1>
            </div>

            <?php displayFlashMessage(); ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Notification Preferences</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- General Notification Settings -->
                                <div class="mb-4">
                                    <h6>General Settings</h6>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                               <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            Email Notifications
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Receive notifications via email
                                        </small>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="browser_notifications" name="browser_notifications" 
                                               <?= $settings['browser_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="browser_notifications">
                                            Browser Notifications
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Receive notifications in your browser
                                        </small>
                                    </div>
                                </div>

                                <hr>

                                <!-- Specific Notification Types -->
                                <div class="mb-4">
                                    <h6>Notification Types</h6>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="assignment_notifications" name="assignment_notifications" 
                                               <?= $settings['assignment_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="assignment_notifications">
                                            New Assignments
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Get notified when new assignments are posted
                                        </small>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="grade_notifications" name="grade_notifications" 
                                               <?= $settings['grade_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="grade_notifications">
                                            Grades Posted
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Get notified when your grades are posted
                                        </small>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="announcement_notifications" name="announcement_notifications" 
                                               <?= $settings['announcement_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="announcement_notifications">
                                            New Announcements
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Get notified about new class announcements
                                        </small>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="message_notifications" name="message_notifications" 
                                               <?= $settings['message_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="message_notifications">
                                            New Messages
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Get notified when you receive new messages
                                        </small>
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="material_notifications" name="material_notifications" 
                                               <?= $settings['material_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="material_notifications">
                                            New Materials
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Get notified when new materials are uploaded
                                        </small>
                                    </div>
                                    

                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="class_notifications" name="class_notifications" 
                                               <?= $settings['class_notifications'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="class_notifications">
                                            Class Updates
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Get notified about class changes and updates
                                        </small>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Help & Information</h6>
                        </div>
                        <div class="card-body">
                            <h6>Notification Types</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-file-alt text-primary"></i> <strong>Assignments:</strong> New assignments posted by teachers</li>
                                <li><i class="fas fa-star text-warning"></i> <strong>Grades:</strong> When your work is graded</li>
                                <li><i class="fas fa-bullhorn text-info"></i> <strong>Announcements:</strong> Class announcements and updates</li>
                                <li><i class="fas fa-envelope text-success"></i> <strong>Messages:</strong> Private messages from teachers/students</li>
                            </ul>
                            
                            <hr>
                            
                            <h6>Tips</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-lightbulb text-warning"></i> Keep email notifications enabled for important updates</li>
                                <li><i class="fas fa-lightbulb text-warning"></i> Browser notifications work best when the site is open</li>
                                <li><i class="fas fa-lightbulb text-warning"></i> You can change these settings anytime</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Auto-save settings when checkboxes change
document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        // Optional: Auto-save on change
        // document.querySelector('form').submit();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
