<?php
// Database configuration
$host = 'localhost';
$dbname = 'classroom_clone';
$username = 'root';
$password = '';

// First, try to connect without specifying database
try {
    $pdo_temp = new PDO("mysql:host=$host", $username, $password);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo_temp = null;
} catch (PDOException $e) {
    die("Database creation failed: " . $e->getMessage());
}

// Now connect to the specific database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}



// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `email` varchar(255) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `role` enum('admin','teacher','student') NOT NULL,
        `status` enum('active','pending','blocked') NOT NULL DEFAULT 'active',
        `profile_image` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `classes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `section` varchar(50) NOT NULL,
        `subject` varchar(255) NOT NULL,
        `description` text,
        `class_code` varchar(10) NOT NULL UNIQUE,
        `qr_code` varchar(255) DEFAULT NULL,
        `status` enum('active','archived') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `class_enrollments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `enrolled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_enrollment` (`class_id`, `student_id`),
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `materials` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `teacher_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text,
        `file_path` varchar(255) DEFAULT NULL,
        `file_name` varchar(255) DEFAULT NULL,
        `file_type` varchar(50) DEFAULT NULL,
        `file_size` int(11) DEFAULT NULL,
        `link_url` varchar(500) DEFAULT NULL,
        `type` enum('file','link','video') NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `assignments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `description` text,
        `due_date` datetime NOT NULL,
        `is_graded` tinyint(1) NOT NULL DEFAULT 0,
        `max_points` int(11) DEFAULT NULL,
        `allow_late` tinyint(1) NOT NULL DEFAULT 0,
        `allow_resubmission` tinyint(1) NOT NULL DEFAULT 0,
        `require_late_approval` tinyint(1) NOT NULL DEFAULT 1,
        `status` enum('active','archived') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `assignment_files` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `assignment_id` int(11) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_type` varchar(50) NOT NULL,
        `file_size` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `submissions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `assignment_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_late` tinyint(1) NOT NULL DEFAULT 0,
        `grade` decimal(5,2) DEFAULT NULL,
        `feedback` text,
        `status` enum('submitted','graded','late') NOT NULL DEFAULT 'submitted',
        `allow_resubmission` tinyint(1) NOT NULL DEFAULT 0,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
        FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `submission_files` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `submission_id` int(11) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_type` varchar(50) NOT NULL,
        `file_size` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `late_submission_requests` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `assignment_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `reason` text,
        `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `teacher_response` text,
        `responded_at` timestamp NULL DEFAULT NULL,
        `responded_by` int(11) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_request` (`assignment_id`, `student_id`),
        FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`responded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `attendance` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `student_id` int(11) NOT NULL,
        `attendance_date` date NOT NULL,
        `status` enum('present','absent','late') NOT NULL,
        `marked_by` int(11) NOT NULL,
        `marked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_attendance` (`class_id`, `student_id`, `attendance_date`),
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `announcements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `content` text NOT NULL,
        `author_id` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `comments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `announcement_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `content` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sender_id` int(11) NOT NULL,
        `receiver_id` int(11) NOT NULL,
        `subject` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `type` enum('assignment','material','grade','announcement','message') NOT NULL,
        `related_id` int(11) DEFAULT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        // Table might already exist, continue
    }
}

// Create default admin user if not exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
if (!$stmt->fetch()) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Admin User', 'admin@classroomclone.com', $admin_password, 'admin', 'active']);
}
?>
