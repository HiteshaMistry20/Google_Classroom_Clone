<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$announcement_id = $_GET['id'] ?? 0;

if (!$announcement_id) {
    setFlashMessage('error', 'Invalid announcement ID.');
    header('Location: announcements.php');
    exit();
}

// Get announcement details and verify access
$stmt = $pdo->prepare("
    SELECT a.*, c.name as class_name, c.section, u.name as author_name, u.email as author_email
    FROM announcements a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON a.author_id = u.id
    WHERE a.id = ? AND ce.student_id = ?
");
$stmt->execute([$announcement_id, $user_id]);
$announcement = $stmt->fetch();

if (!$announcement) {
    setFlashMessage('error', 'Announcement not found or access denied.');
    header('Location: announcements.php');
    exit();
}

// Get comments for this announcement
$stmt = $pdo->prepare("
    SELECT c.*, u.name as user_name, u.role as user_role, u.id as user_id
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.announcement_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$announcement_id]);
$comments = $stmt->fetchAll();

$page_title = "Announcement Details";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($announcement['title']) ?> - Google Classroom Clone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .comment {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .comment-author {
            font-weight: bold;
            color: #007bff;
        }
        .comment-time {
            font-size: 0.85em;
            color: #6c757d;
        }
        .comment-content {
            margin-top: 5px;
        }
        .announcement-content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="authenticated">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <a href="announcements.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        Announcement Details
                    </h1>
                </div>

                <?php displayFlashMessage(); ?>

                <!-- Announcement Content -->
                <div class="announcement-content">
                    <h2><?= htmlspecialchars($announcement['title']) ?></h2>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-chalkboard"></i> <?= htmlspecialchars($announcement['class_name']) ?> (<?= htmlspecialchars($announcement['section']) ?>)<br>
                            <i class="fas fa-user"></i> <?= htmlspecialchars($announcement['author_name']) ?><br>
                            <i class="fas fa-clock"></i> <?= formatDateTime($announcement['created_at']) ?>
                        </small>
                    </div>
                    <div class="announcement-text">
                        <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-comments"></i> Comments (<?= count($comments) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Add Comment Form -->
                        <form id="commentForm" class="mb-4">
                            <div class="mb-3">
                                <label for="commentContent" class="form-label">Add a comment:</label>
                                <textarea class="form-control" id="commentContent" name="content" rows="3" placeholder="Write your comment here..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post Comment
                            </button>
                        </form>

                        <hr>

                        <!-- Comments List -->
                        <div id="commentsList">
                            <?php if (empty($comments)): ?>
                                <p class="text-muted text-center">No comments yet. Be the first to comment!</p>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment" data-comment-id="<?= $comment['id'] ?>">
                                        <div class="comment-author">
                                            <?= htmlspecialchars($comment['user_name']) ?>
                                            <?php if ($comment['user_role'] === 'teacher'): ?>
                                                <span class="badge bg-primary">Teacher</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="comment-time">
                                            <?= formatDateTime($comment['created_at']) ?>
                                        </div>
                                        <div class="comment-content">
                                            <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                        </div>
                                        <?php if ($comment['user_id'] == $user_id): ?>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteComment(<?= $comment['id'] ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        // Handle comment submission
        document.getElementById('commentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const content = document.getElementById('commentContent').value.trim();
            if (!content) {
                alert('Please enter a comment.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('announcement_id', <?= $announcement_id ?>);
            formData.append('content', content);
            
            fetch('../api/comments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add new comment to the list
                    addCommentToDOM(data.comment);
                    document.getElementById('commentContent').value = '';
                    
                    // Update comment count
                    const header = document.querySelector('.card-header h5');
                    const currentCount = parseInt(header.textContent.match(/\((\d+)\)/)[1]);
                    header.innerHTML = `<i class="fas fa-comments"></i> Comments (${currentCount + 1})`;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while posting the comment.');
            });
        });
        
        function addCommentToDOM(comment) {
            const commentsList = document.getElementById('commentsList');
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment';
            commentDiv.setAttribute('data-comment-id', comment.id);
            
            const userRole = '<?= $_SESSION['user_role'] ?>';
            const userId = <?= $user_id ?>;
            
            commentDiv.innerHTML = `
                <div class="comment-author">
                    ${comment.user_name}
                    ${comment.user_role === 'teacher' ? '<span class="badge bg-primary">Teacher</span>' : ''}
                </div>
                <div class="comment-time">
                    ${formatDateTime(comment.created_at)}
                </div>
                <div class="comment-content">
                    ${comment.content.replace(/\n/g, '<br>')}
                </div>
                ${comment.user_id == userId ? `
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteComment(${comment.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                ` : ''}
            `;
            
            // Remove "no comments" message if it exists
            const noCommentsMsg = commentsList.querySelector('.text-muted.text-center');
            if (noCommentsMsg) {
                noCommentsMsg.remove();
            }
            
            commentsList.appendChild(commentDiv);
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
                    // Remove comment from DOM
                    const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
                    commentElement.remove();
                    
                    // Update comment count
                    const header = document.querySelector('.card-header h5');
                    const currentCount = parseInt(header.textContent.match(/\((\d+)\)/)[1]);
                    header.innerHTML = `<i class="fas fa-comments"></i> Comments (${currentCount - 1})`;
                    
                    // Show "no comments" message if no comments left
                    const commentsList = document.getElementById('commentsList');
                    if (commentsList.children.length === 0) {
                        commentsList.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the comment.');
            });
        }
        
        function formatDateTime(dateTimeString) {
            const date = new Date(dateTimeString);
            return date.toLocaleString();
        }
    </script>
</body>
</html>
