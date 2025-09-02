# Comprehensive Notification System

This document describes the comprehensive notification system implemented for the Google Classroom Clone application.

## Overview

The notification system provides real-time notifications for all major activities in the classroom management system, including:

- **Class Management**: Class creation, student enrollment, class updates
- **Assignments**: New assignments, submissions, grading, due date reminders
- **Materials**: New material uploads
- **Announcements**: New announcements and updates
- **Messages**: Direct messaging between users
- **Attendance**: Attendance marking and updates

## Features

### 1. Multi-Channel Notifications
- **In-App Notifications**: Real-time notifications within the application
- **Email Notifications**: Email alerts for important events
- **Browser Notifications**: Desktop notifications (when permission granted)

### 2. Granular Notification Preferences
Users can customize their notification preferences for:
- Email notifications (on/off)
- Browser notifications (on/off)
- Assignment notifications
- Grade notifications
- Announcement notifications
- Message notifications
- Material notifications
- Attendance notifications
- Class notifications

### 3. Notification Types

#### For Teachers:
- **Class Created**: Confirmation when a new class is created
- **Student Joined**: Notification when a new student joins their class
- **Assignment Submitted**: Notification when students submit assignments
- **New Messages**: Incoming messages from students

#### For Students:
- **Class Joined**: Confirmation when successfully joining a class
- **New Assignment**: When teachers post new assignments
- **Assignment Due Reminder**: Reminders for upcoming due dates
- **Grade Posted**: When assignments are graded
- **New Material**: When teachers upload new materials
- **New Announcement**: When teachers post announcements
- **Attendance Marked**: When attendance is recorded
- **New Messages**: Incoming messages from teachers

## Database Schema

### Notifications Table
```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('assignment','material','grade','announcement','message',
              'class_created','student_joined','class_joined','submission',
              'submission_confirmed','attendance','assignment_reminder') NOT NULL,
    related_id INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### User Settings Table
```sql
CREATE TABLE user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    email_notifications TINYINT(1) DEFAULT 1,
    browser_notifications TINYINT(1) DEFAULT 1,
    assignment_notifications TINYINT(1) DEFAULT 1,
    grade_notifications TINYINT(1) DEFAULT 1,
    announcement_notifications TINYINT(1) DEFAULT 1,
    message_notifications TINYINT(1) DEFAULT 1,
    material_notifications TINYINT(1) DEFAULT 1,
    attendance_notifications TINYINT(1) DEFAULT 1,
    class_notifications TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Implementation Details

### Core Files

1. **`includes/notification_system.php`**: Main notification functions
2. **`teacher/notifications.php`**: Teacher notification interface
3. **`student/notifications.php`**: Student notification interface
4. **`api/notifications.php`**: API endpoints for notifications
5. **`notification_settings.php`**: User notification preferences
6. **`cron/assignment_reminders.php`**: Automated assignment reminders

### Key Functions

#### Notification Creation
```php
function createNotification($pdo, $user_id, $title, $message, $type, $related_id = null)
```

#### Specific Notification Functions
- `notifyClassCreated($pdo, $class_id)`
- `notifyStudentJoinedClass($pdo, $class_id, $student_id)`
- `notifyNewAssignment($pdo, $assignment_id, $class_id)`
- `notifyAssignmentSubmitted($pdo, $submission_id)`
- `notifyGradedAssignment($pdo, $submission_id)`
- `notifyNewAnnouncement($pdo, $announcement_id, $class_id)`
- `notifyNewMaterial($pdo, $material_id, $class_id)`
- `notifyAttendanceMarked($pdo, $class_id, $date, $marked_by)`
- `notifyNewMessage($pdo, $message_id)`
- `notifyAssignmentDueReminder($pdo, $assignment_id)`

#### Preference Check Functions
- `hasEmailNotifications($pdo, $user_id)`
- `hasAssignmentNotifications($pdo, $user_id)`
- `hasGradeNotifications($pdo, $user_id)`
- `hasAnnouncementNotifications($pdo, $user_id)`
- `hasMaterialNotifications($pdo, $user_id)`
- `hasAttendanceNotifications($pdo, $user_id)`
- `hasMessageNotifications($pdo, $user_id)`

## Integration Points

### Teacher Actions
1. **Class Creation** (`teacher/classes.php`)
   - Triggers: `notifyClassCreated()`

2. **Assignment Creation** (`teacher/assignments.php`)
   - Triggers: `notifyNewAssignment()`

3. **Material Upload** (`teacher/materials.php`)
   - Triggers: `notifyNewMaterial()`

4. **Announcement Creation** (`teacher/announcements.php`)
   - Triggers: `notifyNewAnnouncement()`

5. **Attendance Marking** (`teacher/attendance.php`)
   - Triggers: `notifyAttendanceMarked()`

6. **Assignment Grading** (`teacher/view_submissions.php`)
   - Triggers: `notifyGradedAssignment()`

7. **Sending Messages** (`teacher/messages.php`)
   - Triggers: `notifyNewMessage()`

### Student Actions
1. **Joining Class** (`student/join_class.php`)
   - Triggers: `notifyStudentJoinedClass()`

2. **Assignment Submission** (`student/assignment_details.php`)
   - Triggers: `notifyAssignmentSubmitted()`

3. **Sending Messages** (`student/messages.php`)
   - Triggers: `notifyNewMessage()`

## API Endpoints

### GET `/api/notifications.php?action=get`
Returns unread notifications for the current user.

### GET `/api/notifications.php?action=get_all`
Returns all notifications with pagination.

### POST `/api/notifications.php?action=mark_read`
Marks a specific notification as read.

### POST `/api/notifications.php?action=mark_all_read`
Marks all notifications as read.

### POST `/api/notifications.php?action=delete`
Deletes a specific notification.

### GET/POST `/api/notifications.php?action=settings`
Manages user notification preferences.

### GET `/api/notifications.php?action=stats`
Returns notification statistics.

## Automated Tasks

### Assignment Reminders
The system includes an automated script (`cron/assignment_reminders.php`) that:
- Checks for assignments due in the next 24 hours
- Sends reminders to students who haven't submitted
- Prevents duplicate reminders (12-hour cooldown)
- Cleans up old notifications

**Recommended Cron Schedule:**
```bash
# Run every hour
0 * * * * php /path/to/cron/assignment_reminders.php

# Or run every 6 hours
0 */6 * * * php /path/to/cron/assignment_reminders.php
```

## User Interface

### Notification Display
- Real-time notification count in header
- Notification bell icon with unread count
- Detailed notification list with icons and timestamps
- Mark as read/unread functionality
- Delete notifications
- Filter by notification type

### Notification Settings
- Toggle email notifications
- Toggle browser notifications
- Granular control over notification types
- Save preferences

## Browser Notifications

The system supports browser notifications for:
- New assignments
- Grade postings
- New announcements
- New messages
- Assignment reminders

Users must grant permission for browser notifications to work.

## Email Notifications

Email notifications are sent for important events:
- Assignment due reminders
- Grade postings
- New announcements
- New messages

Email templates are customizable in the `includes/email_functions.php` file.

## Security Considerations

1. **User Validation**: All notifications are validated against user permissions
2. **SQL Injection Protection**: All database queries use prepared statements
3. **XSS Protection**: All user input is sanitized before display
4. **CSRF Protection**: Forms include CSRF tokens
5. **Access Control**: Users can only see their own notifications

## Performance Optimization

1. **Database Indexing**: Proper indexes on notification tables
2. **Pagination**: Large notification lists are paginated
3. **Cleanup**: Old notifications are automatically cleaned up
4. **Caching**: Notification counts are cached where appropriate

## Troubleshooting

### Common Issues

1. **Notifications not appearing**
   - Check user notification preferences
   - Verify database connection
   - Check error logs

2. **Email notifications not sending**
   - Verify email configuration
   - Check SMTP settings
   - Review email function logs

3. **Browser notifications not working**
   - Ensure user granted permission
   - Check browser compatibility
   - Verify HTTPS (required for notifications)

### Log Files
- Application logs: Check PHP error logs
- Notification logs: `logs/assignment_reminders.log`
- Email logs: Check email function logs

## Future Enhancements

1. **Push Notifications**: Mobile app integration
2. **SMS Notifications**: Text message alerts
3. **Notification Templates**: Customizable notification messages
4. **Advanced Filtering**: Filter notifications by date, type, class
5. **Notification Groups**: Group related notifications
6. **Notification Analytics**: Track notification engagement

## Support

For technical support or questions about the notification system, please refer to the main application documentation or contact the development team. 