<?php
/**
 * Comprehensive Notification System
 * Handles all types of notifications in the classroom system
 */

// Include email functions
require_once __DIR__ . '/email_functions.php';

/**
 * Send notification for new class creation
 */
function notifyClassCreated($pdo, $class_id) {
    try {
        // Get class details
        $stmt = $pdo->prepare("
            SELECT c.name, c.section, c.subject, c.class_code, c.teacher_id, u.name as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();
        
        if (!$class) return false;
        
        // Notify the teacher about successful class creation
        $notification_created = createNotification(
            $pdo,
            $class['teacher_id'],
            'Class Created Successfully',
            "Your class '{$class['name']}' ({$class['section']}) has been created with code: {$class['class_code']}",
            'class_created',
            $class_id
        );
        
        return $notification_created;
    } catch (Exception $e) {
        error_log("Error notifying class creation: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for student joining class
 */
function notifyStudentJoinedClass($pdo, $class_id, $student_id) {
    try {
        // Get class and student details
        $stmt = $pdo->prepare("
            SELECT c.name as class_name, c.teacher_id, u.name as student_name, t.name as teacher_name
            FROM classes c
            JOIN users u ON u.id = ?
            JOIN users t ON c.teacher_id = t.id
            WHERE c.id = ?
        ");
        $stmt->execute([$student_id, $class_id]);
        $details = $stmt->fetch();
        
        if (!$details) return false;
        
        // Notify teacher about new student
        $teacher_notification = createNotification(
            $pdo,
            $details['teacher_id'],
            'New Student Joined',
            "{$details['student_name']} has joined your class '{$details['class_name']}'",
            'student_joined',
            $class_id
        );
        
        // Notify student about successful join
        $student_notification = createNotification(
            $pdo,
            $student_id,
            'Successfully Joined Class',
            "You have successfully joined '{$details['class_name']}' taught by {$details['teacher_name']}",
            'class_joined',
            $class_id
        );
        
        return $teacher_notification || $student_notification;
    } catch (Exception $e) {
        error_log("Error notifying student joined class: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for new assignment
 */
function notifyNewAssignment($pdo, $assignment_id, $class_id) {
    try {
        // Get assignment details
        $stmt = $pdo->prepare("
            SELECT a.title, a.due_date, a.max_points, c.name as class_name, u.name as teacher_name
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.teacher_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) return false;
        
        // Get all students in the class
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            WHERE ce.class_id = ?
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($students as $student) {
            // Check if student has assignment notifications enabled
            if (hasAssignmentNotifications($pdo, $student['id'])) {
                // Create in-app notification
                $notification_created = createNotification(
                    $pdo, 
                    $student['id'], 
                    'New Assignment Posted', 
                    "New assignment '{$assignment['title']}' in {$assignment['class_name']}. Due: " . formatDateTime($assignment['due_date']) . " (Max points: {$assignment['max_points']})", 
                    'assignment', 
                    $assignment_id
                );
                
                // Send email notification if enabled
                if (hasEmailNotifications($pdo, $student['id'])) {
                    $email_sent = sendAssignmentNotification(
                        $student['email'],
                        $student['name'],
                        $assignment['title'],
                        $assignment['class_name'],
                        $assignment['teacher_name'],
                        $assignment['due_date'],
                        $assignment['max_points']
                    );
                }
                
                if ($notification_created) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error notifying new assignment: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for assignment submission
 */
function notifyAssignmentSubmitted($pdo, $submission_id) {
    try {
        // Get submission details
        $stmt = $pdo->prepare("
            SELECT s.submitted_at, a.title as assignment_title, a.teacher_id, a.class_id,
                   c.name as class_name, u.name as student_name, u.email as student_email
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON s.student_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch();
        
        if (!$submission) return false;
        
        // Notify teacher about submission
        $teacher_notification = createNotification(
            $pdo,
            $submission['teacher_id'],
            'Assignment Submitted',
            "{$submission['student_name']} submitted '{$submission['assignment_title']}' in {$submission['class_name']}",
            'submission',
            $submission_id
        );
        
        // Notify student about successful submission
        $student_notification = createNotification(
            $pdo,
            $submission['student_id'],
            'Assignment Submitted Successfully',
            "Your submission for '{$submission['assignment_title']}' has been received",
            'submission_confirmed',
            $submission_id
        );
        
        return $teacher_notification || $student_notification;
    } catch (Exception $e) {
        error_log("Error notifying assignment submission: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for graded assignment
 */
function notifyGradedAssignment($pdo, $submission_id) {
    try {
        // Get submission details
        $stmt = $pdo->prepare("
            SELECT s.grade, s.feedback, s.graded_at, a.title as assignment_title, a.max_points,
                   c.name as class_name, u.name as student_name, u.email as student_email,
                   t.name as teacher_name
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON s.student_id = u.id
            JOIN users t ON a.teacher_id = t.id
            WHERE s.id = ?
        ");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch();
        
        if (!$submission) return false;
        
        // Check if student has grade notifications enabled
        if (hasGradeNotifications($pdo, $submission['student_id'])) {
            // Create in-app notification
            $notification_created = createNotification(
                $pdo,
                $submission['student_id'],
                'Grade Posted',
                "Your grade for '{$submission['assignment_title']}' has been posted: {$submission['grade']}/{$submission['max_points']}",
                'grade',
                $submission_id
            );
            
            // Send email notification if enabled
            if (hasEmailNotifications($pdo, $submission['student_id'])) {
                $email_sent = sendGradeNotification(
                    $submission['student_email'],
                    $submission['student_name'],
                    $submission['assignment_title'],
                    $submission['class_name'],
                    $submission['grade'],
                    $submission['max_points'],
                    $submission['feedback'],
                    $submission['teacher_name']
                );
            }
            
            return $notification_created;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error notifying graded assignment: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for new announcement
 */
function notifyNewAnnouncement($pdo, $announcement_id, $class_id) {
    try {
        // Get announcement details
        $stmt = $pdo->prepare("
            SELECT a.title, a.content, c.name as class_name, u.name as teacher_name
            FROM announcements a
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON a.author_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$announcement_id]);
        $announcement = $stmt->fetch();
        
        if (!$announcement) return false;
        
        // Get all students in the class
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            WHERE ce.class_id = ?
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($students as $student) {
            // Check if student has announcement notifications enabled
            if (hasAnnouncementNotifications($pdo, $student['id'])) {
                // Create in-app notification
                $notification_created = createNotification(
                    $pdo,
                    $student['id'],
                    'New Announcement',
                    "New announcement in {$announcement['class_name']}: {$announcement['title']}",
                    'announcement',
                    $announcement_id
                );
                
                // Send email notification if enabled
                if (hasEmailNotifications($pdo, $student['id'])) {
                    $email_sent = sendAnnouncementNotification(
                        $student['email'],
                        $student['name'],
                        $announcement['title'],
                        $announcement['class_name'],
                        $announcement['teacher_name'],
                        $announcement['content']
                    );
                }
                
                if ($notification_created) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error notifying new announcement: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for new message
 */
function notifyNewMessage($pdo, $message_id) {
    try {
        // Get message details
        $stmt = $pdo->prepare("
            SELECT m.subject, m.message, u.name as sender_name, u.email as sender_email,
                   r.name as recipient_name, r.email as recipient_email, r.id as recipient_id
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            JOIN users r ON m.receiver_id = r.id
            WHERE m.id = ?
        ");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch();
        
        if (!$message) return false;
        
        // Check if recipient has message notifications enabled
        if (hasMessageNotifications($pdo, $message['recipient_id'])) {
            // Create in-app notification
            $notification_created = createNotification(
                $pdo,
                $message['recipient_id'],
                'New Message',
                "New message from {$message['sender_name']}: {$message['subject']}",
                'message',
                $message_id
            );
            
            // Send email notification if enabled
            if (hasEmailNotifications($pdo, $message['recipient_id'])) {
                $email_sent = sendMessageNotification(
                    $message['recipient_email'],
                    $message['recipient_name'],
                    $message['sender_name'],
                    $message['subject'],
                    $message['message']
                );
            }
            
            return $notification_created;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error notifying new message: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for new material
 */
function notifyNewMaterial($pdo, $material_id, $class_id) {
    try {
        // Get material details
        $stmt = $pdo->prepare("
            SELECT m.title, m.description, m.file_path, c.name as class_name, u.name as teacher_name
            FROM materials m
            JOIN classes c ON m.class_id = c.id
            JOIN users u ON c.teacher_id = u.id
            WHERE m.id = ?
        ");
        $stmt->execute([$material_id]);
        $material = $stmt->fetch();
        
        if (!$material) return false;
        
        // Get all students in the class
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            WHERE ce.class_id = ?
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($students as $student) {
            // Check if student has material notifications enabled
            if (hasMaterialNotifications($pdo, $student['id'])) {
                // Create in-app notification
                $notification_created = createNotification(
                    $pdo,
                    $student['id'],
                    'New Material Available',
                    "New material in {$material['class_name']}: {$material['title']}",
                    'material',
                    $material_id
                );
                
                // Send email notification if enabled
                if (hasEmailNotifications($pdo, $student['id'])) {
                    $email_sent = sendMaterialNotification(
                        $student['email'],
                        $student['name'],
                        $material['title'],
                        $material['class_name'],
                        $material['teacher_name'],
                        $material['description']
                    );
                }
                
                if ($notification_created) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error notifying new material: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for attendance marked
 */
function notifyAttendanceMarked($pdo, $class_id, $date, $marked_by) {
    try {
        // Get class details
        $stmt = $pdo->prepare("
            SELECT c.name as class_name, u.name as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();
        
        if (!$class) return false;
        
        // Get all students in the class
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            WHERE ce.class_id = ?
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($students as $student) {
            // Check if student has attendance notifications enabled
            if (hasAttendanceNotifications($pdo, $student['id'])) {
                // Create in-app notification
                $notification_created = createNotification(
                    $pdo,
                    $student['id'],
                    'Attendance Marked',
                    "Attendance has been marked for {$class['class_name']} on " . formatDate($date),
                    'attendance',
                    $class_id
                );
                
                // Send email notification if enabled
                if (hasEmailNotifications($pdo, $student['id'])) {
                    $email_sent = sendAttendanceNotification(
                        $student['email'],
                        $student['name'],
                        $class['class_name'],
                        $date,
                        $class['teacher_name']
                    );
                }
                
                if ($notification_created) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error notifying attendance marked: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for class updates
 */
function notifyClassUpdate($pdo, $class_id, $update_type, $update_details) {
    try {
        // Get class details
        $stmt = $pdo->prepare("
            SELECT c.name as class_name, u.name as teacher_name
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch();
        
        if (!$class) return false;
        
        // Get all students in the class
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            WHERE ce.class_id = ?
        ");
        $stmt->execute([$class_id]);
        $students = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($students as $student) {
            // Check if student has announcement notifications enabled
            if (hasAnnouncementNotifications($pdo, $student['id'])) {
                // Create in-app notification
                $notification_created = createNotification(
                    $pdo,
                    $student['id'],
                    'Class Update',
                    "Update in {$class['class_name']}: {$update_details}",
                    'announcement',
                    null
                );
                
                // Send email notification if enabled
                if (hasEmailNotifications($pdo, $student['id'])) {
                    $email_sent = sendClassUpdateNotification(
                        $student['email'],
                        $student['name'],
                        $class['class_name'],
                        $update_type,
                        $update_details,
                        $class['teacher_name']
                    );
                }
                
                if ($notification_created) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error notifying class update: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification for assignment due date reminder
 */
function notifyAssignmentDueReminder($pdo, $assignment_id) {
    try {
        // Get assignment details
        $stmt = $pdo->prepare("
            SELECT a.title, a.due_date, c.name as class_name, c.id as class_id
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            WHERE a.id = ? AND a.due_date > NOW()
        ");
        $stmt->execute([$assignment_id]);
        $assignment = $stmt->fetch();
        
        if (!$assignment) return false;
        
        // Get students who haven't submitted yet
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            JOIN class_enrollments ce ON u.id = ce.student_id
            LEFT JOIN submissions s ON u.id = s.student_id AND s.assignment_id = ?
            WHERE ce.class_id = ? AND s.id IS NULL
        ");
        $stmt->execute([$assignment_id, $assignment['class_id']]);
        $students = $stmt->fetchAll();
        
        $success_count = 0;
        foreach ($students as $student) {
            // Check if student has assignment notifications enabled
            if (hasAssignmentNotifications($pdo, $student['id'])) {
                // Create in-app notification
                $notification_created = createNotification(
                    $pdo,
                    $student['id'],
                    'Assignment Due Soon',
                    "Reminder: '{$assignment['title']}' is due on " . formatDateTime($assignment['due_date']),
                    'assignment_reminder',
                    $assignment_id
                );
                
                // Send email notification if enabled
                if (hasEmailNotifications($pdo, $student['id'])) {
                    $email_sent = sendAssignmentReminderNotification(
                        $student['email'],
                        $student['name'],
                        $assignment['title'],
                        $assignment['class_name'],
                        $assignment['due_date']
                    );
                }
                
                if ($notification_created) {
                    $success_count++;
                }
            }
        }
        
        return $success_count > 0;
    } catch (Exception $e) {
        error_log("Error notifying assignment due reminder: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify student when resubmission is enabled
 * @param PDO $pdo Database connection
 * @param int $submission_id Submission ID
 * @return bool Success status
 */
function notifyResubmissionEnabled($pdo, $submission_id) {
    try {
        // Get submission details
        $stmt = $pdo->prepare("
            SELECT s.*, a.title as assignment_title, a.allow_late, c.name as class_name, u.name as student_name, u.email as student_email
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON s.student_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            return false;
        }
        
        // Create notification
        $notification_data = [
            'user_id' => $submission['student_id'],
            'type' => 'resubmission_enabled',
            'title' => 'Resubmission Enabled',
            'message' => "Your teacher has enabled resubmission for '{$submission['assignment_title']}' in {$submission['class_name']}. You can now submit updated work.",
            'related_id' => $submission['assignment_id'],
            'related_type' => 'assignment'
        ];
        
        createNotification($pdo, $notification_data);
        
        // Send email notification
        if (function_exists('sendResubmissionEnabledEmail')) {
            sendResubmissionEnabledEmail(
                $submission['student_email'],
                $submission['student_name'],
                $submission['assignment_title'],
                $submission['class_name']
            );
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in notifyResubmissionEnabled: " . $e->getMessage());
        return false;
    }
}

// Notification preference check functions
function hasAssignmentNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT assignment_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['assignment_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking assignment notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

function hasGradeNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT grade_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['grade_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking grade notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

function hasAnnouncementNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT announcement_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['announcement_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking announcement notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

function hasMaterialNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT material_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['material_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking material notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

function hasAttendanceNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT attendance_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['attendance_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking attendance notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

function hasMessageNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT message_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['message_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking message notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

/**
 * Check if user has email notifications enabled
 */
function hasEmailNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT email_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['email_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking email notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

/**
 * Check if user has browser notifications enabled
 */
function hasBrowserNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT browser_notifications FROM user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        
        return $settings ? (bool)$settings['browser_notifications'] : true; // Default to true
    } catch (Exception $e) {
        error_log("Error checking browser notifications: " . $e->getMessage());
        return true; // Default to true on error
    }
}

/**
 * Clean up old notifications (older than 30 days)
 */
function cleanupOldNotifications($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
            AND is_read = 1
        ");
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Error cleaning up old notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notification statistics for a user
 */
function getNotificationStats($pdo, $user_id) {
    try {
        // Total notifications
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total = $stmt->fetchColumn();
        
        // Unread notifications
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread = $stmt->fetchColumn();
        
        // Notifications by type
        $stmt = $pdo->prepare("
            SELECT type, COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? 
            GROUP BY type
        ");
        $stmt->execute([$user_id]);
        $by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
            'by_type' => $by_type
        ];
    } catch (Exception $e) {
        error_log("Error getting notification stats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'read' => 0,
            'by_type' => []
        ];
    }
}
?>
