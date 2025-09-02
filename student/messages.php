<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a student
requireLogin();
requireRole('student');

$student_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'inbox';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $recipient_id = (int)$_POST['receiver_id'];
        $subject = sanitizeInput($_POST['subject']);
        $message = sanitizeInput($_POST['message']);
        
        if (empty($subject) || empty($message) || $recipient_id <= 0) {
            setFlashMessage('error', 'Please fill in all required fields and select a valid recipient.');
        } else {
            // Verify that the recipient exists and is a teacher
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
            $stmt->execute([$recipient_id]);
            if (!$stmt->fetch()) {
                setFlashMessage('error', 'Invalid recipient selected.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                                if ($stmt->execute([$student_id, $recipient_id, $subject, $message])) {
                    $message_id = $pdo->lastInsertId();
                    
                    // Send comprehensive notifications for new message
                    notifyNewMessage($pdo, $message_id);
                    
                    setFlashMessage('success', 'Message sent successfully!');
                    header('Location: messages.php');
                    exit();
                } else {
                    setFlashMessage('error', 'Failed to send message. Please try again.');
                }
            }
        }
    }
    
    if (isset($_POST['mark_read'])) {
        $message_id = $_POST['message_id'];
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$message_id, $student_id]);
        header('Location: messages.php');
        exit();
    }
    
    if (isset($_POST['delete_messages'])) {
        $message_ids = $_POST['message_ids'] ?? [];
        if (!empty($message_ids)) {
            $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
            
            if ($action === 'inbox') {
                // Delete received messages
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND receiver_id = ?");
                $params = array_merge($message_ids, [$student_id]);
            } else {
                // Delete sent messages
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND sender_id = ?");
                $params = array_merge($message_ids, [$student_id]);
            }
            
            if ($stmt->execute($params)) {
                setFlashMessage('success', count($message_ids) . ' message(s) deleted successfully.');
            } else {
                setFlashMessage('error', 'Failed to delete messages.');
            }
        } else {
            setFlashMessage('error', 'Please select messages to delete.');
        }
        header('Location: messages.php?action=' . $action);
        exit();
    }
}

// Get messages based on action
if ($action === 'inbox') {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.email as sender_email
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.receiver_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$student_id]);
    $messages = $stmt->fetchAll();
} else { // sent
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as receiver_name, u.email as receiver_email
        FROM messages m
        JOIN users u ON m.receiver_id = u.id
        WHERE m.sender_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$student_id]);
    $messages = $stmt->fetchAll();
}

// Get teachers for sending messages
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN classes c ON u.id = c.teacher_id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND u.role = 'teacher' AND u.status = 'active'
    ORDER BY u.name
");
$stmt->execute([$student_id]);
$teachers = $stmt->fetchAll();

$page_title = "Messages";
include '../includes/header.php';
?>

<style>
.hover-bg-light:hover {
    background-color: #f8f9fa !important;
    transition: background-color 0.2s ease;
}

.message-checkbox:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-group .btn {
    border-radius: 6px !important;
    margin-right: 4px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}

.card {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.list-group-item {
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    width: 100%;
    display: block;
}

.list-group-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Fix for horizontal layout issue */
.list-group {
    display: flex;
    flex-direction: column;
    width: 100%;
    flex-wrap: nowrap;
}

.list-group-flush {
    border: none;
}

.list-group-flush .list-group-item {
    border: none;
    border-bottom: 1px solid #dee2e6;
}

.list-group-flush .list-group-item:last-child {
    border-bottom: none;
}

.list-group-item .d-flex {
    width: 100%;
}

.list-group-item .flex-grow-1 {
    width: 100%;
    min-width: 0; /* Prevents flex items from overflowing */
}

/* Ensure proper message layout */
.message-item {
    width: 100%;
    display: block;
}

/* Fix button group layout */
.btn-group {
    flex-wrap: nowrap;
    white-space: nowrap;
}

/* Responsive fixes */
@media (max-width: 768px) {
    .list-group-item .d-flex {
        flex-direction: column;
    }
    
    .list-group-item .d-flex .ms-3 {
        margin-left: 0 !important;
        margin-top: 10px;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn {
        margin-bottom: 5px;
        width: 100%;
    }
}

.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

.bulk-actions {
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-envelope"></i> Messages</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="selectAllBtn">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                        <i class="fas fa-plus"></i> New Message
                    </button>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Debug Information (remove this after fixing) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-info">
                    <strong>Debug Info:</strong><br>
                    Action: <?= $action ?><br>
                    User ID: <?= $student_id ?><br>
                    Messages Count: <?= count($messages) ?><br>
                    Teachers Count: <?= count($teachers) ?>
                </div>
                <div class="alert alert-warning">
                    <strong>Test Bulk Actions:</strong><br>
                    <button type="button" class="btn btn-sm btn-warning" onclick="testBulkActions()">Test Show Bulk Actions</button>
                    <button type="button" class="btn btn-sm btn-info" onclick="checkCheckboxes()">Check Checkboxes</button>
                </div>
            <?php endif; ?>

            <!-- Message Navigation -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="btn-group" role="group">
                        <a href="?action=inbox" class="btn btn-outline-primary <?= $action === 'inbox' ? 'active' : '' ?>">
                            <i class="fas fa-inbox"></i> Inbox
                        </a>
                        <a href="?action=sent" class="btn btn-outline-primary <?= $action === 'sent' ? 'active' : '' ?>">
                            <i class="fas fa-paper-plane"></i> Sent
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center py-3">
                    <div class="d-flex align-items-center">
                        <h5 class="card-title mb-0 fw-semibold text-dark">
                            <i class="fas fa-inbox me-2 text-primary"></i>
                            <?= $action === 'inbox' ? 'Inbox Messages' : 'Sent Messages' ?>
                        </h5>
                        <span class="badge bg-light text-dark ms-2"><?= count($messages) ?></span>
                    </div>
                    <div class="bulk-actions" style="display: none;">
                        <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-3 border">
                            <span class="text-muted small fw-medium">Selected:</span>
                            <button type="button" class="btn btn-danger btn-sm px-3" id="deleteSelectedBtn">
                                <i class="fas fa-trash me-1"></i> Delete
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="cancelSelectionBtn">
                                <i class="fas fa-times me-1"></i> Cancel
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form id="bulkDeleteForm" method="POST">
                        <input type="hidden" name="delete_messages" value="1">
                    <?php if (empty($messages)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No messages found.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($messages as $msg): ?>
                                <div class="list-group-item list-group-item-action border-0 py-3 px-0 hover-bg-light" style="width: 100%; display: block;">
                                    <div class="d-flex align-items-start" style="width: 100%;">
                                        <div class="form-check me-3 mt-1">
                                            <input class="form-check-input message-checkbox" type="checkbox" 
                                                   name="message_ids[]" value="<?= $msg['id'] ?>" 
                                                   id="msg_<?= $msg['id'] ?>"
                                                   style="transform: scale(1.1);">
                                        </div>
                                        <div class="flex-grow-1" style="width: 100%; min-width: 0;">
                                    <div class="d-flex w-100 justify-content-between align-items-start mb-2">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-semibold text-dark">
                                                <?php if ($action === 'inbox' && !$msg['is_read']): ?>
                                                    <span class="badge bg-primary me-2" style="width: 8px; height: 8px; border-radius: 50%;"></span>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($msg['subject']) ?>
                                            </h6>
                                            <p class="mb-1 text-muted small">
                                                <i class="fas fa-user me-1"></i>
                                                <?= $action === 'inbox' ? htmlspecialchars($msg['sender_name'] ?? 'Unknown') : htmlspecialchars($msg['receiver_name'] ?? 'Unknown') ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-envelope me-1"></i>
                                                <?= $action === 'inbox' ? htmlspecialchars($msg['sender_email']) : htmlspecialchars($msg['receiver_email']) ?>
                                            </p>
                                        </div>
                                        <small class="text-muted ms-3">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= formatDate($msg['created_at']) ?>
                                        </small>
                                    </div>
                                    <p class="mb-2 text-secondary">
                                        <?= htmlspecialchars(truncateText($msg['message'], 120)) ?>
                                    </p>
                                    <div class="d-flex justify-content-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-sm px-3" 
                                                    data-bs-toggle="modal" data-bs-target="#viewMessageModal" 
                                                    data-subject="<?= htmlspecialchars($msg['subject']) ?>"
                                                    data-message="<?= htmlspecialchars($msg['message']) ?>"
                                                    data-sender="<?= $action === 'inbox' ? htmlspecialchars($msg['sender_name'] ?? 'Unknown') : htmlspecialchars($msg['receiver_name'] ?? 'Unknown') ?>"
                                                    data-date="<?= formatDate($msg['created_at']) ?>">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                            <?php if ($action === 'inbox'): ?>
                                                <button type="button" class="btn btn-outline-success btn-sm px-3" 
                                                        data-bs-toggle="modal" data-bs-target="#replyMessageModal" 
                                                        data-sender-id="<?= $msg['sender_id'] ?>"
                                                        data-sender-name="<?= htmlspecialchars($msg['sender_name']) ?>"
                                                        data-subject="<?= htmlspecialchars($msg['subject']) ?>"
                                                        data-original-message="<?= htmlspecialchars($msg['message']) ?>">
                                                    <i class="fas fa-reply me-1"></i> Reply
                                                </button>
                                                <?php if (!$msg['is_read']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="mark_read" class="btn btn-outline-info btn-sm px-3">
                                                            <i class="fas fa-check me-1"></i> Mark Read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Send Message Modal -->
<div class="modal fade" id="sendMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="receiver_id" class="form-label">To:</label>
                        <select class="form-select" id="receiver_id" name="receiver_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>">
                                    <?= htmlspecialchars($teacher['name']) ?> (<?= htmlspecialchars($teacher['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject:</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message:</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMessageSubject"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>From:</strong> <span id="viewMessageSender"></span>
                </div>
                <div class="mb-3">
                    <strong>Date:</strong> <span id="viewMessageDate"></span>
                </div>
                <div class="mb-3">
                    <strong>Message:</strong>
                    <div class="border rounded p-3 mt-2" id="viewMessageContent"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reply Message Modal -->
<div class="modal fade" id="replyMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reply to Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" id="reply_receiver_id" name="receiver_id">
                    
                    <div class="mb-3">
                        <label for="reply_receiver_name" class="form-label">To</label>
                        <input type="text" class="form-control" id="reply_receiver_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reply_subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="reply_subject" name="subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reply_message" class="form-label">Message</label>
                        <textarea class="form-control" id="reply_message" name="message" rows="8" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Original Message</label>
                        <div class="border rounded p-3 bg-light">
                            <small class="text-muted" id="original_message_preview"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle view message modal
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing messages functionality...');
    const viewMessageModal = document.getElementById('viewMessageModal');
    if (viewMessageModal) {
        viewMessageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const subject = button.getAttribute('data-subject');
            const message = button.getAttribute('data-message');
            const sender = button.getAttribute('data-sender');
            const date = button.getAttribute('data-date');
            
            document.getElementById('viewMessageSubject').textContent = subject;
            document.getElementById('viewMessageSender').textContent = sender;
            document.getElementById('viewMessageDate').textContent = date;
            document.getElementById('viewMessageContent').textContent = message;
        });
    }

    // Handle reply message modal
    const replyMessageModal = document.getElementById('replyMessageModal');
    if (replyMessageModal) {
        replyMessageModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const senderId = button.getAttribute('data-sender-id');
            const senderName = button.getAttribute('data-sender-name');
            const subject = button.getAttribute('data-subject');
            const originalMessage = button.getAttribute('data-original-message');
            
            // Set the recipient (original sender)
            document.getElementById('reply_receiver_id').value = senderId;
            document.getElementById('reply_receiver_name').value = senderName;
            
            // Set the subject with "Re:" prefix
            const replySubject = subject.startsWith('Re:') ? subject : 'Re: ' + subject;
            document.getElementById('reply_subject').value = replySubject;
            
            // Show original message preview
            document.getElementById('original_message_preview').textContent = originalMessage;
            
            // Clear the message field
            document.getElementById('reply_message').value = '';
        });
    }

    // Bulk delete functionality
    const messageCheckboxes = document.querySelectorAll('.message-checkbox');
    const bulkActions = document.querySelector('.bulk-actions');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const cancelSelectionBtn = document.getElementById('cancelSelectionBtn');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    console.log('Found message checkboxes:', messageCheckboxes.length);
    console.log('Bulk actions element:', bulkActions);
    console.log('Delete button:', deleteSelectedBtn);

    // Show/hide bulk actions based on checkbox selection
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
        console.log('Checked boxes:', checkedBoxes.length);
        
        if (checkedBoxes.length > 0) {
            bulkActions.style.display = 'block';
            deleteSelectedBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${checkedBoxes.length})`;
            console.log('Showing bulk actions');
        } else {
            bulkActions.style.display = 'none';
            console.log('Hiding bulk actions');
        }
    }

    // Add event listeners to checkboxes
    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });

    // Delete selected messages
    deleteSelectedBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
        if (checkedBoxes.length > 0) {
            if (confirm(`Are you sure you want to delete ${checkedBoxes.length} message(s)? This action cannot be undone.`)) {
                bulkDeleteForm.submit();
            }
        }
    });

    // Cancel selection
    cancelSelectionBtn.addEventListener('click', function() {
        messageCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateBulkActions();
    });

    // Select all functionality
    const selectAllBtn = document.getElementById('selectAllBtn');
    let allSelected = false;

    // Select all button functionality
    selectAllBtn.addEventListener('click', function() {
        allSelected = !allSelected;
        messageCheckboxes.forEach(checkbox => {
            checkbox.checked = allSelected;
        });
        updateBulkActions();
        updateSelectAll();
        
        // Update button text
        if (allSelected) {
            selectAllBtn.innerHTML = '<i class="fas fa-square"></i> Deselect All';
            selectAllBtn.classList.remove('btn-outline-secondary');
            selectAllBtn.classList.add('btn-secondary');
        } else {
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
            selectAllBtn.classList.remove('btn-secondary');
            selectAllBtn.classList.add('btn-outline-secondary');
        }
    });

    // Update select all button state
    function updateSelectAll() {
        const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
        const totalBoxes = messageCheckboxes.length;
        
        if (checkedBoxes.length === 0) {
            allSelected = false;
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
            selectAllBtn.classList.remove('btn-secondary');
            selectAllBtn.classList.add('btn-outline-secondary');
        } else if (checkedBoxes.length === totalBoxes) {
            allSelected = true;
            selectAllBtn.innerHTML = '<i class="fas fa-square"></i> Deselect All';
            selectAllBtn.classList.remove('btn-outline-secondary');
            selectAllBtn.classList.add('btn-secondary');
        } else {
            allSelected = false;
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
            selectAllBtn.classList.remove('btn-secondary');
            selectAllBtn.classList.add('btn-outline-secondary');
        }
    }

    // Update select all when individual checkboxes change
    messageCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAll);
    });

    // Test functions for debugging
    window.testBulkActions = function() {
        console.log('Testing bulk actions...');
        const bulkActions = document.querySelector('.bulk-actions');
        if (bulkActions) {
            bulkActions.style.display = 'block';
            console.log('Bulk actions shown manually');
        } else {
            console.log('Bulk actions element not found');
        }
    };

    window.checkCheckboxes = function() {
        console.log('Checking checkboxes...');
        const checkboxes = document.querySelectorAll('.message-checkbox');
        console.log('Found checkboxes:', checkboxes.length);
        checkboxes.forEach((checkbox, index) => {
            console.log(`Checkbox ${index}:`, checkbox.checked, checkbox.value);
        });
    };
});
</script>

<?php include '../includes/footer.php'; ?>
