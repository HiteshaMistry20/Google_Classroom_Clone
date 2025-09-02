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
    if (isset($_POST['create_announcement'])) {
        $title = sanitizeInput($_POST['title']);
        $content = sanitizeInput($_POST['content']);
        $class_id = (int)$_POST['class_id'];
        // Note: is_important functionality removed as table doesn't have this column
        
        // Validate class ownership
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ? AND status = 'active'");
        $stmt->execute([$class_id, $teacher_id]);
        if (!$stmt->fetch()) {
            setFlashMessage('error', 'Invalid class selected.');
            header('Location: announcements.php');
            exit();
        }
        
        // Insert announcement into database
        $stmt = $pdo->prepare("INSERT INTO announcements (class_id, title, content, author_id) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$class_id, $title, $content, $teacher_id])) {
            $announcement_id = $pdo->lastInsertId();
            
            // Send comprehensive notifications for new announcement
            notifyNewAnnouncement($pdo, $announcement_id, $class_id);
            
            setFlashMessage('success', 'Announcement posted successfully!');
        } else {
            setFlashMessage('error', 'Failed to post announcement. Please try again.');
        }
        
        header('Location: announcements.php?class_id=' . $class_id);
        exit();
    }
    
    if (isset($_POST['delete_announcement'])) {
        $announcement_id = (int)$_POST['announcement_id'];
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT a.*, c.teacher_id FROM announcements a JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND a.author_id = ?");
        $stmt->execute([$announcement_id, $teacher_id]);
        $announcement = $stmt->fetch();
        
        if ($announcement) {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            if ($stmt->execute([$announcement_id])) {
                setFlashMessage('success', 'Announcement deleted successfully!');
            } else {
                setFlashMessage('error', 'Failed to delete announcement.');
            }
        } else {
            setFlashMessage('error', 'Announcement not found or access denied.');
        }
        
        header('Location: announcements.php?class_id=' . $class_id);
        exit();
    }
}

// Get teacher's classes
$stmt = $pdo->prepare("SELECT id, name, subject FROM classes WHERE teacher_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$teacher_id]);
$classes = $stmt->fetchAll();

// Get announcements for selected class
$announcements = [];
if ($class_id > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as class_name, u.name,
               (SELECT COUNT(*) FROM comments c WHERE c.announcement_id = a.id) as comment_count
        FROM announcements a 
        JOIN classes c ON a.class_id = c.id 
        JOIN users u ON a.author_id = u.id
        WHERE a.class_id = ? AND c.teacher_id = ? 
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$class_id, $teacher_id]);
    $announcements = $stmt->fetchAll();
}

$page_title = "Announcements";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Announcements</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                        <i class="fas fa-bullhorn"></i> Post Announcement
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
                <!-- Announcements List -->
                <div class="row">
                    <?php if (empty($announcements)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No announcements posted yet for this class.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                                                         <div class="col-12 mb-4">
                                 <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1"><?= htmlspecialchars($announcement['title']) ?></h5>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="viewComments(<?= $announcement['id'] ?>)">
                                                        <i class="fas fa-comments"></i> View Comments (<?= $announcement['comment_count'] ?>)
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="editAnnouncement(<?= $announcement['id'] ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteAnnouncement(<?= $announcement['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Posted by <?= htmlspecialchars($announcement['name']) ?> 
                                                on <?= formatDate($announcement['created_at']) ?>
                                            </small>
                                            <div>
                                                <?php if ($announcement['comment_count'] > 0): ?>
                                                    <span class="badge bg-info"><?= $announcement['comment_count'] ?> comments</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please select a class to view and manage announcements.
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Create Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Post Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createAnnouncementForm">
                <div class="modal-body">
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
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" name="content" id="content" rows="6" required></textarea>
                    </div>
                    
                                         <!-- Note: Important announcement functionality removed as table doesn't support it -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_announcement" class="btn btn-primary">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this announcement? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="announcement_id" id="deleteAnnouncementId">
                    <button type="submit" name="delete_announcement" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div class="modal fade" id="commentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Comments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="currentAnnouncementId">
                
                <!-- Add Comment Form -->
                <div class="mb-4">
                    <h6>Add a Comment</h6>
                    <div class="row">
                        <div class="col-md-10">
                            <textarea class="form-control" id="commentContent" rows="3" placeholder="Write your comment..."></textarea>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-primary" onclick="addComment(document.getElementById('currentAnnouncementId').value)">
                                <i class="fas fa-paper-plane"></i> Post
                            </button>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Comments Container -->
                <div id="commentsContainer">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading comments...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteAnnouncement(announcementId) {
    document.getElementById('deleteAnnouncementId').value = announcementId;
    new bootstrap.Modal(document.getElementById('deleteAnnouncementModal')).show();
}

function editAnnouncement(announcementId) {
    // TODO: Implement edit functionality
    alert('Edit functionality will be implemented in the next update.');
}

function viewComments(announcementId) {
    // Set current announcement ID
    document.getElementById('currentAnnouncementId').value = announcementId;
    
    // Load comments for this announcement
    fetch(`../api/comments.php?action=get&announcement_id=${announcementId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayComments(data.comments);
                new bootstrap.Modal(document.getElementById('commentsModal')).show();
            } else {
                alert('Error loading comments: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading comments. Please try again.');
        });
}

function displayComments(comments) {
    const container = document.getElementById('commentsContainer');
    if (comments.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-3">No comments yet</div>';
        return;
    }
    
    let html = '';
    comments.forEach(comment => {
        html += `
            <div class="comment-item border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            <strong class="me-2">${comment.user_name}</strong>
                            <span class="badge bg-secondary">${comment.user_role}</span>
                            <small class="text-muted ms-2">${formatDateTime(comment.created_at)}</small>
                        </div>
                        <p class="mb-0">${comment.content}</p>
                    </div>
                    <div class="ms-2">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteComment(${comment.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function addComment(announcementId) {
    const content = document.getElementById('commentContent').value.trim();
    if (!content) {
        alert('Please enter a comment');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('announcement_id', announcementId);
    formData.append('content', content);
    
    fetch('../api/comments.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('commentContent').value = '';
            // Reload comments
            viewComments(announcementId);
        } else {
            alert('Error adding comment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding comment. Please try again.');
    });
}

function deleteComment(commentId) {
    if (!confirm('Are you sure you want to delete this comment?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('comment_id', commentId);
    
    fetch('../api/comments.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload comments
            const announcementId = document.getElementById('currentAnnouncementId').value;
            viewComments(announcementId);
        } else {
            alert('Error deleting comment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting comment. Please try again.');
    });
}

// Character counter for content
document.getElementById('content').addEventListener('input', function() {
    const maxLength = 1000;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    // You can add a character counter display here if needed
    if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
