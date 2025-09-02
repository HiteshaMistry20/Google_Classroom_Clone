<?php
/**
 * Database Migration: Add teacher_id to materials table
 * This script adds the teacher_id column to existing materials records
 */

require_once 'config/database.php';

try {
    // First, add the teacher_id column if it doesn't exist (without NOT NULL constraint initially)
    $pdo->exec("ALTER TABLE materials ADD COLUMN IF NOT EXISTS teacher_id int(11) AFTER class_id");
    
    // Update existing materials to set teacher_id based on the class teacher
    $stmt = $pdo->prepare("
        UPDATE materials m 
        JOIN classes c ON m.class_id = c.id 
        SET m.teacher_id = c.teacher_id 
        WHERE m.teacher_id IS NULL
    ");
    $stmt->execute();
    
    $updated_count = $stmt->rowCount();
    
    echo "Updated $updated_count materials with teacher_id.\n";
    
    // Now make the column NOT NULL
    $pdo->exec("ALTER TABLE materials MODIFY COLUMN teacher_id int(11) NOT NULL");
    
    // Add foreign key constraint if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE materials ADD CONSTRAINT fk_materials_teacher_id FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "Foreign key constraint added successfully.\n";
    } catch (Exception $e) {
        echo "Foreign key constraint already exists or could not be added: " . $e->getMessage() . "\n";
    }
    
    // Verify the migration
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM materials WHERE teacher_id IS NULL");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "All materials now have valid teacher_id values.\n";
    } else {
        echo "Warning: {$result['count']} materials still have NULL teacher_id values.\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
