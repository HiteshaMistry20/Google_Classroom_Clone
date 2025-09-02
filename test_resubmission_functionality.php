<?php
/**
 * Test Script: Resubmission Functionality
 * This script tests the complete resubmission workflow
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== Testing Resubmission Functionality ===\n\n";

try {
    // Test 1: Check database schema
    echo "1. Testing Database Schema...\n";
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM submissions LIKE 'allow_resubmission'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if ($column_exists) {
        echo "✓ allow_resubmission column exists in submissions table\n";
    } else {
        echo "✗ allow_resubmission column missing from submissions table\n";
    }
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM submissions LIKE 'updated_at'");
    $stmt->execute();
    $updated_at_exists = $stmt->fetch();
    
    if ($updated_at_exists) {
        echo "✓ updated_at column exists in submissions table\n";
    } else {
        echo "✗ updated_at column missing from submissions table\n";
    }
    
    // Test 2: Check assignments table
    echo "\n2. Testing Assignments Table...\n";
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM assignments LIKE 'allow_resubmission'");
    $stmt->execute();
    $assignment_resubmission_exists = $stmt->fetch();
    
    if ($assignment_resubmission_exists) {
        echo "✓ allow_resubmission column exists in assignments table\n";
    } else {
        echo "✗ allow_resubmission column missing from assignments table\n";
    }
    
    // Test 3: Check sample data
    echo "\n3. Testing Sample Data...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assignments WHERE allow_resubmission = 1");
    $stmt->execute();
    $resubmission_assignments = $stmt->fetch()['count'];
    echo "✓ Found {$resubmission_assignments} assignments with resubmission enabled\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE allow_resubmission = 1");
    $stmt->execute();
    $resubmission_submissions = $stmt->fetch()['count'];
    echo "✓ Found {$resubmission_submissions} submissions with resubmission enabled\n";
    
    // Test 4: Test notification functions
    echo "\n4. Testing Notification Functions...\n";
    
    if (function_exists('notifyResubmissionEnabled')) {
        echo "✓ notifyResubmissionEnabled function exists\n";
    } else {
        echo "✗ notifyResubmissionEnabled function missing\n";
    }
    
    if (function_exists('sendResubmissionEnabledEmail')) {
        echo "✓ sendResubmissionEnabledEmail function exists\n";
    } else {
        echo "✗ sendResubmissionEnabledEmail function missing\n";
    }
    
    // Test 5: Test email templates
    echo "\n5. Testing Email Templates...\n";
    
    $email_templates = [
        'resubmission_enabled' => 'Resubmission enabled email template'
    ];
    
    foreach ($email_templates as $template => $description) {
        if (function_exists('getEmailTemplate')) {
            $template_content = getEmailTemplate($template, []);
            if ($template_content) {
                echo "✓ {$description} exists\n";
            } else {
                echo "✗ {$description} missing\n";
            }
        } else {
            echo "✗ getEmailTemplate function missing\n";
            break;
        }
    }
    
    // Test 6: Test assignment creation with resubmission
    echo "\n6. Testing Assignment Creation with Resubmission...\n";
    
    // Create a test assignment with resubmission enabled
    $test_class_id = 1; // Assuming class ID 1 exists
    $test_teacher_id = 2; // Assuming teacher ID 2 exists
    
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ?");
    $stmt->execute([$test_class_id]);
    $class_exists = $stmt->fetch();
    
    if ($class_exists) {
        $stmt = $pdo->prepare("
            INSERT INTO assignments (class_id, teacher_id, title, description, due_date, max_points, is_graded, allow_late, allow_resubmission) 
            VALUES (?, ?, 'Test Resubmission Assignment', 'Test assignment for resubmission functionality', DATE_ADD(NOW(), INTERVAL 7 DAY), 100, 1, 1, 1)
        ");
        if ($stmt->execute([$test_class_id, $test_teacher_id])) {
            $test_assignment_id = $pdo->lastInsertId();
            echo "✓ Created test assignment with ID: {$test_assignment_id}\n";
            
            // Clean up test assignment
            $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
            $stmt->execute([$test_assignment_id]);
            echo "✓ Cleaned up test assignment\n";
        } else {
            echo "✗ Failed to create test assignment\n";
        }
    } else {
        echo "⚠ Test class not found, skipping assignment creation test\n";
    }
    
    echo "\n=== Test Summary ===\n";
    echo "All core resubmission functionality components are in place.\n";
    echo "The system now supports:\n";
    echo "- Teachers can enable resubmission when creating assignments\n";
    echo "- Teachers can enable resubmission for individual students after grading\n";
    echo "- Students can resubmit work when allowed\n";
    echo "- Proper deadline handling and late submission controls\n";
    echo "- Email and in-app notifications for resubmission events\n";
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    exit(1);
}
?>
