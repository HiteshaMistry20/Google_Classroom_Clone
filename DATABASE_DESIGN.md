# Classroom Management System - Database Design Document

## Overview
This document describes the database design for a comprehensive classroom management system that supports teachers, students, and administrators. The system manages classes, assignments, materials, attendance, communications, and notifications.

## Database: `classroom_clone`
**Engine:** InnoDB  
**Character Set:** utf8mb4  
**Collation:** utf8mb4_unicode_ci

---

## Table Structure

### 1. USERS TABLE
**Purpose:** Stores all system users (admin, teachers, students)

```sql
CREATE TABLE `users` (
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
);
```

**Key Features:**
- Unique email constraint
- Role-based access control
- Account status management
- Profile image support
- Audit timestamps

**Indexes:**
- Primary Key: `id`
- Unique: `email`

---

### 2. CLASSES TABLE
**Purpose:** Stores class information and teacher assignments

```sql
CREATE TABLE `classes` (
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
);
```

**Key Features:**
- Unique class codes for enrollment
- QR code support for mobile access
- Class status management
- Teacher ownership

**Indexes:**
- Primary Key: `id`
- Unique: `class_code`
- Index: `teacher_id`

---

### 3. CLASS_ENROLLMENTS TABLE
**Purpose:** Manages student enrollment in classes

```sql
CREATE TABLE `class_enrollments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
    `student_id` int(11) NOT NULL,
    `enrolled_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_enrollment` (`class_id`, `student_id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Prevents duplicate enrollments
- Automatic enrollment timestamp
- Cascading deletes

**Indexes:**
- Primary Key: `id`
- Unique: `class_id`, `student_id`
- Index: `student_id`
- Index: `class_id`

---

### 4. ATTENDANCE TABLE
**Purpose:** Tracks daily student attendance

```sql
CREATE TABLE `attendance` (
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
);
```

**Key Features:**
- Prevents duplicate attendance records per day
- Tracks who marked the attendance
- Three attendance statuses
- Date-based tracking

**Indexes:**
- Primary Key: `id`
- Unique: `class_id`, `student_id`, `attendance_date`
- Index: `class_id`
- Index: `student_id`

---

### 5. MATERIALS TABLE
**Purpose:** Stores class materials and resources

```sql
CREATE TABLE `materials` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
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
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Supports multiple material types (file, link, video)
- File metadata tracking
- Class-specific organization

**Indexes:**
- Primary Key: `id`
- Index: `class_id`

---

### 6. ASSIGNMENTS TABLE
**Purpose:** Manages class assignments and homework

```sql
CREATE TABLE `assignments` (
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
);
```

**Key Features:**
- Flexible grading options
- Late submission policies
- Resubmission allowances
- Assignment status management

**Indexes:**
- Primary Key: `id`
- Index: `class_id`
- Index: `teacher_id`

---

### 7. ASSIGNMENT_FILES TABLE
**Purpose:** Stores files attached to assignments

```sql
CREATE TABLE `assignment_files` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` int(11) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_type` varchar(50) NOT NULL,
    `file_size` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Multiple files per assignment
- File metadata tracking
- Cascading deletes

**Indexes:**
- Primary Key: `id`
- Index: `assignment_id`

---

### 8. SUBMISSIONS TABLE
**Purpose:** Tracks student assignment submissions

```sql
CREATE TABLE `submissions` (
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
);
```

**Key Features:**
- One submission per student per assignment
- Late submission tracking
- Grading and feedback support
- Submission status management

**Indexes:**
- Primary Key: `id`
- Unique: `assignment_id`, `student_id`
- Index: `assignment_id`
- Index: `student_id`

---

### 9. SUBMISSION_FILES TABLE
**Purpose:** Stores files submitted by students

```sql
CREATE TABLE `submission_files` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `submission_id` int(11) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_type` varchar(50) NOT NULL,
    `file_size` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Multiple files per submission
- File metadata tracking
- Cascading deletes

**Indexes:**
- Primary Key: `id`
- Index: `submission_id`

---

### 10. ANNOUNCEMENTS TABLE
**Purpose:** Stores class announcements and updates

```sql
CREATE TABLE `announcements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `content` text NOT NULL,
    `author_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Class-specific announcements
- Author tracking
- Timestamp for chronological ordering

**Indexes:**
- Primary Key: `id`
- Index: `class_id`
- Index: `author_id`

---

### 11. COMMENTS TABLE
**Purpose:** Stores comments on announcements

```sql
CREATE TABLE `comments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `announcement_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `content` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`announcement_id`) REFERENCES `announcements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Multiple comments per announcement
- User attribution
- Chronological ordering

**Indexes:**
- Primary Key: `id`
- Index: `announcement_id`
- Index: `user_id`

---

### 12. MESSAGES TABLE
**Purpose:** Stores private messages between users

```sql
CREATE TABLE `messages` (
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
);
```

**Key Features:**
- Private messaging system
- Read/unread status
- Subject and message content
- Sender/receiver tracking

**Indexes:**
- Primary Key: `id`
- Index: `sender_id`
- Index: `receiver_id`
- Index: `receiver_id`, `is_read`

---

### 13. NOTIFICATIONS TABLE
**Purpose:** Stores system notifications for users

```sql
CREATE TABLE `notifications` (
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
);
```

**Key Features:**
- Multiple notification types
- Read/unread status
- Related content linking
- User-specific notifications

**Indexes:**
- Primary Key: `id`
- Index: `user_id`
- Index: `user_id`, `is_read`

---

### 14. USER_SETTINGS TABLE
**Purpose:** Stores user notification preferences

```sql
CREATE TABLE `user_settings` (
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
);
```

**Key Features:**
- Granular notification preferences
- One settings record per user
- Default enabled settings
- Timestamp tracking

**Indexes:**
- Primary Key: `id`
- Unique: `user_id`

---

### 15. ADMIN_LOGS TABLE
**Purpose:** Tracks administrative actions for moderation

```sql
CREATE TABLE `admin_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `admin_id` int(11) NOT NULL,
    `action` enum('remove','warn','approve','reject') NOT NULL,
    `content_type` enum('announcement','comment','material','assignment','user') NOT NULL,
    `content_id` int(11) NOT NULL,
    `reason` text,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
```

**Key Features:**
- Administrative action tracking
- Content moderation support
- Reason documentation
- Audit trail

**Indexes:**
- Primary Key: `id`
- Index: `admin_id`
- Index: `content_type`, `content_id`

---

## Database Relationships

### Primary Relationships:
1. **Users → Classes** (One-to-Many): Teachers create multiple classes
2. **Classes → Class_Enrollments** (One-to-Many): Classes have multiple enrolled students
3. **Users → Class_Enrollments** (One-to-Many): Students enroll in multiple classes
4. **Classes → Materials** (One-to-Many): Classes have multiple materials
5. **Classes → Assignments** (One-to-Many): Classes have multiple assignments
6. **Assignments → Submissions** (One-to-Many): Assignments have multiple student submissions
7. **Classes → Announcements** (One-to-Many): Classes have multiple announcements
8. **Announcements → Comments** (One-to-Many): Announcements have multiple comments
9. **Users → Messages** (One-to-Many): Users send/receive multiple messages
10. **Users → Notifications** (One-to-Many): Users have multiple notifications

