<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? null;

// Get grades
$where_conditions = ["sub.student_id = ?"];
$params = [$user_id];

if ($class_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $class_id;
}

$where_clause = implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT sub.*, ass.title as assignment_title, ass.max_points, c.name as class_name, u.name as teacher_name
    FROM submissions sub
    JOIN assignments ass ON sub.assignment_id = ass.id
    JOIN classes c ON ass.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE $where_clause AND sub.grade IS NOT NULL
    ORDER BY sub.submitted_at DESC
");
$stmt->execute($params);
$grades = $stmt->fetchAll();

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

// Calculate overall statistics
$total_assignments = count($grades);
$total_points = 0;
$earned_points = 0;

foreach ($grades as $grade) {
    $total_points += $grade['max_points'];
    $earned_points += $grade['grade'];
}

$overall_percentage = $total_points > 0 ? round(($earned_points / $total_points) * 100, 1) : 0;

$page_title = "My Grades";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-chart-line"></i> My Grades</h1>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Class Filter -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <select class="form-select" onchange="window.location.href='?class_id=' + this.value">
                        <option value="">All Classes</option>
                        <?php foreach ($enrolled_classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['section']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Overall Statistics -->
            <?php if ($total_assignments > 0): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center shadow">
                            <div class="card-body">
                                <h3 class="text-primary"><?= $total_assignments ?></h3>
                                <p class="card-text">Total Assignments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center shadow">
                            <div class="card-body">
                                <h3 class="text-success"><?= $earned_points ?></h3>
                                <p class="card-text">Points Earned</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center shadow">
                            <div class="card-body">
                                <h3 class="text-info"><?= $total_points ?></h3>
                                <p class="card-text">Total Points</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center shadow">
                            <div class="card-body">
                                <h3 class="text-warning"><?= $overall_percentage ?>%</h3>
                                <p class="card-text">Overall Grade</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Grades List -->
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Grade Details</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($grades)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No graded assignments found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Assignment</th>
                                        <th>Class</th>
                                        <th>Submitted</th>
                                        <th>Grade</th>
                                        <th>Percentage</th>
                                        <th>Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grades as $grade): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($grade['assignment_title']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($grade['class_name']) ?></td>
                                            <td><?= formatDate($grade['submitted_at']) ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= $grade['grade'] ?>/<?= $grade['max_points'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $percentage = round(($grade['grade'] / $grade['max_points']) * 100, 1);
                                                $percentage_class = $percentage >= 90 ? 'text-success' : 
                                                                   ($percentage >= 80 ? 'text-primary' : 
                                                                   ($percentage >= 70 ? 'text-warning' : 'text-danger'));
                                                ?>
                                                <span class="<?= $percentage_class ?> fw-bold">
                                                    <?= $percentage ?>%
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($grade['teacher_name']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
