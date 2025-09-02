<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$class_id = $_GET['class_id'] ?? null;

// Get announcements
$where_conditions = ["ce.student_id = ?"];
$params = [$user_id];

if ($class_id) {
    $where_conditions[] = "c.id = ?";
    $params[] = $class_id;
}

$where_clause = implode(" AND ", $where_conditions);

$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name, u.name as author_name,
           (SELECT COUNT(*) FROM comments WHERE announcement_id = a.id) as comment_count
    FROM announcements a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON a.author_id = u.id
    WHERE $where_clause
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$announcements = $stmt->fetchAll();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Google Classroom Clone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="authenticated">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bullhorn"></i> Announcements</h1>
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

                <?php if (empty($announcements)): ?>
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> No Announcements</h5>
                        <p>No announcements have been posted in your classes yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <?= htmlspecialchars($announcement['title']) ?>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text">
                                            <?= htmlspecialchars(truncateText($announcement['content'], 150)) ?>
                                        </p>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-chalkboard"></i> <?= htmlspecialchars($announcement['class_name']) ?><br>
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($announcement['author_name']) ?><br>
                                                <i class="fas fa-clock"></i> <?= formatDate($announcement['created_at']) ?><br>
                                                <i class="fas fa-comments"></i> <?= $announcement['comment_count'] ?> comments
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="announcement_details.php?id=<?= $announcement['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>
