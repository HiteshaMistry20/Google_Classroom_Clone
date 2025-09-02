<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_material'])) {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $material_type = sanitizeInput($_POST['material_type']);
        $class_id = (int)$_POST['class_id'];
        
        // Validate class ownership
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ? AND status = 'active'");
        $stmt->execute([$class_id, $teacher_id]);
        if (!$stmt->fetch()) {
            setFlashMessage('error', 'Invalid class selected.');
            header('Location: materials.php');
            exit();
        }
        
        // Handle file upload
        $file_path = null;
        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/materials/';
            $upload_result = uploadFile($_FILES['material_file'], $upload_dir);
            
            if (!$upload_result) {
                setFlashMessage('error', 'Failed to upload file. Please try again.');
                header('Location: materials.php?class_id=' . $class_id);
                exit();
            }
            
            // Extract just the file path from the upload result
            $file_path = extractFilePath($upload_result);
        }
        
        // Insert material into database
        $stmt = $pdo->prepare("INSERT INTO materials (class_id, teacher_id, title, description, type, file_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt->execute([$class_id, $teacher_id, $title, $description, $material_type, $file_path])) {
            // Send comprehensive notifications for new material
            $material_id = $pdo->lastInsertId();
            notifyNewMaterial($pdo, $material_id, $class_id);
            
            $success_message = 'Material uploaded successfully!';
            if ($file_path) {
                $success_message .= ' You can now view and download the file.';
            }
            setFlashMessage('success', $success_message);
        } else {
            setFlashMessage('error', 'Failed to save material. Please try again.');
        }
        
        header('Location: materials.php?class_id=' . $class_id);
        exit();
    }
    
    if (isset($_POST['edit_material'])) {
        $material_id = (int)$_POST['material_id'];
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $material_type = sanitizeInput($_POST['material_type']);
        $class_id = (int)$_POST['class_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT m.* FROM materials m WHERE m.id = ? AND m.teacher_id = ?");
        $stmt->execute([$material_id, $teacher_id]);
        $material = $stmt->fetch();
        
        if (!$material) {
            setFlashMessage('error', 'Material not found or access denied.');
            header('Location: materials.php?class_id=' . $class_id);
            exit();
        }
        
        // Handle new file upload if provided
        $file_path = $material['file_path']; // Keep existing file by default
        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/materials/';
            $upload_result = uploadFile($_FILES['material_file'], $upload_dir);
            
            if ($upload_result) {
                // Delete old file if exists
                if ($material['file_path'] && file_exists($material['file_path'])) {
                    unlink($material['file_path']);
                }
                $file_path = extractFilePath($upload_result);
            }
        }
        
        // Update material in database
        $stmt = $pdo->prepare("UPDATE materials SET title = ?, description = ?, type = ?, file_path = ? WHERE id = ?");
        if ($stmt->execute([$title, $description, $material_type, $file_path, $material_id])) {
            setFlashMessage('success', 'Material updated successfully!');
        } else {
            setFlashMessage('error', 'Failed to update material. Please try again.');
        }
        
        header('Location: materials.php?class_id=' . $class_id);
        exit();
    }
    
    if (isset($_POST['delete_material'])) {
        $material_id = (int)$_POST['material_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT m.* FROM materials m WHERE m.id = ? AND m.teacher_id = ?");
        $stmt->execute([$material_id, $teacher_id]);
        $material = $stmt->fetch();
        
        if ($material) {
            // Delete file if exists
            if ($material['file_path'] && file_exists('../' . $material['file_path'])) {
                unlink('../' . $material['file_path']);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
            if ($stmt->execute([$material_id])) {
                setFlashMessage('success', 'Material deleted successfully!');
            } else {
                setFlashMessage('error', 'Failed to delete material.');
            }
        } else {
            setFlashMessage('error', 'Material not found or access denied.');
        }
        
        header('Location: materials.php?class_id=' . $class_id);
        exit();
    }
}

// Get teacher's classes
$stmt = $pdo->prepare("SELECT id, name, subject FROM classes WHERE teacher_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Get materials for selected class
$materials = [];
if ($class_id > 0) {
    $stmt = $pdo->prepare("
        SELECT m.*, c.name as class_name 
        FROM materials m 
        JOIN classes c ON m.class_id = c.id 
        WHERE m.class_id = ? AND m.teacher_id = ? 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$class_id, $teacher_id]);
    $materials = $stmt->fetchAll();
}

$page_title = "Materials Management";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Materials Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadMaterialModal">
                        <i class="fas fa-upload"></i> Upload Material
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Class Selection -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <label for="classSelect" class="form-label">Select Class</label>
                    <select class="form-select" id="classSelect" onchange="window.location.href='?class_id=' + this.value">
                        <option value="">Choose a class...</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($class_id > 0): ?>
                <!-- Materials List -->
                <div class="row">
                    <?php if (empty($materials)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No materials uploaded yet for this class.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0"><?= htmlspecialchars($material['title']) ?></h6>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($material['file_path'] && file_exists('../' . $material['file_path'])): ?>
                                                        <li><a class="dropdown-item" href="../<?= $material['file_path'] ?>" target="_blank">
                                                            <i class="fas fa-download"></i> Download
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="../<?= $material['file_path'] ?>" target="_blank">
                                                            <i class="fas fa-eye"></i> View
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#" onclick="editMaterial(<?= $material['id'] ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteMaterial(<?= $material['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <p class="card-text text-muted small"><?= htmlspecialchars($material['description']) ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?= $material['type'] === 'document' ? 'primary' : ($material['type'] === 'video' ? 'danger' : 'success') ?>">
                                                <?= ucfirst($material['type']) ?>
                                            </span>
                                            <small class="text-muted"><?= formatDate($material['created_at']) ?></small>
                                        </div>
                                        
                                        <?php if ($material['file_path'] && file_exists('../' . $material['file_path'])): ?>
                                            <div class="mt-2">
                                                <i class="<?= getFileIcon(pathinfo($material['file_path'], PATHINFO_EXTENSION)) ?>"></i>
                                                <small class="text-muted"><?= basename($material['file_path']) ?></small>
                                                <div class="mt-2">
                                                    <?php 
                                                    $file_extension = strtolower(pathinfo($material['file_path'], PATHINFO_EXTENSION));
                                                    $can_preview = in_array($file_extension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov']);
                                                    ?>
                                                    <?php if ($can_preview): ?>
                                                        <a href="../<?= $material['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="../<?= $material['file_path'] ?>" class="btn btn-sm btn-outline-secondary" download>
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        <?php elseif ($material['file_path']): ?>
                                            <div class="mt-2">
                                                <div class="alert alert-warning alert-sm">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    <small>File not found: <?= basename($material['file_path']) ?></small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please select a class to view and manage materials.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Upload Material Modal -->
<div class="modal fade" id="uploadMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadMaterialForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Class *</label>
                                <select class="form-select" name="class_id" id="class_id" required>
                                    <option value="">Select a class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= $class['id'] ?>" <?= $class_id == $class['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="material_type" class="form-label">Material Type *</label>
                                <select class="form-select" name="material_type" id="material_type" required>
                                    <option value="">Select type...</option>
                                    <option value="document">Document</option>
                                    <option value="video">Video</option>
                                    <option value="link">Link</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="material_file" class="form-label">File (Optional)</label>
                        <input type="file" class="form-control" name="material_file" id="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov">
                        <div class="form-text">Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, Images, Videos (Max 50MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_material" class="btn btn-primary">Upload Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Material Modal -->
<div class="modal fade" id="editMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editMaterialForm">
                <div class="modal-body">
                    <input type="hidden" name="material_id" id="edit_material_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_class_id" class="form-label">Class *</label>
                                <select class="form-select" name="class_id" id="edit_class_id" required>
                                    <option value="">Select a class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?= $class['id'] ?>">
                                            <?= htmlspecialchars($class['name']) ?> (<?= htmlspecialchars($class['subject']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_material_type" class="form-label">Material Type *</label>
                                <select class="form-select" name="material_type" id="edit_material_type" required>
                                    <option value="">Select type...</option>
                                    <option value="document">Document</option>
                                    <option value="video">Video</option>
                                    <option value="link">Link</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" id="edit_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_material_file" class="form-label">File (Optional)</label>
                        <input type="file" class="form-control" name="material_file" id="edit_material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov">
                        <div class="form-text">Leave empty to keep the current file. Supported formats: PDF, DOC, DOCX, PPT, PPTX, TXT, Images, Videos (Max 50MB)</div>
                    </div>
                    
                    <div id="current_file_info" class="alert alert-info" style="display: none;">
                        <strong>Current File:</strong> <span id="current_file_name"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_material" class="btn btn-primary">Update Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this material? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="material_id" id="deleteMaterialId">
                    <button type="submit" name="delete_material" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteMaterial(materialId) {
    document.getElementById('deleteMaterialId').value = materialId;
    new bootstrap.Modal(document.getElementById('deleteMaterialModal')).show();
}

function editMaterial(materialId) {
    // Fetch material data via AJAX
    fetch(`../api/get_material.php?id=${materialId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const material = data.material;
                
                // Populate the edit form
                document.getElementById('edit_material_id').value = material.id;
                document.getElementById('edit_class_id').value = material.class_id;
                document.getElementById('edit_material_type').value = material.type;
                document.getElementById('edit_title').value = material.title;
                document.getElementById('edit_description').value = material.description;
                
                // Show current file info if exists
                const currentFileInfo = document.getElementById('current_file_info');
                const currentFileName = document.getElementById('current_file_name');
                
                if (material.file_path) {
                    currentFileName.textContent = material.file_path.split('/').pop();
                    currentFileInfo.style.display = 'block';
                } else {
                    currentFileInfo.style.display = 'none';
                }
                
                // Show the modal
                new bootstrap.Modal(document.getElementById('editMaterialModal')).show();
            } else {
                alert('Failed to load material data: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load material data. Please try again.');
        });
}

// Initialize file upload preview
document.getElementById('material_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        if (fileSize > 50) {
            alert('File size must be less than 50MB');
            this.value = '';
        }
    }
});

// Initialize edit file upload preview
document.getElementById('edit_material_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        if (fileSize > 50) {
            alert('File size must be less than 50MB');
            this.value = '';
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>

