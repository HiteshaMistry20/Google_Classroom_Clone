<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Google Classroom Clone</title>
    
    <!-- Get the base URL for assets -->
    <?php
    $current_path = $_SERVER['PHP_SELF'];
    $path_parts = explode('/', $current_path);
    $depth = count($path_parts) - 1;
    $base_path = str_repeat('../', $depth - 1);
    if (strpos($current_path, 'admin/') !== false || 
        strpos($current_path, 'teacher/') !== false || 
        strpos($current_path, 'student/') !== false) {
        $base_path = '../';
    } else {
        $base_path = '';
    }
    
    // Get user info for header
    $user_name = $_SESSION['user_name'] ?? 'User';
    $user_role = $_SESSION['user_role'] ?? 'user';
    $user_id = $_SESSION['user_id'] ?? 0;
    
    // Get notification counts
    $notification_count = 0;
    $message_count = 0;
    if ($user_id) {
        try {
            // Get unread notifications count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $notification_count = $stmt->fetch()['count'];
            
            // Get unread messages count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $message_count = $stmt->fetch()['count'];
        } catch (Exception $e) {
            // Silently handle errors
        }
    }
    ?>

    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo $base_path; ?>assets/css/style.css" rel="stylesheet">
    
    <style>
        .top-header {
            background: linear-gradient(135deg, #4285f4, #34a853);
            color: white;
            padding: 0.75rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        
        .header-brand:hover {
            color: rgba(255,255,255,0.9);
        }
        
        .header-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-nav .nav-link {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .header-nav .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .header-nav .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 8px;
        }
        
        .dropdown-item {
            padding: 0.75rem 1rem;
            transition: background-color 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header-nav {
                display: none;
            }
            
            .user-menu {
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body class="authenticated">
    <!-- Top Header -->
    <header class="top-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <a href="<?php echo $base_path; ?>index.php" class="header-brand">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Classroom
                    </a>
                </div>
                
                <div class="col-md-6">
                    <nav class="header-nav justify-content-center">
                        <?php if ($user_role === 'student'): ?>
                            <a href="<?php echo $base_path; ?>student/dashboard.php" class="nav-link <?php echo strpos($current_path, 'student/dashboard.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                            <a href="<?php echo $base_path; ?>student/my_classes.php" class="nav-link <?php echo strpos($current_path, 'student/my_classes.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-users me-1"></i>My Classes
                            </a>
                            <a href="<?php echo $base_path; ?>student/assignments.php" class="nav-link <?php echo strpos($current_path, 'student/assignments.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-tasks me-1"></i>Assignments
                            </a>
                            <a href="<?php echo $base_path; ?>student/grades.php" class="nav-link <?php echo strpos($current_path, 'student/grades.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-chart-line me-1"></i>Grades
                            </a>
                        <?php elseif ($user_role === 'teacher'): ?>
                            <a href="<?php echo $base_path; ?>teacher/dashboard.php" class="nav-link <?php echo strpos($current_path, 'teacher/dashboard.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                            <a href="<?php echo $base_path; ?>teacher/classes.php" class="nav-link <?php echo strpos($current_path, 'teacher/classes.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-chalkboard-teacher me-1"></i>Classes
                            </a>
                            <a href="<?php echo $base_path; ?>teacher/assignments.php" class="nav-link <?php echo strpos($current_path, 'teacher/assignments.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-tasks me-1"></i>Assignments
                            </a>
                            <a href="<?php echo $base_path; ?>teacher/attendance.php" class="nav-link <?php echo strpos($current_path, 'teacher/attendance.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-check me-1"></i>Attendance
                            </a>
                        <?php elseif ($user_role === 'admin'): ?>
                            <a href="<?php echo $base_path; ?>admin/dashboard.php" class="nav-link <?php echo strpos($current_path, 'admin/dashboard.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-home me-1"></i>Dashboard
                            </a>
                            <a href="<?php echo $base_path; ?>admin/users.php" class="nav-link <?php echo strpos($current_path, 'admin/users.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-users me-1"></i>Users
                            </a>
                            <a href="<?php echo $base_path; ?>admin/classes.php" class="nav-link <?php echo strpos($current_path, 'admin/classes.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-chalkboard me-1"></i>Classes
                            </a>
                            <a href="<?php echo $base_path; ?>admin/reports.php" class="nav-link <?php echo strpos($current_path, 'admin/reports.php') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar me-1"></i>Reports
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div class="col-md-3">
                    <div class="user-menu justify-content-end">
                        <!-- Notifications -->
                        <div class="dropdown position-relative">
                            <button class="btn btn-link text-white p-0" data-bs-toggle="dropdown">
                                <i class="fas fa-bell fs-5"></i>
                                <?php if ($notification_count > 0): ?>
                                    <span class="notification-badge"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($notification_count > 0): ?>
                                    <li><a class="dropdown-item" href="<?php echo $base_path; ?><?php echo $user_role; ?>/notifications.php">
                                        <i class="fas fa-bell text-warning"></i>
                                        <?php echo $notification_count; ?> new notifications
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?><?php echo $user_role; ?>/notifications.php">
                                    <i class="fas fa-bell"></i> View all notifications
                                </a></li>
                            </ul>
                        </div>
                        
                        <!-- Messages -->
                        <div class="dropdown position-relative">
                            <button class="btn btn-link text-white p-0" data-bs-toggle="dropdown">
                                <i class="fas fa-envelope fs-5"></i>
                                <?php if ($message_count > 0): ?>
                                    <span class="notification-badge"><?php echo $message_count; ?></span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($message_count > 0): ?>
                                    <li><a class="dropdown-item" href="<?php echo $base_path; ?><?php echo $user_role; ?>/messages.php">
                                        <i class="fas fa-envelope text-warning"></i>
                                        <?php echo $message_count; ?> new messages
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?><?php echo $user_role; ?>/messages.php">
                                    <i class="fas fa-envelope"></i> View all messages
                                </a></li>
                            </ul>
                        </div>
                        
                        <!-- User Menu -->
                        <div class="dropdown">
                            <button class="btn btn-link text-white p-0 d-flex align-items-center" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                                </div>
                                <span class="d-none d-md-inline"><?php echo htmlspecialchars($user_name); ?></span>
                                <i class="fas fa-chevron-down ms-2"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>profile.php">
                                    <i class="fas fa-user"></i> Profile
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>settings.php">
                                    <i class="fas fa-cog"></i> Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard"> 