<?php
/**
 * Email Notification System for Google Classroom Clone
 * Handles all email notifications with templates and configuration
 */

// Email configuration
define('EMAIL_FROM_NAME', 'Google Classroom Clone');
define('EMAIL_FROM_ADDRESS', 'noreply@classroomclone.com');
define('EMAIL_SMTP_HOST', 'localhost'); // Change to your SMTP server
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USERNAME', '');
define('EMAIL_SMTP_PASSWORD', '');
define('EMAIL_SMTP_SECURE', 'tls');

/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param string $alt_message Plain text alternative
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $message, $alt_message = '') {
    // Use PHP's built-in mail function for simplicity
    // In production, consider using PHPMailer or similar library
    
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM_ADDRESS . '>';
    $headers[] = 'Reply-To: ' . EMAIL_FROM_ADDRESS;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    $alt_message = $alt_message ?: strip_tags($message);
    
    // Add plain text version
    $full_message = "
    <html>
    <head>
        <title>$subject</title>
    </head>
    <body>
        $message
    </body>
    </html>
    ";
    
    return mail($to, $subject, $full_message, implode("\r\n", $headers));
}

/**
 * Send welcome email to new user
 * @param string $email User email
 * @param string $name User name
 * @param string $role User role
 * @param string $password Plain text password (for new accounts)
 * @return bool Success status
 */
function sendWelcomeEmail($email, $name, $role, $password = '') {
    $subject = 'Welcome to Google Classroom Clone';
    
    $message = getEmailTemplate('welcome', [
        'name' => $name,
        'role' => ucfirst($role),
        'password' => $password,
        'login_url' => getBaseUrl() . '/index.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send new assignment notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $assignment_title Assignment title
 * @param string $class_name Class name
 * @param string $due_date Due date
 * @return bool Success status
 */
function sendAssignmentNotification($email, $student_name, $assignment_title, $class_name, $due_date) {
    $subject = "New Assignment: $assignment_title";
    
    $message = getEmailTemplate('new_assignment', [
        'student_name' => $student_name,
        'assignment_title' => $assignment_title,
        'class_name' => $class_name,
        'due_date' => formatDateTime($due_date),
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send new material notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $material_title Material title
 * @param string $class_name Class name
 * @return bool Success status
 */
function sendMaterialNotification($email, $student_name, $material_title, $class_name) {
    $subject = "New Material Available: $material_title";
    
    $message = getEmailTemplate('new_material', [
        'student_name' => $student_name,
        'material_title' => $material_title,
        'class_name' => $class_name,
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send new announcement notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $announcement_title Announcement title
 * @param string $class_name Class name
 * @param bool $is_important Whether announcement is marked as important
 * @return bool Success status
 */
function sendAnnouncementNotification($email, $student_name, $announcement_title, $class_name, $is_important = false) {
    $subject = $is_important ? "IMPORTANT: $announcement_title" : "New Announcement: $announcement_title";
    
    $message = getEmailTemplate('new_announcement', [
        'student_name' => $student_name,
        'announcement_title' => $announcement_title,
        'class_name' => $class_name,
        'is_important' => $is_important,
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send grade notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $assignment_title Assignment title
 * @param string $class_name Class name
 * @param int $score Score received
 * @param int $max_points Maximum possible points
 * @param string $feedback Teacher feedback
 * @return bool Success status
 */
function sendGradeNotification($email, $student_name, $assignment_title, $class_name, $score, $max_points, $feedback = '') {
    $subject = "Grade Posted: $assignment_title";
    
    $percentage = $max_points > 0 ? round(($score / $max_points) * 100, 1) : 0;
    
    $message = getEmailTemplate('grade_posted', [
        'student_name' => $student_name,
        'assignment_title' => $assignment_title,
        'class_name' => $class_name,
        'score' => $score,
        'max_points' => $max_points,
        'percentage' => $percentage,
        'feedback' => $feedback,
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send teacher approval notification
 * @param string $email Teacher email
 * @param string $teacher_name Teacher name
 * @param bool $approved Whether account was approved or rejected
 * @param string $reason Reason for rejection (if applicable)
 * @return bool Success status
 */
function sendTeacherApprovalNotification($email, $teacher_name, $approved, $reason = '') {
    $subject = $approved ? 'Account Approved - Welcome!' : 'Account Status Update';
    
    $message = getEmailTemplate('teacher_approval', [
        'teacher_name' => $teacher_name,
        'approved' => $approved,
        'reason' => $reason,
        'login_url' => getBaseUrl() . '/index.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send password reset email
 * @param string $email User email
 * @param string $name User name
 * @param string $reset_token Reset token
 * @return bool Success status
 */
function sendPasswordResetEmail($email, $name, $reset_token) {
    $subject = 'Password Reset Request';
    
    $reset_url = getBaseUrl() . '/reset_password.php?token=' . $reset_token;
    
    $message = getEmailTemplate('password_reset', [
        'name' => $name,
        'reset_url' => $reset_url,
        'expiry_hours' => 24
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send class invitation email
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $class_name Class name
 * @param string $teacher_name Teacher name
 * @param string $class_code Class code
 * @return bool Success status
 */
function sendClassInvitationEmail($email, $student_name, $class_name, $teacher_name, $class_code) {
    $subject = "You're invited to join: $class_name";
    
    $message = getEmailTemplate('class_invitation', [
        'student_name' => $student_name,
        'class_name' => $class_name,
        'teacher_name' => $teacher_name,
        'class_code' => $class_code,
        'join_url' => getBaseUrl() . '/student/join_class.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send attendance notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $class_name Class name
 * @param string $date Attendance date
 * @param string $teacher_name Teacher name
 * @return bool Success status
 */
function sendAttendanceNotification($email, $student_name, $class_name, $date, $teacher_name) {
    $subject = "Attendance Marked: $class_name";
    
    $message = getEmailTemplate('attendance_marked', [
        'student_name' => $student_name,
        'class_name' => $class_name,
        'date' => formatDate($date),
        'teacher_name' => $teacher_name,
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send class update notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $class_name Class name
 * @param string $update_type Type of update
 * @param string $update_details Update details
 * @param string $teacher_name Teacher name
 * @return bool Success status
 */
function sendClassUpdateNotification($email, $student_name, $class_name, $update_type, $update_details, $teacher_name) {
    $subject = "Class Update: $class_name";
    
    $message = getEmailTemplate('class_update', [
        'student_name' => $student_name,
        'class_name' => $class_name,
        'update_type' => $update_type,
        'update_details' => $update_details,
        'teacher_name' => $teacher_name,
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send message notification
 * @param string $email Recipient email
 * @param string $recipient_name Recipient name
 * @param string $sender_name Sender name
 * @param string $message_preview Message preview
 * @return bool Success status
 */
function sendMessageNotification($email, $recipient_name, $sender_name, $message_preview) {
    $subject = "New Message from $sender_name";
    
    $message = getEmailTemplate('new_message', [
        'recipient_name' => $recipient_name,
        'sender_name' => $sender_name,
        'message_preview' => $message_preview,
        'login_url' => getBaseUrl() . '/messages.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send assignment reminder notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $assignment_title Assignment title
 * @param string $class_name Class name
 * @param string $due_date Due date
 * @return bool Success status
 */
function sendAssignmentReminderNotification($email, $student_name, $assignment_title, $class_name, $due_date) {
    $subject = "Assignment Reminder: $assignment_title";
    
    $message = getEmailTemplate('assignment_reminder', [
        'student_name' => $student_name,
        'assignment_title' => $assignment_title,
        'class_name' => $class_name,
        'due_date' => formatDateTime($due_date),
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Send resubmission enabled notification
 * @param string $email Student email
 * @param string $student_name Student name
 * @param string $assignment_title Assignment title
 * @param string $class_name Class name
 * @return bool Success status
 */
function sendResubmissionEnabledEmail($email, $student_name, $assignment_title, $class_name) {
    $subject = "Resubmission Enabled: $assignment_title";
    
    $message = getEmailTemplate('resubmission_enabled', [
        'student_name' => $student_name,
        'assignment_title' => $assignment_title,
        'class_name' => $class_name,
        'login_url' => getBaseUrl() . '/student/dashboard.php'
    ]);
    
    return sendEmailNotification($email, $subject, $message);
}

/**
 * Get email template
 * @param string $template_name Template name
 * @param array $variables Variables to replace in template
 * @return string Processed template
 */
function getEmailTemplate($template_name, $variables = []) {
    $template = getEmailTemplateContent($template_name);
    
    // Replace variables in template
    foreach ($variables as $key => $value) {
        $template = str_replace("{{" . $key . "}}", $value, $template);
    }
    
    return $template;
}

/**
 * Get email template content
 * @param string $template_name Template name
 * @return string Template content
 */
function getEmailTemplateContent($template_name) {
    $templates = [
        'welcome' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Welcome to Google Classroom Clone!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Welcome to our learning management system! Your account has been successfully created as a <strong>{{role}}</strong>.
                </p>
                ' . (isset($variables['password']) && $variables['password'] ? '
                <div style="background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0; color: #495057;"><strong>Your temporary password:</strong> {{password}}</p>
                    <p style="margin: 10px 0 0 0; color: #6c757d; font-size: 14px;">Please change your password after your first login.</p>
                </div>
                ' : '') . '
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    You can now log in to your account and start using our platform.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Login Now</a>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    If you have any questions or need assistance, please don\'t hesitate to contact our support team.
                </p>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'new_assignment' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">New Assignment Posted!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    A new assignment has been posted in your class <strong>{{class_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #ff6b6b; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">{{assignment_title}}</h3>
                    <p style="color: #666; margin: 0;"><strong>Due Date:</strong> {{due_date}}</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Please log in to view the assignment details and submit your work on time.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #ff6b6b; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Assignment</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'new_material' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">New Material Available!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    New learning material has been added to your class <strong>{{class_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #4ecdc4; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">{{material_title}}</h3>
                    <p style="color: #666; margin: 0;">Check it out in your class materials section.</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Log in to access the new material and enhance your learning experience.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #4ecdc4; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Material</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'new_announcement' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">{{is_important ? "IMPORTANT ANNOUNCEMENT" : "New Announcement"}}</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    ' . ($variables['is_important'] ? 'An important announcement has been posted in your class <strong>{{class_name}}</strong>.' : 'A new announcement has been posted in your class <strong>{{class_name}}</strong>.') . '
                </p>
                <div style="background: white; border-left: 4px solid #f093fb; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">{{announcement_title}}</h3>
                    <p style="color: #666; margin: 0;">Please read the full announcement for important details.</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Log in to view the complete announcement and stay updated with your class.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #f093fb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Read Announcement</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'grade_posted' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Grade Posted!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Your grade has been posted for an assignment in <strong>{{class_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">{{assignment_title}}</h3>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #666;"><strong>Score:</strong></span>
                        <span style="color: #333; font-weight: bold;">{{score}} / {{max_points}} ({{percentage}}%)</span>
                    </div>
                    ' . (isset($variables['feedback']) && $variables['feedback'] ? '
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <p style="color: #666; margin: 0;"><strong>Feedback:</strong></p>
                        <p style="color: #333; margin: 5px 0 0 0;">{{feedback}}</p>
                    </div>
                    ' : '') . '
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Log in to view your detailed grade report and feedback.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Grade</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'teacher_approval' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, {{approved ? "#28a745" : "#dc3545"}} 0%, {{approved ? "#20c997" : "#c82333"}} 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">{{approved ? "Account Approved!" : "Account Status Update"}}</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{teacher_name}},</h2>
                ' . ($variables['approved'] ? '
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Congratulations! Your teacher account has been approved. You can now create classes and start teaching on our platform.
                </p>
                <div style="background: white; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">What\'s Next?</h3>
                    <ul style="color: #666; margin: 0; padding-left: 20px;">
                        <li>Log in to your account</li>
                        <li>Create your first class</li>
                        <li>Invite students to join</li>
                        <li>Start posting materials and assignments</li>
                    </ul>
                </div>
                ' : '
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    We regret to inform you that your teacher account application has not been approved at this time.
                </p>
                ' . (isset($variables['reason']) && $variables['reason'] ? '
                <div style="background: white; border-left: 4px solid #dc3545; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Reason:</h3>
                    <p style="color: #666; margin: 0;">{{reason}}</p>
                </div>
                ' : '') . '
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    You may reapply in the future with additional information or clarification.
                </p>
                ') . '
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: {{approved ? "#28a745" : "#6c757d"}}; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">{{approved ? "Start Teaching" : "Login"}}</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'password_reset' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Password Reset Request</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    We received a request to reset your password. Click the button below to create a new password.
                </p>
                <div style="background: white; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0;">
                    <p style="color: #666; margin: 0;"><strong>Important:</strong> This link will expire in {{expiry_hours}} hours for security reasons.</p>
                </div>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{reset_url}}" style="background: #ffc107; color: #333; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Reset Password</a>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    If you didn\'t request this password reset, you can safely ignore this email.
                </p>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'class_invitation' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">You\'re Invited!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    You\'ve been invited to join a class on Google Classroom Clone!
                </p>
                <div style="background: white; border-left: 4px solid #17a2b8; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">{{class_name}}</h3>
                    <p style="color: #666; margin: 5px 0;"><strong>Teacher:</strong> {{teacher_name}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Class Code:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">{{class_code}}</code></p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Click the button below to join the class and start learning!
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{join_url}}" style="background: #17a2b8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Join Class</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'attendance_marked' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Attendance Marked!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Your attendance has been marked for <strong>{{class_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Attendance Details</h3>
                    <p style="color: #666; margin: 5px 0;"><strong>Class:</strong> {{class_name}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Date:</strong> {{date}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Teacher:</strong> {{teacher_name}}</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    You can view your attendance record and other class information by logging into your account.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Attendance</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'class_update' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Class Update!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    There has been an update in your class <strong>{{class_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #6f42c1; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Update Details</h3>
                    <p style="color: #666; margin: 5px 0;"><strong>Type:</strong> {{update_type}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Details:</strong> {{update_details}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Teacher:</strong> {{teacher_name}}</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Log in to your account to view the complete update and stay informed about your class.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #6f42c1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">View Update</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'new_message' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #fd7e14 0%, #e55a00 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">New Message!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{recipient_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    You have received a new message from <strong>{{sender_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #fd7e14; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Message Preview</h3>
                    <p style="color: #666; margin: 0; font-style: italic;">{{message_preview}}</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Log in to your account to read the complete message and respond.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #fd7e14; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Read Message</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'assignment_reminder' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Assignment Due Soon!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    This is a reminder that you have an assignment due soon in <strong>{{class_name}}</strong>.
                </p>
                <div style="background: white; border-left: 4px solid #dc3545; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Assignment Details</h3>
                    <p style="color: #666; margin: 5px 0;"><strong>Title:</strong> {{assignment_title}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Due Date:</strong> {{due_date}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Class:</strong> {{class_name}}</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Please make sure to submit your assignment on time to avoid any penalties.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Submit Assignment</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        ',
        
        'resubmission_enabled' => '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">Resubmission Enabled!</h1>
            </div>
            <div style="padding: 30px; background: #f8f9fa;">
                <h2 style="color: #333; margin-bottom: 20px;">Hello {{student_name}},</h2>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    Your assignment <strong>{{assignment_title}}</strong> in <strong>{{class_name}}</strong> now allows resubmission.
                </p>
                <div style="background: white; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #333; margin: 0 0 10px 0;">Assignment Details</h3>
                    <p style="color: #666; margin: 5px 0;"><strong>Title:</strong> {{assignment_title}}</p>
                    <p style="color: #666; margin: 5px 0;"><strong>Class:</strong> {{class_name}}</p>
                </div>
                <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                    You can now submit your assignment again if you wish to improve your score.
                </p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{login_url}}" style="background: #ffc107; color: #333; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Submit Assignment</a>
                </div>
                <p style="color: #666; line-height: 1.6;">
                    Best regards,<br>
                    The Google Classroom Clone Team
                </p>
            </div>
        </div>
        '
    ];
    
    return isset($templates[$template_name]) ? $templates[$template_name] : '';
}

/**
 * Get base URL for the application
 * @return string Base URL
 */
function getBaseUrl() {
    // Handle CLI environment
    if (php_sapi_name() === 'cli') {
        return 'http://localhost/test';
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME'] ?? '/test');
    
    return $protocol . '://' . $host . $path;
}

/**
 * Send bulk email notifications
 * @param array $recipients Array of recipient data
 * @param string $template_name Template to use
 * @param array $common_variables Common variables for all emails
 * @return array Results array with success/failure counts
 */
function sendBulkEmailNotifications($recipients, $template_name, $common_variables = []) {
    $results = [
        'total' => count($recipients),
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    foreach ($recipients as $recipient) {
        $variables = array_merge($common_variables, $recipient);
        
        $success = sendEmailNotification(
            $recipient['email'],
            $recipient['subject'],
            getEmailTemplate($template_name, $variables),
            $recipient['alt_message'] ?? ''
        );
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failed']++;
            $results['errors'][] = "Failed to send email to: " . $recipient['email'];
        }
        
        // Add small delay to prevent overwhelming the mail server
        usleep(100000); // 0.1 second delay
    }
    
    return $results;
}

/**
 * Test email configuration
 * @param string $test_email Email address to send test to
 * @return array Test results
 */
function testEmailConfiguration($test_email) {
    $subject = 'Email Configuration Test';
    $message = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <div style="background: #28a745; color: white; padding: 30px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px;">Email Test Successful!</h1>
        </div>
        <div style="padding: 30px; background: #f8f9fa;">
            <h2 style="color: #333; margin-bottom: 20px;">Hello!</h2>
            <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                This is a test email to verify that your email configuration is working correctly.
            </p>
            <div style="background: white; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
                <h3 style="color: #333; margin: 0 0 10px 0;">Configuration Details:</h3>
                <p style="color: #666; margin: 5px 0;"><strong>From:</strong> ' . EMAIL_FROM_NAME . ' &lt;' . EMAIL_FROM_ADDRESS . '&gt;</p>
                <p style="color: #666; margin: 5px 0;"><strong>SMTP Host:</strong> ' . EMAIL_SMTP_HOST . '</p>
                <p style="color: #666; margin: 5px 0;"><strong>Test Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
            </div>
            <p style="color: #666; line-height: 1.6;">
                If you received this email, your email configuration is working properly!
            </p>
            <p style="color: #666; line-height: 1.6;">
                Best regards,<br>
                The Google Classroom Clone Team
            </p>
        </div>
    </div>
    ';
    
    $success = sendEmailNotification($test_email, $subject, $message);
    
    return [
        'success' => $success,
        'message' => $success ? 'Email sent successfully!' : 'Failed to send email. Check your configuration.',
        'config' => [
            'from_name' => EMAIL_FROM_NAME,
            'from_address' => EMAIL_FROM_ADDRESS,
            'smtp_host' => EMAIL_SMTP_HOST,
            'smtp_port' => EMAIL_SMTP_PORT
        ]
    ];
}
?>
