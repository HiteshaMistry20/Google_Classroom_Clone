<?php
/**
 * Assignment Due Date Reminders Script
 * This script should be run as a cron job to send reminders for upcoming assignments
 * Recommended: Run every hour or every 6 hours
 * 
 * Usage: php cron/assignment_reminders.php
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Set timezone
date_default_timezone_set('UTC');

// Log file for tracking
$log_file = '../logs/assignment_reminders.log';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message" . PHP_EOL;
    
    // Write to log file
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}

try {
    logMessage("Starting assignment reminder check...");
    
    // Get assignments due in the next 24 hours that haven't been reminded yet
    $stmt = $pdo->prepare("
        SELECT DISTINCT a.id, a.title, a.due_date, c.id as class_id, c.name as class_name
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        WHERE a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND a.status = 'active'
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.type = 'assignment_reminder' 
            AND n.related_id = a.id 
            AND n.created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
        )
    ");
    $stmt->execute();
    $upcoming_assignments = $stmt->fetchAll();
    
    logMessage("Found " . count($upcoming_assignments) . " assignments due in next 24 hours");
    
    $total_reminders_sent = 0;
    
    foreach ($upcoming_assignments as $assignment) {
        logMessage("Processing assignment: {$assignment['title']} (Due: {$assignment['due_date']})");
        
        // Send reminders for this assignment
        $reminders_sent = notifyAssignmentDueReminder($pdo, $assignment['id']);
        
        if ($reminders_sent) {
            $total_reminders_sent += $reminders_sent;
            logMessage("Sent $reminders_sent reminders for assignment: {$assignment['title']}");
        } else {
            logMessage("No reminders sent for assignment: {$assignment['title']}");
        }
    }
    
    logMessage("Completed assignment reminder check. Total reminders sent: $total_reminders_sent");
    
    // Clean up old notifications (older than 30 days)
    $cleanup_result = cleanupOldNotifications($pdo);
    if ($cleanup_result) {
        logMessage("Old notifications cleanup completed");
    } else {
        logMessage("Old notifications cleanup failed");
    }
    
} catch (Exception $e) {
    logMessage("Error in assignment reminder script: " . $e->getMessage());
    error_log("Assignment reminder script error: " . $e->getMessage());
}

logMessage("Assignment reminder script finished");
?> 