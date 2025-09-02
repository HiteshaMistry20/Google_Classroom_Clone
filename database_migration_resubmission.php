<?php
/**
 * Database Migration: Add resubmission functionality to submissions table
 * Run this script once to update existing database schema
 */

require_once 'config/database.php';

try {
    echo "Starting database migration for resubmission functionality...\n";
    
    // Check if allow_resubmission column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM submissions LIKE 'allow_resubmission'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Add allow_resubmission column
        $pdo->exec("ALTER TABLE submissions ADD COLUMN allow_resubmission TINYINT(1) NOT NULL DEFAULT 0");
        echo "✓ Added allow_resubmission column to submissions table\n";
    } else {
        echo "✓ allow_resubmission column already exists\n";
    }
    
    // Check if updated_at column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM submissions LIKE 'updated_at'");
    $stmt->execute();
    $updated_at_exists = $stmt->fetch();
    
    if (!$updated_at_exists) {
        // Add updated_at column
        $pdo->exec("ALTER TABLE submissions ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✓ Added updated_at column to submissions table\n";
    } else {
        echo "✓ updated_at column already exists\n";
    }
    
    echo "\nMigration completed successfully!\n";
    echo "Resubmission functionality is now available.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
