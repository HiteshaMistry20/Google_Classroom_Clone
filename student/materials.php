<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? null;

// Get materials
$where_conditions = ["ce.student_id = ?"];
$params = [$user_id];

if ($class_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $class_id;
}

$where_clause = implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT m.*, c.name as class_name, u.name as teacher_name
    FROM materials m
    JOIN classes c ON m.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE $where_clause
    ORDER BY m.created_at DESC
");
$stmt->execute($params);
$materials = $stmt->fetchAll();

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

$page_title = "Materials";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-book"></i> Materials</h1>
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

            <?php if (empty($materials)): ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> No Materials Found</h5>
                    <p>No materials have been shared in your classes yet.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($materials as $material): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <?= htmlspecialchars($material['title']) ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        <?= htmlspecialchars(truncateText($material['description'], 100)) ?>
                                    </p>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-chalkboard"></i> <?= htmlspecialchars($material['class_name']) ?>
                                        </small>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($material['teacher_name']) ?>
                                        </small>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?= formatDate($material['created_at']) ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($material['file_path']): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-paperclip"></i> File attached
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="d-grid gap-2">
                                        <a href="material_details.php?id=<?= $material['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Material
                                        </a>
                                        <?php if ($material['file_path']): ?>
                                            <a href="../uploads/materials/<?= $material['file_path'] ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
