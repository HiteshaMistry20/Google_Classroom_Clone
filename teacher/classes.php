<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

requireRole('teacher');

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_class'])) {
        $name = sanitizeInput($_POST['name']);
        $section = sanitizeInput($_POST['section']);
        $subject = sanitizeInput($_POST['subject']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($name) || empty($section) || empty($subject)) {
            setFlashMessage('error', 'Please fill in all required fields.');
        } else {
            $class_code = generateUniqueClassCode($pdo);
            $qr_code = generateQRCode($class_code);
            
            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, name, section, subject, description, class_code, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$user_id, $name, $section, $subject, $description, $class_code, $qr_code])) {
                $class_id = $pdo->lastInsertId();
                
                // Send notification for class creation
                notifyClassCreated($pdo, $class_id);
                
                setFlashMessage('success', 'Class created successfully!');
                header('Location: classes.php');
                exit();
            } else {
                setFlashMessage('error', 'Failed to create class. Please try again.');
            }
        }
    } elseif (isset($_POST['update_class'])) {
        $class_id = $_POST['class_id'];
        $name = sanitizeInput($_POST['name']);
        $section = sanitizeInput($_POST['section']);
        $subject = sanitizeInput($_POST['subject']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($name) || empty($section) || empty($subject)) {
            setFlashMessage('error', 'Please fill in all required fields.');
        } else {
            $stmt = $pdo->prepare("UPDATE classes SET name = ?, section = ?, subject = ?, description = ? WHERE id = ? AND teacher_id = ?");
            
            if ($stmt->execute([$name, $section, $subject, $description, $class_id, $user_id])) {
                // Send notification for class update
                notifyClassUpdate($pdo, $class_id, 'class_updated', "Class details have been updated");
                
                setFlashMessage('success', 'Class updated successfully!');
                header('Location: classes.php');
                exit();
            } else {
                setFlashMessage('error', 'Failed to update class. Please try again.');
            }
        }
    } elseif (isset($_POST['archive_class'])) {
        $class_id = $_POST['class_id'];
        
        $stmt = $pdo->prepare("UPDATE classes SET status = 'archived' WHERE id = ? AND teacher_id = ?");
        
        if ($stmt->execute([$class_id, $user_id])) {
            // Send notification for class archived
            notifyClassUpdate($pdo, $class_id, 'class_archived', "Class has been archived");
            
            setFlashMessage('success', 'Class archived successfully!');
        } else {
            setFlashMessage('error', 'Failed to archive class. Please try again.');
        }
    } elseif (isset($_POST['unarchive_class'])) {
        $class_id = $_POST['class_id'];
        
        $stmt = $pdo->prepare("UPDATE classes SET status = 'active' WHERE id = ? AND teacher_id = ?");
        
        if ($stmt->execute([$class_id, $user_id])) {
            // Send notification for class unarchived
            notifyClassUpdate($pdo, $class_id, 'class_unarchived', "Class has been reactivated");
            
            setFlashMessage('success', 'Class unarchived successfully!');
        } else {
            setFlashMessage('error', 'Failed to unarchive class. Please try again.');
        }
    } elseif (isset($_POST['delete_class'])) {
        $class_id = $_POST['class_id'];
        
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ? AND teacher_id = ?");
        
        if ($stmt->execute([$class_id, $user_id])) {
            setFlashMessage('success', 'Class deleted successfully!');
        } else {
            setFlashMessage('error', 'Failed to delete class. Please try again.');
        }
    }
}

// Get classes for listing
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'active';

$where_conditions = ["c.teacher_id = ?"];
$params = [$user_id];

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.subject LIKE ? OR c.section LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(ce.student_id) as student_count,
           COUNT(a.id) as assignment_count,
           COUNT(m.id) as material_count
    FROM classes c 
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id 
    LEFT JOIN assignments a ON c.id = a.class_id AND a.status = 'active'
    LEFT JOIN materials m ON c.id = m.class_id
    WHERE $where_clause
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$classes = $stmt->fetchAll();

// Get specific class for editing
$edit_class = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$_GET['id'], $user_id]);
    $edit_class = $stmt->fetch();
    
    if (!$edit_class) {
        setFlashMessage('error', 'Class not found.');
        header('Location: classes.php');
        exit();
    }
}
?>

<?php $page_title = "My Classes"; ?>
<?php include '../includes/header.php'; ?>
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main>
            <div class="content-area">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Classes</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="?action=create" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Create New Class
                        </a>
                    </div>
                </div>

                <?php displayFlashMessage(); ?>

                <?php if ($action === 'list'): ?>
                    <!-- Search and Filter -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" class="d-flex">
                                <input type="text" name="search" class="form-control me-2" placeholder="Search classes..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-outline-primary">Search</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" class="d-flex justify-content-end">
                                <select name="status" class="form-select me-2" style="width: auto;">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                                <button type="submit" class="btn btn-outline-secondary">Filter</button>
                            </form>
                        </div>
                    </div>

                    <!-- Classes Grid -->
                    <div class="row">
                        <?php if (empty($classes)): ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
                                    <h4 class="text-muted">No classes found</h4>
                                    <p class="text-muted">Create your first class to get started.</p>
                                    <a href="?action=create" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Create Class
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card class-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($class['name']); ?></h5>
                                                <div class="dropdown">
                                                    <button class="btn btn-link btn-sm" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="class_details.php?id=<?php echo $class['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>View Details
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="?action=edit&id=<?php echo $class['id']; ?>">
                                                            <i class="fas fa-edit me-2"></i>Edit
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#qrModal" data-class-code="<?php echo $class['class_code']; ?>">
                                                            <i class="fas fa-qrcode me-2"></i>Show QR Code
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <?php if ($class['status'] === 'active'): ?>
                                                            <li><a class="dropdown-item text-warning" href="#" data-bs-toggle="modal" data-bs-target="#archiveModal" data-class-id="<?php echo $class['id']; ?>" data-class-name="<?php echo htmlspecialchars($class['name']); ?>">
                                                                <i class="fas fa-archive me-2"></i>Archive
                                                            </a></li>
                                                        <?php else: ?>
                                                            <li><a class="dropdown-item text-success" href="#" data-bs-toggle="modal" data-bs-target="#unarchiveModal" data-class-id="<?php echo $class['id']; ?>" data-class-name="<?php echo htmlspecialchars($class['name']); ?>">
                                                                <i class="fas fa-unarchive me-2"></i>Unarchive
                                                            </a></li>
                                                        <?php endif; ?>
                                                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal" data-class-id="<?php echo $class['id']; ?>" data-class-name="<?php echo htmlspecialchars($class['name']); ?>">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars($class['subject']); ?> • Section <?php echo htmlspecialchars($class['section']); ?></p>
                                            
                                            <?php if ($class['description']): ?>
                                                <p class="card-text"><?php echo htmlspecialchars(truncateText($class['description'], 100)); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <div class="fw-bold text-primary"><?php echo $class['student_count']; ?></div>
                                                    <small class="text-muted">Students</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold text-success"><?php echo $class['assignment_count']; ?></div>
                                                    <small class="text-muted">Assignments</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold text-info"><?php echo $class['material_count']; ?></div>
                                                    <small class="text-muted">Materials</small>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="class-code"><?php echo $class['class_code']; ?></span>
                                                <?php echo getStatusBadge($class['status']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="card-footer bg-transparent">
                                            <div class="d-flex justify-content-between">
                                                <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View Class
                                                </a>
                                                <button class="btn btn-outline-secondary btn-sm copy-btn" data-copy="<?php echo $class['class_code']; ?>" data-bs-toggle="tooltip" title="Copy class code">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                    <!-- Create/Edit Class Form -->
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-<?php echo $action === 'create' ? 'plus' : 'edit'; ?> me-2"></i>
                                        <?php echo $action === 'create' ? 'Create New Class' : 'Edit Class'; ?>
                                    </h5>
                                    <small class="text-muted">
                                        <?php if ($action === 'create'): ?>
                                            Fill in the details below to create a new class. A unique class code will be automatically generated for students to join.
                                        <?php else: ?>
                                            Update the class information below. The class code will remain the same.
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="needs-validation" novalidate>
                                        <?php if ($edit_class): ?>
                                            <input type="hidden" name="class_id" value="<?php echo $edit_class['id']; ?>">
                                        <?php endif; ?>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="name" class="form-label">
                                                    <i class="fas fa-chalkboard me-1"></i>Class Name *
                                                </label>
                                                <input type="text" class="form-control" id="name" name="name" required 
                                                       placeholder="e.g., Mathematics 101, English Literature"
                                                       value="<?php echo $edit_class ? htmlspecialchars($edit_class['name']) : ''; ?>">
                                                <div class="invalid-feedback">Please provide a class name.</div>
                                                <div class="form-text">Enter a descriptive name for your class</div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="section" class="form-label">
                                                    <i class="fas fa-layer-group me-1"></i>Section *
                                                </label>
                                                <input type="text" class="form-control" id="section" name="section" required 
                                                       placeholder="e.g., A, B, 1, 2"
                                                       value="<?php echo $edit_class ? htmlspecialchars($edit_class['section']) : ''; ?>">
                                                <div class="invalid-feedback">Please provide a section.</div>
                                                <div class="form-text">Enter the section identifier</div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="subject" class="form-label">
                                                <i class="fas fa-book me-1"></i>Subject *
                                            </label>
                                            <input type="text" class="form-control" id="subject" name="subject" required 
                                                   placeholder="e.g., Mathematics, English, Science"
                                                   value="<?php echo $edit_class ? htmlspecialchars($edit_class['subject']) : ''; ?>">
                                            <div class="invalid-feedback">Please provide a subject.</div>
                                            <div class="form-text">Enter the main subject area</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="description" class="form-label">
                                                <i class="fas fa-align-left me-1"></i>Description
                                            </label>
                                            <textarea class="form-control" id="description" name="description" rows="4" 
                                                      placeholder="Describe what this class is about, learning objectives, or any important information for students..."><?php echo $edit_class ? htmlspecialchars($edit_class['description']) : ''; ?></textarea>
                                            <div class="form-text">Optional: Provide additional details about the class</div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <a href="classes.php" class="btn btn-secondary">Cancel</a>
                                            <button type="submit" name="<?php echo $action === 'create' ? 'create_class' : 'update_class'; ?>" class="btn btn-primary">
                                                <?php echo $action === 'create' ? 'Create Class' : 'Update Class'; ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Class QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrCodeContainer"></div>
                    <p class="mt-3 mb-0">
                        <strong>Class Code:</strong> <span id="modalClassCode"></span>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary copy-btn" data-copy="">Copy Code</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Modal -->
    <div class="modal fade" id="archiveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive the class "<span id="archiveClassName"></span>"?</p>
                    <p class="text-muted">Archived classes will be hidden from students but can be restored later.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="class_id" id="archiveClassId">
                        <button type="submit" name="archive_class" class="btn btn-warning">Archive Class</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Unarchive Modal -->
    <div class="modal fade" id="unarchiveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Unarchive Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to unarchive the class "<span id="unarchiveClassName"></span>"?</p>
                    <p class="text-muted">Unarchived classes will be visible to students again.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="class_id" id="unarchiveClassId">
                        <button type="submit" name="unarchive_class" class="btn btn-success">Unarchive Class</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the class "<span id="deleteClassName"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All class data, assignments, and submissions will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="class_id" id="deleteClassId">
                        <button type="submit" name="delete_class" class="btn btn-danger">Delete Class</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        // Modal event handlers
        document.addEventListener('DOMContentLoaded', function() {
            // QR Code Modal
            const qrModal = document.getElementById('qrModal');
            qrModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const classCode = button.getAttribute('data-class-code');
                document.getElementById('modalClassCode').textContent = classCode;
                document.querySelector('#qrModal .copy-btn').setAttribute('data-copy', classCode);
                generateQRCode(classCode);
            });
            
            // Archive Modal
            const archiveModal = document.getElementById('archiveModal');
            archiveModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const classId = button.getAttribute('data-class-id');
                const className = button.getAttribute('data-class-name');
                document.getElementById('archiveClassId').value = classId;
                document.getElementById('archiveClassName').textContent = className;
            });
            
            // Unarchive Modal
            const unarchiveModal = document.getElementById('unarchiveModal');
            unarchiveModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const classId = button.getAttribute('data-class-id');
                const className = button.getAttribute('data-class-name');
                document.getElementById('unarchiveClassId').value = classId;
                document.getElementById('unarchiveClassName').textContent = className;
            });
            
            // Delete Modal
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const classId = button.getAttribute('data-class-id');
                const className = button.getAttribute('data-class-name');
                document.getElementById('deleteClassId').value = classId;
                document.getElementById('deleteClassName').textContent = className;
            });
        });
    </script>
</body>
</html>
