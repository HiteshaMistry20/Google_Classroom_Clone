-- Google Classroom Clone - Database Import File
-- This file creates the complete database structure and sample data

-- Disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- Drop the entire database and recreate it
DROP DATABASE IF EXISTS classroom_clone;
CREATE DATABASE classroom_clone;
USE classroom_clone;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create users table first (parent table)
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL UNIQUE,
    `password` varchar(255) NOT NULL,
    `role` enum('admin','teacher','student') NOT NULL,
    `status` enum('active','pending','blocked') NOT NULL DEFAULT 'active',
    `phone` varchar(20) DEFAULT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert all users first (they're referenced by other tables)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES 
(1, 'Admin User', 'admin@classroomclone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active'),
(2, 'John Smith', 'john.smith@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active'),
(3, 'Sarah Johnson', 'sarah.johnson@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active'),
(4, 'Michael Brown', 'michael.brown@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'active'),
(5, 'Emily Wilson', 'emily.wilson@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
(6, 'David Brown', 'david.brown@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
(7, 'Lisa Garcia', 'lisa.garcia@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
(8, 'James Miller', 'james.miller@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
(9, 'Maria Rodriguez', 'maria.rodriguez@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
(10, 'Robert Taylor', 'robert.taylor@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active');

-- Create and populate other tables in order of dependencies
CREATE TABLE IF NOT EXISTS `classes` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample classes
INSERT INTO `classes` (`id`, `teacher_id`, `name`, `section`, `subject`, `description`, `class_code`) VALUES
(1, 2, 'Mathematics 101', 'A', 'Mathematics', 'Introduction to basic mathematical concepts', 'MATH101'),
(2, 2, 'Advanced Algebra', 'B', 'Mathematics', 'Advanced algebraic concepts and problem solving', 'MATH201'),
(3, 3, 'English Literature', 'A', 'English', 'Study of classic literature and writing', 'ENG101'),
(4, 3, 'Creative Writing', 'B', 'English', 'Creative writing and storytelling', 'ENG201'),
(5, 4, 'Computer Science', 'A', 'Technology', 'Introduction to programming concepts', 'CS101'),
(6, 4, 'Web Development', 'B', 'Technology', 'HTML, CSS, JavaScript, and modern web technologies', 'CS201');

-- Create and populate enrollment table
CREATE TABLE IF NOT EXISTS `class_enrollments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
    `student_id` int(11) NOT NULL,
    `enrolled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_enrollment` (`class_id`, `student_id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample enrollments
INSERT INTO `class_enrollments` (`class_id`, `student_id`) VALUES
(1, 5), -- Emily Wilson
(1, 6), -- David Brown
(1, 8), -- James Miller
(2, 7), -- Lisa Garcia
(2, 9), -- Maria Rodriguez
(2, 10), -- Robert Taylor
(3, 5), -- Emily Wilson
(3, 6), -- David Brown
(3, 9), -- Maria Rodriguez
(4, 7), -- Lisa Garcia
(4, 8), -- James Miller
(4, 10), -- Robert Taylor
(5, 6), -- David Brown
(5, 8), -- James Miller
(5, 9), -- Maria Rodriguez
(6, 5), -- Emily Wilson
(6, 7), -- Lisa Garcia
(6, 10); -- Robert Taylor

-- Create attendance table
CREATE TABLE IF NOT EXISTS `attendance` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample attendance records (using teacher IDs for marked_by)
INSERT INTO `attendance` (`class_id`, `student_id`, `attendance_date`, `status`, `marked_by`) VALUES
-- Math class (teacher_id = 2) - Multiple dates for current month
(1, 5, CURDATE(), 'present', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'late', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'present', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'absent', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 2),
(1, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'present', 2),

(1, 6, CURDATE(), 'present', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'late', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'present', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'absent', 2),
(1, 6, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'present', 2),

(1, 8, CURDATE(), 'late', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'present', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'late', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 2),
(1, 8, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'absent', 2),

-- English class (teacher_id = 3) - Multiple dates for current month
(3, 5, CURDATE(), 'present', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'late', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'present', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 3),
(3, 5, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'absent', 3),

(3, 6, CURDATE(), 'absent', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'present', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'late', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 3),
(3, 6, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'present', 3),

(3, 9, CURDATE(), 'present', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'late', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'present', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'present', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'absent', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 3),
(3, 9, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'present', 3),

-- CS class (teacher_id = 4) - Multiple dates for current month
(5, 6, CURDATE(), 'present', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'present', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'late', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 4),
(5, 6, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'absent', 4),

(5, 8, CURDATE(), 'present', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'late', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'present', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'present', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'late', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'present', 4),
(5, 8, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'present', 4),

(5, 9, CURDATE(), 'late', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'present', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'present', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'absent', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'present', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'present', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'late', 4),
(5, 9, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'present', 4);

-- Create materials table
CREATE TABLE IF NOT EXISTS `materials` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample materials
INSERT INTO `materials` (`class_id`, `teacher_id`, `title`, `description`, `type`) VALUES
(1, 2, 'Algebra Basics', 'Introduction to algebraic concepts and operations', 'file'),
(1, 2, 'Geometry Formulas', 'Essential geometry formulas and theorems', 'file'),
(3, 3, 'Shakespeare Study Guide', 'Comprehensive guide to Shakespeare\'s works', 'file'),
(3, 3, 'Poetry Analysis', 'Techniques for analyzing poetry', 'file'),
(5, 4, 'Programming Fundamentals', 'Basic programming concepts and syntax', 'file'),
(5, 4, 'HTML Tutorial', 'Complete HTML tutorial for beginners', 'file'),
(6, 4, 'CSS Styling Guide', 'Modern CSS techniques and best practices', 'file');

-- Create assignments table
CREATE TABLE IF NOT EXISTS `assignments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
    `teacher_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text,
    `due_date` datetime NOT NULL,
    `is_graded` tinyint(1) NOT NULL DEFAULT 0,
    `max_points` int(11) DEFAULT NULL,
    `allow_late` tinyint(1) NOT NULL DEFAULT 0,
    `allow_resubmission` tinyint(1) NOT NULL DEFAULT 0,
    `status` enum('active','archived') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample assignments
INSERT INTO `assignments` (
    `class_id`, 
    `teacher_id`, 
    `title`, 
    `description`, 
    `due_date`, 
    `max_points`, 
    `is_graded`,
    `allow_late`,
    `allow_resubmission`
) VALUES
(1, 2, 'Algebra Quiz 1', 'Basic algebraic operations and equations', DATE_ADD(NOW(), INTERVAL 7 DAY), 100, 1, 0, 0),
(1, 2, 'Geometry Project', 'Create a presentation on geometric shapes', DATE_ADD(NOW(), INTERVAL 14 DAY), 100, 1, 1, 1),
(3, 3, 'Shakespeare Essay', 'Analysis of Macbeth themes', DATE_ADD(NOW(), INTERVAL 10 DAY), 100, 1, 0, 0),
(3, 3, 'Poetry Writing', 'Write an original poem', DATE_ADD(NOW(), INTERVAL 5 DAY), 50, 1, 1, 1),
(5, 4, 'Programming Exercise', 'Basic programming exercises in Python', DATE_ADD(NOW(), INTERVAL 3 DAY), 100, 1, 0, 0),
(5, 4, 'HTML Project', 'Create a simple webpage', DATE_ADD(NOW(), INTERVAL 21 DAY), 100, 1, 1, 1),
(6, 4, 'CSS Challenge', 'Style a webpage using CSS', DATE_ADD(NOW(), INTERVAL 15 DAY), 100, 1, 0, 0);

-- Create assignment_files table
CREATE TABLE IF NOT EXISTS `assignment_files` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` int(11) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_type` varchar(50) NOT NULL,
    `file_size` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create submissions table
CREATE TABLE IF NOT EXISTS `submissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` int(11) NOT NULL,
    `student_id` int(11) NOT NULL,
    `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_late` tinyint(1) NOT NULL DEFAULT 0,
    `grade` decimal(5,2) DEFAULT NULL,
    `feedback` text,
    `status` enum('submitted','graded','late') NOT NULL DEFAULT 'submitted',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
    FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample submissions
INSERT INTO `submissions` (
    `assignment_id`, 
    `student_id`, 
    `submitted_at`, 
    `is_late`,
    `grade`, 
    `feedback`, 
    `status`
) VALUES
(1, 5, DATE_SUB(NOW(), INTERVAL 2 DAY), 0, 95.00, 'Excellent work!', 'graded'),
(1, 6, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, 88.50, 'Good effort, but check your calculations.', 'graded'),
(2, 5, DATE_SUB(NOW(), INTERVAL 3 DAY), 0, 92.00, 'Creative and well-presented.', 'graded'),
(2, 8, DATE_SUB(NOW(), INTERVAL 2 DAY), 0, 85.00, 'Good work, but could use more detail.', 'graded'),
(3, 5, DATE_SUB(NOW(), INTERVAL 5 DAY), 0, 97.00, 'Outstanding analysis!', 'graded'),
(3, 6, DATE_SUB(NOW(), INTERVAL 4 DAY), 0, 89.00, 'Well written, but could improve citations.', 'graded'),
(4, 7, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, NULL, NULL, 'submitted'),
(5, 8, DATE_SUB(NOW(), INTERVAL 2 DAY), 0, 91.00, 'Great code structure!', 'graded'),
(5, 9, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, NULL, NULL, 'submitted');

-- Create submission_files table
CREATE TABLE IF NOT EXISTS `submission_files` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `submission_id` int(11) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_type` varchar(50) NOT NULL,
    `file_size` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Create announcements table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `content` text NOT NULL,
    `author_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample announcements
INSERT INTO `announcements` (`class_id`, `title`, `content`, `author_id`) VALUES
(1, 'Welcome to Mathematics 101', 'Welcome to our math class! Please review the syllabus.', 2),
(3, 'Essay Guidelines', 'Updated guidelines for the upcoming Shakespeare essay.', 3),
(5, 'Project Deadline Extended', 'The programming project deadline has been extended by one week.', 4);

-- Create comments table
CREATE TABLE IF NOT EXISTS `comments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `announcement_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `content` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample comments
INSERT INTO `comments` (`announcement_id`, `user_id`, `content`) VALUES
(1, 5, 'Thank you for the welcome!'),
(1, 6, 'Looking forward to the class.'),
(2, 9, 'Could you clarify the essay length requirement?'),
(3, 8, 'Thanks for the extension!');

-- Create messages table
CREATE TABLE IF NOT EXISTS `messages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample messages
INSERT INTO `messages` (`sender_id`, `receiver_id`, `subject`, `message`, `is_read`) VALUES
(2, 5, 'Assignment Question', 'Could you clarify your question about the algebra homework?', 0),
(5, 2, 'Re: Assignment Question', 'Yes, I was confused about question 3.', 1),
(3, 6, 'Essay Feedback', 'Please see me after class to discuss your essay.', 0);

-- Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `type` enum('assignment','material','grade','announcement','message','class_created','student_joined','class_joined','submission','submission_confirmed','attendance','assignment_reminder') NOT NULL,
    `related_id` int(11) DEFAULT NULL,
    `is_read` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`) VALUES
(5, 'New Assignment', 'A new math assignment has been posted.', 'assignment', 0),
(6, 'Grade Posted', 'Your grade for the English essay has been posted.', 'grade', 0),
(7, 'New Announcement', 'There is a new announcement in your CS class.', 'announcement', 0),
(2, 'Class Created Successfully', 'Your class Mathematics 101 (A) has been created with code: MATH101', 'class_created', 0),
(5, 'Successfully Joined Class', 'You have successfully joined Mathematics 101 taught by John Smith', 'class_joined', 0),
(2, 'New Student Joined', 'Emily Wilson has joined your class Mathematics 101', 'student_joined', 0);

-- Create user_settings table
CREATE TABLE IF NOT EXISTS `user_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `browser_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `assignment_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `grade_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `announcement_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `message_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `material_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `attendance_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `class_notifications` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings for all users
INSERT INTO `user_settings` (`user_id`, `email_notifications`, `browser_notifications`, `assignment_notifications`, `grade_notifications`, `announcement_notifications`, `message_notifications`, `material_notifications`, `attendance_notifications`, `class_notifications`)
SELECT id, 1, 1, 1, 1, 1, 1, 1, 1, 1 FROM users;

-- Create admin_logs table for content moderation tracking
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `action` enum('remove','warn','approve','reject') NOT NULL,
    `content_type` enum('announcement','comment','material','assignment','user') NOT NULL,
    `content_id` int(11) NOT NULL,
    `reason` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX `idx_classes_teacher` ON `classes` (`teacher_id`);
CREATE INDEX `idx_classes_code` ON `classes` (`class_code`);
CREATE INDEX `idx_enrollments_student` ON `class_enrollments` (`student_id`);
CREATE INDEX `idx_enrollments_class` ON `class_enrollments` (`class_id`);
CREATE INDEX `idx_materials_class` ON `materials` (`class_id`);
CREATE INDEX `idx_assignments_class` ON `assignments` (`class_id`);
CREATE INDEX `idx_assignments_teacher` ON `assignments` (`teacher_id`);
CREATE INDEX `idx_submissions_assignment` ON `submissions` (`assignment_id`);
CREATE INDEX `idx_submissions_student` ON `submissions` (`student_id`);
CREATE INDEX `idx_attendance_class` ON `attendance` (`class_id`);
CREATE INDEX `idx_attendance_student` ON `attendance` (`student_id`);
CREATE INDEX `idx_announcements_class` ON `announcements` (`class_id`);
CREATE INDEX `idx_announcements_author` ON `announcements` (`author_id`);
CREATE INDEX `idx_comments_announcement` ON `comments` (`announcement_id`);
CREATE INDEX `idx_comments_user` ON `comments` (`user_id`);
CREATE INDEX `idx_notifications_user` ON `notifications` (`user_id`);
CREATE INDEX `idx_notifications_unread` ON `notifications` (`user_id`, `is_read`);
CREATE INDEX `idx_messages_recipient` ON `messages` (`receiver_id`);
CREATE INDEX `idx_messages_unread` ON `messages` (`receiver_id`, `is_read`);
CREATE INDEX `idx_admin_logs_admin` ON `admin_logs` (`admin_id`);
CREATE INDEX `idx_admin_logs_content` ON `admin_logs` (`content_type`, `content_id`);

-- Display import summary
SELECT 'Database import completed successfully!' as status;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_classes FROM classes;
SELECT COUNT(*) as total_assignments FROM assignments;
SELECT COUNT(*) as total_materials FROM materials;
