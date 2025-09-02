<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action']) || !isset($input['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit();
    }
    
    $action = $input['action'];
    $user_id = $input['user_id'];
    
    switch ($action) {
        case 'approve':
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'teacher' AND status = 'pending'");
            if ($stmt->execute([$user_id])) {
                // Get user details for notification
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Send email notification
                    $subject = "Teacher Account Approved";
                    $message = "Dear {$user['name']},\n\nYour teacher account has been approved. You can now log in and start creating classes.\n\nBest regards,\nClassroom Clone Team";
                    sendEmail($user['email'], $subject, $message);
                    
                    // Create notification
                    createNotification(
                        $pdo,
                        $user_id,
                        'Account Approved',
                        'Your teacher account has been approved. You can now log in and start creating classes.',
                        'announcement'
                    );
                }
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve user']);
            }
            break;
            
        case 'reject':
            $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ? AND role = 'teacher' AND status = 'pending'");
            if ($stmt->execute([$user_id])) {
                // Get user details for notification
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Send email notification
                    $subject = "Teacher Account Application";
                    $message = "Dear {$user['name']},\n\nWe regret to inform you that your teacher account application has not been approved at this time.\n\nBest regards,\nClassroom Clone Team";
                    sendEmail($user['email'], $subject, $message);
                }
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to reject user']);
            }
            break;
            
        case 'block':
            $stmt = $pdo->prepare("UPDATE users SET status = 'blocked' WHERE id = ? AND id != ?");
            if ($stmt->execute([$user_id, $_SESSION['user_id']])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to block user']);
            }
            break;
            
        case 'unblock':
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to unblock user']);
            }
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
            if ($stmt->execute([$user_id, $_SESSION['user_id']])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
