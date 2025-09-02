<?php
// Get current user role and active page
$user_role = $_SESSION['user_role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get base URL for correct navigation
$base_path = '';
$role_path = '';

if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $base_path = '../';
    $role_path = ''; // Already in admin directory
} elseif (strpos($_SERVER['PHP_SELF'], '/teacher/') !== false) {
    $base_path = '../';
    $role_path = ''; // Already in teacher directory
} elseif (strpos($_SERVER['PHP_SELF'], '/student/') !== false) {
    $base_path = '../';
    $role_path = ''; // Already in student directory
} else {
    // We're in the root directory (profile.php, settings.php, etc.)
    $base_path = '';
    $role_path = $user_role . '/';
}

// Special handling for root-level pages
$is_root_page = !strpos($_SERVER['PHP_SELF'], '/admin/') && 
                !strpos($_SERVER['PHP_SELF'], '/teacher/') && 
                !strpos($_SERVER['PHP_SELF'], '/student/') &&
                $_SERVER['PHP_SELF'] !== '/test/index.php';
?>

<nav id="sidebarMenu">
    <div class="sidebar-content">
        <ul class="nav-list">
            <?php if ($user_role === 'teacher'): ?>
                <!-- Teacher Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/dashboard.php' : 'dashboard.php'; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'classes' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/classes.php' : 'classes.php'; ?>">
                        <i class="fas fa-chalkboard-teacher"></i> My Classes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/materials.php' : 'materials.php'; ?>">
                        <i class="fas fa-book"></i> Materials
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/assignments.php' : 'assignments.php'; ?>">
                        <i class="fas fa-clipboard-list"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/attendance.php' : 'attendance.php'; ?>">
                        <i class="fas fa-calendar-check"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'announcements' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/announcements.php' : 'announcements.php'; ?>">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'submissions' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/view_submissions.php' : 'view_submissions.php'; ?>">
                        <i class="fas fa-file-alt"></i> Submissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'late_requests' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/late_submission_requests.php' : 'late_submission_requests.php'; ?>">
                        <i class="fas fa-clock"></i> Late Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'teacher/messages.php' : 'messages.php'; ?>">
                        <i class="fas fa-envelope"></i> Messages
                    </a>
                </li>
                
            <?php elseif ($user_role === 'student'): ?>
                <!-- Student Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/dashboard.php' : 'dashboard.php'; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'join_class' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/join_class.php' : 'join_class.php'; ?>">
                        <i class="fas fa-plus-circle"></i> Join Class
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'my_classes' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/my_classes.php' : 'my_classes.php'; ?>">
                        <i class="fas fa-chalkboard"></i> My Classes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'assignments' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/assignments.php' : 'assignments.php'; ?>">
                        <i class="fas fa-clipboard-list"></i> Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'materials' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/materials.php' : 'materials.php'; ?>">
                        <i class="fas fa-book"></i> Materials
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'attendance' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/attendance.php' : 'attendance.php'; ?>">
                        <i class="fas fa-calendar-check"></i> My Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'announcements' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/announcements.php' : 'announcements.php'; ?>">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'grades' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/grades.php' : 'grades.php'; ?>">
                        <i class="fas fa-chart-line"></i> My Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'messages' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'student/messages.php' : 'messages.php'; ?>">
                        <i class="fas fa-envelope"></i> Messages
                    </a>
                </li>
                
            <?php elseif ($user_role === 'admin'): ?>
                <!-- Admin Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/dashboard.php' : 'dashboard.php'; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/users.php' : 'users.php'; ?>">
                        <i class="fas fa-users"></i> User Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'classes' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/classes.php' : 'classes.php'; ?>">
                        <i class="fas fa-chalkboard-teacher"></i> Class Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/reports.php' : 'reports.php'; ?>">
                        <i class="fas fa-chart-bar"></i> Reports & Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'system' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/system.php' : 'system.php'; ?>">
                        <i class="fas fa-cogs"></i> System Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'logs' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/logs.php' : 'logs.php'; ?>">
                        <i class="fas fa-list-alt"></i> Activity Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'content_moderation' ? 'active' : '' ?>" href="<?php echo $is_root_page ? 'admin/content_moderation.php' : 'content_moderation.php'; ?>">
                        <i class="fas fa-shield-alt"></i> Content Moderation
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Quick Actions Section -->
        <div class="sidebar-section">
            <h6 class="sidebar-heading">Quick Actions</h6>
            <ul class="nav-list">
                <?php if ($user_role === 'teacher'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'teacher/classes.php?action=create' : 'classes.php?action=create'; ?>">
                            <i class="fas fa-plus"></i> Create Class
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'teacher/assignments.php?action=create' : 'assignments.php?action=create'; ?>">
                            <i class="fas fa-plus"></i> Create Assignment
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'teacher/announcements.php?action=create' : 'announcements.php?action=create'; ?>">
                            <i class="fas fa-plus"></i> Post Announcement
                        </a>
                    </li>
                <?php elseif ($user_role === 'student'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'student/join_class.php' : 'join_class.php'; ?>">
                            <i class="fas fa-qrcode"></i> Scan QR Code
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'student/assignments.php?filter=pending' : 'assignments.php?filter=pending'; ?>">
                            <i class="fas fa-clock"></i> Pending Assignments
                        </a>
                    </li>
                <?php elseif ($user_role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'admin/users.php?filter=pending' : 'users.php?filter=pending'; ?>">
                            <i class="fas fa-user-clock"></i> Pending Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $is_root_page ? 'admin/reports.php?type=overview' : 'reports.php?type=overview'; ?>">
                            <i class="fas fa-chart-pie"></i> System Overview
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Account Section -->
        <div class="sidebar-section">
            <h6 class="sidebar-heading">Account</h6>
            <ul class="nav-list">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>" href="<?= $base_path ?>profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>" href="<?= $base_path ?>settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
