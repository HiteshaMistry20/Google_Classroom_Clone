<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
requireLogin();
requireRole('admin');

$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_class'])) {
        $class_id = $_POST['class_id'];
        $name = sanitizeInput($_POST['name']);
        $section = sanitizeInput($_POST['section']);
        $subject = sanitizeInput($_POST['subject']);
        $description = sanitizeInput($_POST['description']);
        $status = sanitizeInput($_POST['status']);
        
        if (empty($name) || empty($section) || empty($subject)) {
            setFlashMessage('error', 'Please fill in all required fields.');
        } else {
            $stmt = $pdo->prepare("UPDATE classes SET name = ?, section = ?, subject = ?, description = ?, status = ? WHERE id = ?");
            
            if ($stmt->execute([$name, $section, $subject, $description, $status, $class_id])) {
                setFlashMessage('success', 'Class updated successfully!');
                header('Location: classes.php');
                exit();
            } else {
                setFlashMessage('error', 'Failed to update class. Please try again.');
            }
        }
    } elseif (isset($_POST['archive_class'])) {
        $class_id = $_POST['class_id'];
        
        $stmt = $pdo->prepare("UPDATE classes SET status = 'archived' WHERE id = ?");
        
        if ($stmt->execute([$class_id])) {
            setFlashMessage('success', 'Class archived successfully!');
        } else {
            setFlashMessage('error', 'Failed to archive class. Please try again.');
        }
    } elseif (isset($_POST['unarchive_class'])) {
        $class_id = $_POST['class_id'];
        
        $stmt = $pdo->prepare("UPDATE classes SET status = 'active' WHERE id = ?");
        
        if ($stmt->execute([$class_id])) {
            setFlashMessage('success', 'Class unarchived successfully!');
        } else {
            setFlashMessage('error', 'Failed to unarchive class. Please try again.');
        }
    } elseif (isset($_POST['delete_class'])) {
        $class_id = $_POST['class_id'];
        
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        
        if ($stmt->execute([$class_id])) {
            setFlashMessage('success', 'Class deleted successfully!');
        } else {
            setFlashMessage('error', 'Failed to delete class. Please try again.');
        }
    }
}

// Get classes for listing
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$teacher_filter = $_GET['teacher'] ?? '';

$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.subject LIKE ? OR c.section LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

if (!empty($teacher_filter)) {
    $where_conditions[] = "c.teacher_id = ?";
    $params[] = $teacher_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get all classes with teacher information
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        u.name as teacher_name,
        u.email as teacher_email,
        (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.id) as student_count,
        (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) as assignment_count,
        (SELECT COUNT(*) FROM materials m WHERE m.class_id = c.id) as material_count
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    WHERE $where_clause
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$classes = $stmt->fetchAll();

// Get all teachers for filter dropdown
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_classes,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_classes,
        COUNT(CASE WHEN status = 'archived' THEN 1 END) as archived_classes
    FROM classes
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Class Management";
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
                        <h1>Class Management 📚</h1>
                        <p>Manage all classes and monitor their activities</p>
                    </div>
                    <div class="d-none d-md-block">
                        <button type="button" class="btn btn-light me-2" onclick="exportClasses()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button type="button" class="btn btn-light" onclick="window.location.href='dashboard.php'">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="admin-stat-card primary">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon primary me-3">
                                <i class="fas fa-chalkboard"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['total_classes'] ?></div>
                                <div class="admin-stat-label">Total Classes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="admin-stat-card success">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon success me-3">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['active_classes'] ?></div>
                                <div class="admin-stat-label">Active Classes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="admin-stat-card warning">
                        <div class="d-flex align-items-center">
                            <div class="admin-stat-icon warning me-3">
                                <i class="fas fa-archive"></i>
                            </div>
                            <div>
                                <div class="admin-stat-number"><?= $stats['archived_classes'] ?></div>
                                <div class="admin-stat-label">Archived Classes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Filters -->
            <div class="admin-table-container mb-4">
                <div class="admin-table-header">
                    <h5><i class="fas fa-filter me-2"></i>Search & Filters</h5>
                </div>
                <div class="p-3">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="admin-search-box" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search classes, subjects, teachers...">
                        </div>
                        <div class="col-md-2">
                            <select class="admin-filter-dropdown" name="status">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="admin-filter-dropdown" name="teacher">
                                <option value="">All Teachers</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?= $teacher['id'] ?>" <?= $teacher_filter == $teacher['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($teacher['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="admin-action-btn primary">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="classes.php" class="admin-action-btn warning">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Enhanced Classes List -->
            <div class="admin-table-container">
                <div class="admin-table-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-chalkboard me-2"></i>Classes (<?= count($classes) ?>)</h5>
                    </div>
                </div>
                <div class="table-responsive">
                    <?php if (empty($classes)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No classes found</h5>
                            <p class="text-muted">No classes match your current search criteria.</p>
                        </div>
                    <?php else: ?>
                        <table class="table admin-table" id="classesTable">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Teacher</th>
                                    <th>Students</th>
                                    <th>Content</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3" style="background: linear-gradient(135deg, #34a853, #4caf50);">
                                                    <i class="fas fa-chalkboard"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($class['name']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($class['subject']) ?> • <?= htmlspecialchars($class['section']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2" style="width: 35px; height: 35px; font-size: 0.9rem;">
                                                    <?= strtoupper(substr($class['teacher_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold small"><?= htmlspecialchars($class['teacher_name']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($class['teacher_email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="fw-bold"><?= $class['student_count'] ?></div>
                                                <div class="text-muted small">students</div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-3">
                                                <div class="text-center">
                                                    <div class="fw-bold text-primary"><?= $class['assignment_count'] ?></div>
                                                    <div class="text-muted small">assignments</div>
                                                </div>
                                                <div class="text-center">
                                                    <div class="fw-bold text-success"><?= $class['material_count'] ?></div>
                                                    <div class="text-muted small">materials</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="user-status-badge <?= $class['status'] ?>"><?= ucfirst($class['status']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?= formatDate($class['created_at']) ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="admin-action-btn btn-sm primary" onclick="viewClass(<?= $class['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($class['status'] === 'active'): ?>
                                                    <button type="button" class="admin-action-btn btn-sm warning" onclick="archiveClass(<?= $class['id'] ?>)">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="admin-action-btn btn-sm success" onclick="unarchiveClass(<?= $class['id'] ?>)">
                                                        <i class="fas fa-unarchive"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="admin-action-btn btn-sm danger" onclick="deleteClass(<?= $class['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
function exportClasses() {
    // Implementation for exporting classes
    alert('Export functionality will be implemented here');
}

function viewClass(classId) {
    window.location.href = `class_details.php?id=${classId}`;
}

function archiveClass(classId) {
    if (confirm('Are you sure you want to archive this class?')) {
        submitClassAction('archive_class', classId);
    }
}

function unarchiveClass(classId) {
    if (confirm('Are you sure you want to unarchive this class?')) {
        submitClassAction('unarchive_class', classId);
    }
}

function deleteClass(classId) {
    if (confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
        submitClassAction('delete_class', classId);
    }
}

function submitClassAction(action, classId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="${action}" value="1">
        <input type="hidden" name="class_id" value="${classId}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
