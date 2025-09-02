<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';
$class_id = $_GET['class_id'] ?? null;

// Build query based on filters
$where_conditions = ["ce.student_id = ?"];
$params = [$user_id];

if ($class_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $class_id;
}

if ($filter === 'pending') {
    $where_conditions[] = "ass.due_date > NOW()";
} elseif ($filter === 'overdue') {
    $where_conditions[] = "ass.due_date < NOW()";
} elseif ($filter === 'submitted') {
    $where_conditions[] = "sub.id IS NOT NULL";
} elseif ($filter === 'not_submitted') {
    $where_conditions[] = "sub.id IS NULL";
}

$where_clause = implode(" AND ", $where_conditions);

// Get assignments
$stmt = $pdo->prepare("
    SELECT ass.*, c.name as class_name, c.class_code, u.name as teacher_name,
           sub.id as submission_id, sub.submitted_at, sub.grade, sub.status as submission_status,
           (SELECT COUNT(*) FROM assignment_files WHERE assignment_id = ass.id) as attachment_count
    FROM assignments ass
    JOIN classes c ON ass.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN submissions sub ON ass.id = sub.assignment_id AND sub.student_id = ce.student_id
    WHERE $where_clause
    ORDER BY ass.due_date ASC
");
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Get enrolled classes for filter
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.section
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND c.status = 'active'
    ORDER BY c.name
");
$stmt->execute([$user_id]);
$enrolled_classes = $stmt->fetchAll();

$page_title = "Assignments";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-clipboard-list"></i> Assignments</h1>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-8">
                    <div class="btn-group" role="group">
                        <a href="?filter=all<?= $class_id ? '&class_id=' . $class_id : '' ?>" 
                           class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">
                            All
                        </a>
                        <a href="?filter=pending<?= $class_id ? '&class_id=' . $class_id : '' ?>" 
                           class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">
                            Pending
                        </a>
                        <a href="?filter=overdue<?= $class_id ? '&class_id=' . $class_id : '' ?>" 
                           class="btn btn-outline-danger <?= $filter === 'overdue' ? 'active' : '' ?>">
                            Overdue
                        </a>
                        <a href="?filter=submitted<?= $class_id ? '&class_id=' . $class_id : '' ?>" 
                           class="btn btn-outline-success <?= $filter === 'submitted' ? 'active' : '' ?>">
                            Submitted
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" onchange="window.location.href='?filter=<?= $filter ?>&class_id=' + this.value">
                        <option value="">All Classes</option>
                        <?php foreach ($enrolled_classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['section']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Assignments List -->
            <div class="row">
                <?php if (empty($assignments)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No assignments found matching your criteria.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card shadow h-100">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <?= htmlspecialchars($assignment['title']) ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-chalkboard"></i> <?= htmlspecialchars($assignment['class_name']) ?>
                                        </small>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($assignment['teacher_name']) ?>
                                        </small>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> Due: <?= formatDateTime($assignment['due_date']) ?>
                                        </small>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-star"></i> Max Points: <?= $assignment['max_points'] ?>
                                        </small>
                                    </div>
                                    <?php if ($assignment['attachment_count'] > 0): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-paperclip"></i> <?= $assignment['attachment_count'] ?> attachment(s)
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status and Grade -->
                                    <?php if ($assignment['submission_id']): ?>
                                        <div class="alert alert-success mb-2">
                                            <i class="fas fa-check-circle"></i> 
                                            Submitted on <?= formatDate($assignment['submitted_at']) ?>
                                            <?php if ($assignment['grade'] !== null): ?>
                                                <br><strong>Grade: <?= $assignment['grade'] ?>/<?= $assignment['max_points'] ?></strong>
                                            <?php endif; ?>
                                            <?php if ($assignment['allow_resubmission']): ?>
                                                <br><small class="text-info"><i class="fas fa-redo"></i> Resubmission allowed</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (strtotime($assignment['due_date']) < time()): ?>
                                        <div class="alert alert-danger mb-2">
                                            <i class="fas fa-exclamation-triangle"></i> Overdue
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-2">
                                            <i class="fas fa-clock"></i> Pending
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex gap-2">
                                        <a href="assignment_details.php?id=<?= $assignment['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($assignment['submission_id']): ?>
                                            <a href="assignment_details.php?id=<?= $assignment['id'] ?>&view=submission" class="btn btn-outline-primary btn-sm flex-fill">
                                                <i class="fas fa-file-alt"></i> View Submission
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
