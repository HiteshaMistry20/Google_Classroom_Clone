<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? null;
$month = $_GET['month'] ?? date('Y-m');

// Get enrolled classes
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.section
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND c.status = 'active'
    ORDER BY c.name
");
$stmt->execute([$user_id]);
$enrolled_classes = $stmt->fetchAll();

// Get attendance data
$where_conditions = ["a.student_id = ?"];
$params = [$user_id];

if ($class_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $class_id;
}

if ($month) {
    $where_conditions[] = "DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
    $params[] = $month;
}

$where_clause = implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name, u.name as marked_by_name
    FROM attendance a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON a.marked_by = u.id
    WHERE $where_clause
    ORDER BY a.attendance_date DESC, c.name
");
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// Get attendance summary
$stmt = $pdo->prepare("
    SELECT c.name as class_name,
           COUNT(*) as total_days,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days
    FROM attendance a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?
    GROUP BY c.id, c.name
");
$stmt->execute([$user_id, $month]);
$attendance_summary = $stmt->fetchAll();

$page_title = "My Attendance";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-calendar-check"></i> My Attendance</h1>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <select class="form-select" onchange="window.location.href='?class_id=' + this.value + '&month=<?= $month ?>'">
                        <option value="">All Classes</option>
                        <?php foreach ($enrolled_classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['section']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="month" class="form-control" value="<?= $month ?>" 
                           onchange="window.location.href='?class_id=<?= $class_id ?>&month=' + this.value">
                </div>
            </div>

            <!-- Attendance Summary -->
            <?php if (!empty($attendance_summary)): ?>
                <div class="row mb-4">
                    <?php foreach ($attendance_summary as $summary): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card shadow">
                                <div class="card-header">
                                    <h6 class="card-title mb-0"><?= htmlspecialchars($summary['class_name']) ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-success">
                                                <div class="h4 mb-0"><?= $summary['present_days'] ?></div>
                                                <small>Present</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-warning">
                                                <div class="h4 mb-0"><?= $summary['late_days'] ?></div>
                                                <small>Late</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-danger">
                                                <div class="h4 mb-0"><?= $summary['absent_days'] ?></div>
                                                <small>Absent</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="progress">
                                            <?php 
                                            $attendance_rate = $summary['total_days'] > 0 ? 
                                                (($summary['present_days'] + $summary['late_days']) / $summary['total_days']) * 100 : 0;
                                            ?>
                                            <div class="progress-bar bg-success" style="width: <?= ($summary['present_days'] / $summary['total_days']) * 100 ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= ($summary['late_days'] / $summary['total_days']) * 100 ?>%"></div>
                                        </div>
                                        <small class="text-muted">Attendance Rate: <?= round($attendance_rate, 1) ?>%</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Attendance Records -->
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Attendance Records</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($attendance_records)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No attendance records found for the selected criteria.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                        <th>Marked By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                        <tr>
                                            <td><?= formatDate($record['attendance_date']) ?></td>
                                            <td><?= htmlspecialchars($record['class_name']) ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                $status_icon = '';
                                                switch ($record['status']) {
                                                    case 'present':
                                                        $status_class = 'badge-success';
                                                        $status_icon = 'fas fa-check-circle';
                                                        break;
                                                    case 'absent':
                                                        $status_class = 'badge-danger';
                                                        $status_icon = 'fas fa-times-circle';
                                                        break;
                                                    case 'late':
                                                        $status_class = 'badge-warning';
                                                        $status_icon = 'fas fa-clock';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?= $status_class ?>">
                                                    <i class="<?= $status_icon ?>"></i> <?= ucfirst($record['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($record['marked_by_name']) ?></td>
                                            <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
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
