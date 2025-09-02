<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
requireLogin();
requireRole('admin');

$content_type = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Handle content removal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_content'])) {
        $content_type = $_POST['content_type'];
        $content_id = $_POST['content_id'];
        $reason = sanitizeInput($_POST['reason']);
        
        try {
            switch ($content_type) {
                case 'announcement':
                    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                    $stmt->execute([$content_id]);
                    setFlashMessage('success', 'Announcement removed successfully!');
                    break;
                    
                case 'comment':
                    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
                    $stmt->execute([$content_id]);
                    setFlashMessage('success', 'Comment removed successfully!');
                    break;
                    
                case 'material':
                    // Get file path before deletion
                    $stmt = $pdo->prepare("SELECT file_path FROM materials WHERE id = ?");
                    $stmt->execute([$content_id]);
                    $material = $stmt->fetch();
                    
                    if ($material && file_exists('../' . $material['file_path'])) {
                        unlink('../' . $material['file_path']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
                    $stmt->execute([$content_id]);
                    setFlashMessage('success', 'Material removed successfully!');
                    break;
                    
                case 'assignment':
                    $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
                    $stmt->execute([$content_id]);
                    setFlashMessage('success', 'Assignment removed successfully!');
                    break;
            }
            
            // Log the moderation action
            $admin_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, content_type, content_id, reason, created_at) VALUES (?, 'remove', ?, ?, ?, NOW())");
            $stmt->execute([$admin_id, $content_type, $content_id, $reason]);
            
        } catch (Exception $e) {
            error_log("Content moderation error: " . $e->getMessage());
            setFlashMessage('error', 'Failed to remove content. Please try again.');
        }
        
        header('Location: content_moderation.php');
        exit();
    }
}

// Build query based on filters
$where_conditions = ["1=1"];
$params = [];

if ($content_type !== 'all') {
    $where_conditions[] = "content_type = ?";
    $params[] = $content_type;
}

if (!empty($search)) {
    $where_conditions[] = "(content LIKE ? OR user_name LIKE ? OR class_name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$where_clause = implode(' AND ', $where_conditions);

// Get content for moderation
try {
    $query = "
        SELECT 
            'announcement' as content_type,
            a.id,
            a.title as content,
            a.content as full_content,
            a.created_at,
            u.name as user_name,
            u.email as user_email,
            c.name as class_name,
            c.id as class_id,
            'announcement' as type
        FROM announcements a
        JOIN users u ON a.teacher_id = u.id
        JOIN classes c ON a.class_id = c.id
        WHERE $where_clause AND a.id IN (
            SELECT a2.id FROM announcements a2 WHERE a2.id = a.id
        )
        
        UNION ALL
        
        SELECT 
            'comment' as content_type,
            co.id,
            LEFT(co.content, 100) as content,
            co.content as full_content,
            co.created_at,
            u.name as user_name,
            u.email as user_email,
            c.name as class_name,
            c.id as class_id,
            'comment' as type
        FROM comments co
        JOIN users u ON co.user_id = u.id
        JOIN announcements a ON co.announcement_id = a.id
        JOIN classes c ON a.class_id = c.id
        WHERE $where_clause AND co.id IN (
            SELECT co2.id FROM comments co2 WHERE co2.id = co.id
        )
        
        UNION ALL
        
        SELECT 
            'material' as content_type,
            m.id,
            m.title as content,
            m.description as full_content,
            m.created_at,
            u.name as user_name,
            u.email as user_email,
            c.name as class_name,
            c.id as class_id,
            'material' as type
        FROM materials m
        JOIN users u ON m.teacher_id = u.id
        JOIN classes c ON m.class_id = c.id
        WHERE $where_clause AND m.id IN (
            SELECT m2.id FROM materials m2 WHERE m2.id = m.id
        )
        
        ORDER BY created_at DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $content_list = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Content moderation query error: " . $e->getMessage());
    $content_list = [];
}

// Get statistics
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM announcements");
    $stmt->execute();
    $announcement_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM comments");
    $stmt->execute();
    $comment_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM materials");
    $stmt->execute();
    $material_count = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $announcement_count = $comment_count = $material_count = 0;
}

$page_title = "Content Moderation";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Content Moderation</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportModerationReport()">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Announcements</h5>
                            <p class="card-text display-6"><?= $announcement_count ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Comments</h5>
                            <p class="card-text display-6"><?= $comment_count ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Materials</h5>
                            <p class="card-text display-6"><?= $material_count ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="type" class="form-label">Content Type</label>
                            <select class="form-select" name="type" id="type">
                                <option value="all" <?= $content_type === 'all' ? 'selected' : '' ?>>All Content</option>
                                <option value="announcement" <?= $content_type === 'announcement' ? 'selected' : '' ?>>Announcements</option>
                                <option value="comment" <?= $content_type === 'comment' ? 'selected' : '' ?>>Comments</option>
                                <option value="material" <?= $content_type === 'material' ? 'selected' : '' ?>>Materials</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search content...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="content_moderation.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Content List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Content for Review</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($content_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No content found matching your criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Content</th>
                                        <th>Author</th>
                                        <th>Class</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($content_list as $content): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $type_icons = [
                                                    'announcement' => 'fas fa-bullhorn text-primary',
                                                    'comment' => 'fas fa-comment text-info',
                                                    'material' => 'fas fa-file-alt text-success'
                                                ];
                                                $icon_class = $type_icons[$content['type']] ?? 'fas fa-file text-secondary';
                                                ?>
                                                <i class="<?= $icon_class ?>"></i>
                                                <span class="badge bg-secondary"><?= ucfirst($content['type']) ?></span>
                                            </td>
                                            <td>
                                                <div class="content-preview">
                                                    <strong><?= htmlspecialchars($content['content']) ?></strong>
                                                    <?php if (strlen($content['full_content']) > 100): ?>
                                                        <button class="btn btn-sm btn-link p-0 ms-2" onclick="viewFullContent(<?= $content['id'] ?>, '<?= $content['type'] ?>')">
                                                            View Full
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($content['user_name']) ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= htmlspecialchars($content['user_email']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="../teacher/class.php?id=<?= $content['class_id'] ?>" target="_blank">
                                                    <?= htmlspecialchars($content['class_name']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <small><?= date('M j, Y g:i A', strtotime($content['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-danger" onclick="removeContent(<?= $content['id'] ?>, '<?= $content['type'] ?>', '<?= htmlspecialchars($content['content']) ?>')">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="warnUser(<?= $content['id'] ?>, '<?= $content['type'] ?>')">
                                                        <i class="fas fa-exclamation-triangle"></i> Warn
                                                    </button>
                                                </div>
                                            </td>
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

<!-- Remove Content Modal -->
<div class="modal fade" id="removeContentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remove Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to remove this content?</p>
                    <div id="contentPreview" class="alert alert-warning"></div>
                    
                    <div class="mb-3">
                        <label for="removalReason" class="form-label">Reason for Removal</label>
                        <select class="form-select" name="reason" id="removalReason" required>
                            <option value="">Select a reason...</option>
                            <option value="inappropriate">Inappropriate Content</option>
                            <option value="spam">Spam</option>
                            <option value="offensive">Offensive Language</option>
                            <option value="copyright">Copyright Violation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="content_type" id="removeContentType">
                    <input type="hidden" name="content_id" id="removeContentId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="remove_content" class="btn btn-danger">Remove Content</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Full Content Modal -->
<div class="modal fade" id="viewContentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Full Content</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fullContentDisplay"></div>
            </div>
        </div>
    </div>
</div>

<script>
function removeContent(contentId, contentType, contentPreview) {
    document.getElementById('removeContentId').value = contentId;
    document.getElementById('removeContentType').value = contentType;
    document.getElementById('contentPreview').textContent = contentPreview;
    new bootstrap.Modal(document.getElementById('removeContentModal')).show();
}

function viewFullContent(contentId, contentType) {
    // Fetch full content via AJAX
    fetch(`api/content_details.php?type=${contentType}&id=${contentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const content = data.content;
                let displayHtml = '';
                
                switch (contentType) {
                    case 'announcement':
                        displayHtml = `
                            <div class="content-details">
                                <h6>Announcement Details</h6>
                                <p><strong>Title:</strong> ${content.title}</p>
                                <p><strong>Author:</strong> ${content.author_name} (${content.author_email})</p>
                                <p><strong>Class:</strong> ${content.class_name}</p>
                                <p><strong>Date:</strong> ${new Date(content.created_at).toLocaleString()}</p>
                                <hr>
                                <h6>Content:</h6>
                                <div class="content-body">${content.content}</div>
                            </div>
                        `;
                        break;
                        
                    case 'comment':
                        displayHtml = `
                            <div class="content-details">
                                <h6>Comment Details</h6>
                                <p><strong>Author:</strong> ${content.author_name} (${content.author_email})</p>
                                <p><strong>Class:</strong> ${content.class_name}</p>
                                <p><strong>Announcement:</strong> ${content.announcement_title}</p>
                                <p><strong>Date:</strong> ${new Date(content.created_at).toLocaleString()}</p>
                                <hr>
                                <h6>Comment:</h6>
                                <div class="content-body">${content.content}</div>
                            </div>
                        `;
                        break;
                        
                    case 'material':
                        displayHtml = `
                            <div class="content-details">
                                <h6>Material Details</h6>
                                <p><strong>Title:</strong> ${content.title}</p>
                                <p><strong>Author:</strong> ${content.author_name} (${content.author_email})</p>
                                <p><strong>Class:</strong> ${content.class_name}</p>
                                <p><strong>Date:</strong> ${new Date(content.created_at).toLocaleString()}</p>
                                <hr>
                                <h6>Description:</h6>
                                <div class="content-body">${content.description || 'No description provided'}</div>
                                ${content.file_path ? `<p><strong>File:</strong> <a href="../${content.file_path}" target="_blank">View File</a></p>` : ''}
                            </div>
                        `;
                        break;
                        
                    default:
                        displayHtml = '<div class="alert alert-warning">Content type not supported for preview.</div>';
                }
                
                document.getElementById('fullContentDisplay').innerHTML = displayHtml;
            } else {
                document.getElementById('fullContentDisplay').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Error loading content: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('fullContentDisplay').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    Error loading content: ${error.message}
                </div>
            `;
        });
    
    new bootstrap.Modal(document.getElementById('viewContentModal')).show();
}

function warnUser(contentId, contentType) {
    if (confirm('Send a warning to the user about this content?')) {
        // This would typically send a warning via AJAX
        alert('Warning sent to user (functionality to be implemented)');
    }
}

function exportModerationReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'content_moderation.php?' + params.toString();
}
</script>

<?php include '../includes/footer.php'; ?>
