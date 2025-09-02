<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
requireLogin();
requireRole('admin');

$report_type = isset($_GET['type']) ? $_GET['type'] : 'overview';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get system statistics
$stats = [];

// User statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$stats['new_users'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'teacher' AND created_at BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$stats['new_teachers'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND created_at BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$stats['new_students'] = $stmt->fetchColumn();

// Class statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM classes WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$stats['new_classes'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM classes WHERE status = 'active'");
$stmt->execute();
$stats['active_classes'] = $stmt->fetchColumn();

// Assignment statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM assignments WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$stats['new_assignments'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM submissions WHERE submitted_at BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to . ' 23:59:59']);
$stats['new_submissions'] = $stmt->fetchColumn();

// Attendance statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance WHERE attendance_date BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$stats['attendance_records'] = $stmt->fetchColumn();

// Get detailed data based on report type
$detailed_data = [];

switch ($report_type) {
    case 'user_activity':
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                u.status,
                u.created_at,
                (SELECT COUNT(*) FROM classes c WHERE c.teacher_id = u.id) as classes_created,
                (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.student_id = u.id) as classes_enrolled,
                (SELECT COUNT(*) FROM submissions s WHERE s.student_id = u.id) as assignments_submitted
            FROM users u
            WHERE u.created_at BETWEEN ? AND ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$date_from, $date_to . ' 23:59:59']);
        $detailed_data = $stmt->fetchAll();
        break;
        
    case 'class_performance':
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.subject,
                c.status,
                c.created_at,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.id) as student_count,
                (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) as assignment_count,
                (SELECT COUNT(*) FROM materials m WHERE m.class_id = c.id) as material_count,
                (SELECT COUNT(*) FROM announcements ann WHERE ann.class_id = c.id) as announcement_count
            FROM classes c
            JOIN users u ON c.teacher_id = u.id
            WHERE c.created_at BETWEEN ? AND ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$date_from, $date_to . ' 23:59:59']);
        $detailed_data = $stmt->fetchAll();
        break;
        
    case 'assignment_analytics':
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.title,
                a.description,
                a.due_date,
                a.created_at,
                c.name as class_name,
                u.name as teacher_name,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'submitted') as submitted_count,
                (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = 'graded') as graded_count
            FROM assignments a
            JOIN classes c ON a.class_id = c.id
            JOIN users u ON c.teacher_id = u.id
            WHERE a.created_at BETWEEN ? AND ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$date_from, $date_to . ' 23:59:59']);
        $detailed_data = $stmt->fetchAll();
        break;
}

$page_title = "Reports & Analytics";
include '../includes/header.php';
?>

<div class="container-fluid admin-dashboard">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <!-- Enhanced Welcome Section -->
            <div class="admin-welcome-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1>Reports & Analytics 📊</h1>
                        <p>Comprehensive insights into your learning management system</p>
                    </div>
                    <div class="d-none d-md-block">
                        <button type="button" class="btn btn-light me-2" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i>Export Report
                        </button>
                        <button type="button" class="btn btn-light" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Report Filters -->
            <div class="admin-table-container mb-4">
                <div class="admin-table-header">
                    <h5><i class="fas fa-filter me-2"></i>Report Filters</h5>
                </div>
                <div class="p-3">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select class="admin-filter-dropdown" name="type">
                                <option value="overview" <?= $report_type === 'overview' ? 'selected' : '' ?>>Overview</option>
                                <option value="user_activity" <?= $report_type === 'user_activity' ? 'selected' : '' ?>>User Activity</option>
                                <option value="class_performance" <?= $report_type === 'class_performance' ? 'selected' : '' ?>>Class Performance</option>
                                <option value="assignment_analytics" <?= $report_type === 'assignment_analytics' ? 'selected' : '' ?>>Assignment Analytics</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="admin-search-box" name="date_from" value="<?= $date_from ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="admin-search-box" name="date_to" value="<?= $date_to ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="admin-action-btn primary">
                                <i class="fas fa-search me-2"></i>Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Enhanced Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card primary">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon primary me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['new_users'] ?></div>
                                <div class="admin-stat-label">New Users</div>
                                <div class="admin-stat-subtitle">This period</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card success">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon success me-3">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['new_classes'] ?></div>
                                <div class="admin-stat-label">New Classes</div>
                                <div class="admin-stat-subtitle">This period</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card info">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon info me-3">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['new_assignments'] ?></div>
                                <div class="admin-stat-label">New Assignments</div>
                                <div class="admin-stat-subtitle">This period</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="admin-stat-card warning">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon warning me-3">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['new_submissions'] ?></div>
                                <div class="admin-stat-label">Submissions</div>
                                <div class="admin-stat-subtitle">This period</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Detailed Statistics -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="admin-table-container">
                        <div class="admin-table-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>User Breakdown</h5>
                        </div>
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>New Teachers</span>
                                <span class="fw-bold text-primary"><?= $stats['new_teachers'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>New Students</span>
                                <span class="fw-bold text-success"><?= $stats['new_students'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Active Classes</span>
                                <span class="fw-bold text-info"><?= $stats['active_classes'] ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Attendance Records</span>
                                <span class="fw-bold text-warning"><?= $stats['attendance_records'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="admin-table-container">
                        <div class="admin-table-header">
                            <h5><i class="fas fa-heartbeat me-2"></i>System Health</h5>
                        </div>
                        <div class="p-3">
                            <?php 
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
                            $stmt->execute();
                            $total_users = $stmt->fetchColumn();
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes");
                            $stmt->execute();
                            $total_classes = $stmt->fetchColumn();
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments");
                            $stmt->execute();
                            $total_assignments = $stmt->fetchColumn();
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Total Users</span>
                                <span class="fw-bold text-primary"><?= $total_users ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Total Classes</span>
                                <span class="fw-bold text-success"><?= $total_classes ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Total Assignments</span>
                                <span class="fw-bold text-info"><?= $total_assignments ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>System Status</span>
                                <span class="badge bg-success">Healthy</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Detailed Data Table -->
            <?php if (!empty($detailed_data)): ?>
                <div class="admin-table-container">
                    <div class="admin-table-header">
                        <h5>
                            <i class="fas fa-table me-2"></i>
                            <?php 
                            switch ($report_type) {
                                case 'user_activity': echo 'User Activity Report'; break;
                                case 'class_performance': echo 'Class Performance Report'; break;
                                case 'assignment_analytics': echo 'Assignment Analytics Report'; break;
                                default: echo 'Detailed Report';
                            }
                            ?>
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table admin-table">
                            <thead>
                                <tr>
                                    <?php if ($report_type === 'user_activity'): ?>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Activity</th>
                                        <th>Joined</th>
                                    <?php elseif ($report_type === 'class_performance'): ?>
                                        <th>Class</th>
                                        <th>Teacher</th>
                                        <th>Students</th>
                                        <th>Content</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    <?php elseif ($report_type === 'assignment_analytics'): ?>
                                        <th>Assignment</th>
                                        <th>Class</th>
                                        <th>Teacher</th>
                                        <th>Submissions</th>
                                        <th>Due Date</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_data as $item): ?>
                                    <tr>
                                        <?php if ($report_type === 'user_activity'): ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?= strtoupper(substr($item['name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                                        <div class="text-muted small"><?= htmlspecialchars($item['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="user-role-badge <?= $item['role'] ?>"><?= ucfirst($item['role']) ?></span>
                                            </td>
                                            <td>
                                                <span class="user-status-badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($item['role'] === 'teacher'): ?>
                                                    <span class="text-muted"><?= $item['classes_created'] ?> classes created</span>
                                                <?php else: ?>
                                                    <span class="text-muted"><?= $item['classes_enrolled'] ?> classes enrolled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= formatDate($item['created_at']) ?></span>
                                            </td>
                                        <?php elseif ($report_type === 'class_performance'): ?>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3" style="background: linear-gradient(135deg, #34a853, #4caf50);">
                                                        <i class="fas fa-chalkboard"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                                        <div class="text-muted small"><?= htmlspecialchars($item['subject']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= htmlspecialchars($item['teacher_name']) ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?= $item['student_count'] ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-primary"><?= $item['assignment_count'] ?> assignments</span>
                                                    <span class="badge bg-success"><?= $item['material_count'] ?> materials</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="user-status-badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= formatDate($item['created_at']) ?></span>
                                            </td>
                                        <?php elseif ($report_type === 'assignment_analytics'): ?>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($item['title']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars(substr($item['description'], 0, 50)) ?>...</div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= htmlspecialchars($item['class_name']) ?></span>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= htmlspecialchars($item['teacher_name']) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-info"><?= $item['submitted_count'] ?>/<?= $item['submission_count'] ?> submitted</span>
                                                    <span class="badge bg-success"><?= $item['graded_count'] ?> graded</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= formatDateTime($item['due_date']) ?></span>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
function exportReport() {
    const reportType = document.querySelector('select[name="type"]').value;
    const dateFrom = document.querySelector('input[name="date_from"]').value;
    const dateTo = document.querySelector('input[name="date_to"]').value;
    
    const params = new URLSearchParams({
        type: reportType,
        date_from: dateFrom,
        date_to: dateTo,
        export: '1'
    });
    
    window.open(`export_report.php?${params.toString()}`, '_blank');
}
</script>
