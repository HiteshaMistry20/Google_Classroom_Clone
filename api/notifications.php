<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            // Get unread notifications
            $stmt = $pdo->prepare("
                SELECT id, title, message, type, related_id, created_at, is_read
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get notification count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            $count_result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $count_result['count']
            ]);
            break;
            
        case 'get_all':
            // Get all notifications (read and unread)
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $stmt = $pdo->prepare("
                SELECT id, title, message, type, related_id, created_at, is_read
                FROM notifications 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM notifications 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $total_result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'total' => $total_result['total'],
                'page' => $page,
                'has_more' => ($page * $limit) < $total_result['total']
            ]);
            break;
            
        case 'mark_read':
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Notification ID required']);
            }
            break;
            
        case 'mark_all_read':
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Notification ID required']);
            }
            break;
            
        case 'settings':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $browser_notifications = isset($_POST['browser_notifications']) ? 1 : 0;
                $assignment_notifications = isset($_POST['assignment_notifications']) ? 1 : 0;
                $grade_notifications = isset($_POST['grade_notifications']) ? 1 : 0;
                $announcement_notifications = isset($_POST['announcement_notifications']) ? 1 : 0;
                $message_notifications = isset($_POST['message_notifications']) ? 1 : 0;
                $material_notifications = isset($_POST['material_notifications']) ? 1 : 0;
                $attendance_notifications = isset($_POST['attendance_notifications']) ? 1 : 0;
                $class_notifications = isset($_POST['class_notifications']) ? 1 : 0;
                
                // Update or insert user settings
                $stmt = $pdo->prepare("
                    INSERT INTO user_settings (
                        user_id, email_notifications, browser_notifications,
                        assignment_notifications, grade_notifications, 
                        announcement_notifications, message_notifications,
                        material_notifications, attendance_notifications, class_notifications
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    email_notifications = VALUES(email_notifications),
                    browser_notifications = VALUES(browser_notifications),
                    assignment_notifications = VALUES(assignment_notifications),
                    grade_notifications = VALUES(grade_notifications),
                    announcement_notifications = VALUES(announcement_notifications),
                    message_notifications = VALUES(message_notifications),
                    material_notifications = VALUES(material_notifications),
                    attendance_notifications = VALUES(attendance_notifications),
                    class_notifications = VALUES(class_notifications)
                ");
                $stmt->execute([
                    $user_id, $email_notifications, $browser_notifications,
                    $assignment_notifications, $grade_notifications,
                    $announcement_notifications, $message_notifications,
                    $material_notifications, $attendance_notifications, $class_notifications
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
            } else {
                // Get current settings
                $stmt = $pdo->prepare("
                    SELECT email_notifications, browser_notifications,
                           assignment_notifications, grade_notifications,
                           announcement_notifications, message_notifications,
                           material_notifications, attendance_notifications, class_notifications
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
                            announcement_notifications, message_notifications,
                            material_notifications, attendance_notifications, class_notifications
                        ) VALUES (?, 1, 1, 1, 1, 1, 1, 1, 1, 1)
                    ");
                    $stmt->execute([$user_id]);
                    $settings = [
                        'email_notifications' => 1,
                        'browser_notifications' => 1,
                        'assignment_notifications' => 1,
                        'grade_notifications' => 1,
                        'announcement_notifications' => 1,
                        'message_notifications' => 1,
                        'material_notifications' => 1,
                        'attendance_notifications' => 1,
                        'class_notifications' => 1
                    ];
                }
                
                echo json_encode(['success' => true, 'settings' => $settings]);
            }
            break;
            
        case 'stats':
            // Get notification statistics
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $unread = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT type, COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? 
                GROUP BY type
            ");
            $stmt->execute([$user_id]);
            $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total' => $total,
                    'unread' => $unread,
                    'read' => $total - $unread,
                    'by_type' => $by_type
                ]
            ]);
            break;
            
        case 'get_realtime':
            // Get real-time notification data for AJAX updates
            $requested_user_id = $_GET['user_id'] ?? $user_id;
            
            // Verify the user is requesting their own notifications
            if ($requested_user_id != $user_id) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized']);
                exit();
            }
            
            // Get total count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $total = $stmt->fetchColumn();
            
            // Get unread count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $unread = $stmt->fetchColumn();
            
            // Get recent notifications (last 20)
            $stmt = $pdo->prepare("
                SELECT 
                    n.*,
                    CASE 
                        WHEN n.type = 'assignment' THEN a.title
                        WHEN n.type = 'material' THEN m.title
                        WHEN n.type = 'announcement' THEN ann.title
                        ELSE NULL
                    END as related_title,
                    CASE 
                        WHEN n.type = 'assignment' THEN CONCAT('../student/assignment_details.php?id=', a.id)
                        WHEN n.type = 'material' THEN CONCAT('../student/material_details.php?id=', m.id)
                        WHEN n.type = 'announcement' THEN CONCAT('../student/class_details.php?id=', ann.class_id)
                        ELSE '#'
                    END as related_link
                FROM notifications n
                LEFT JOIN assignments a ON n.type = 'assignment' AND n.related_id = a.id
                LEFT JOIN materials m ON n.type = 'material' AND n.related_id = m.id
                LEFT JOIN announcements ann ON n.type = 'announcement' AND n.related_id = ann.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'total' => $total,
                'unread' => $unread,
                'read' => $total - $unread,
                'notifications' => $notifications
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred']);
}
?>
