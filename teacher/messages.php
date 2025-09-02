<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

// Check if user is logged in and is a teacher
requireLogin();
requireRole('teacher');

$teacher_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $recipient_id = (int)$_POST['recipient_id'];
        $subject = sanitizeInput($_POST['subject']);
        $message = sanitizeInput($_POST['message']);
        
        if (empty($subject) || empty($message)) {
            setFlashMessage('error', 'Please fill in all required fields.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt->execute([$teacher_id, $recipient_id, $subject, $message])) {
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
    
    if (isset($_POST['delete_messages'])) {
        $message_ids = $_POST['message_ids'] ?? [];
        if (!empty($message_ids)) {
            $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
            
            if ($action === 'sent') {
                // Delete sent messages
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND sender_id = ?");
                $params = array_merge($message_ids, [$teacher_id]);
            } else {
                // Delete received messages
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id IN ($placeholders) AND receiver_id = ?");
                $params = array_merge($message_ids, [$teacher_id]);
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

// Get messages
try {
    if ($action === 'sent') {
        // Sent messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as recipient_name, u.email as recipient_email, u.id as recipient_id
            FROM messages m
            JOIN users u ON m.receiver_id = u.id
            WHERE m.sender_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$teacher_id]);
        $messages = $stmt->fetchAll();
        
        // Debug: Log the first message to see what fields are available
        if (!empty($messages)) {
            error_log("First sent message data: " . print_r($messages[0], true));
        }
    } else {
        // Received messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.name as sender_name, u.email as sender_email, u.id as sender_id
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$teacher_id]);
        $messages = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Messages query error: " . $e->getMessage());
    $messages = [];
}

// Get potential recipients (students in teacher's classes)
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.role
        FROM users u
        JOIN class_enrollments ce ON u.id = ce.student_id
        JOIN classes c ON ce.class_id = c.id
        WHERE c.teacher_id = ? AND u.role = 'student'
        ORDER BY u.name
    ");
    $stmt->execute([$teacher_id]);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    $students = [];
}

$page_title = "Messages";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Google Classroom Clone</title>
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
    /* Fix for horizontal layout issue */
    .message-item {
        width: 100%;
        display: block;
    }
    
    .message-item .d-flex {
        width: 100%;
    }
    
    .message-item .flex-grow-1 {
        width: 100%;
        min-width: 0; /* Prevents flex items from overflowing */
    }
    
    /* Fix button group layout */
    .btn-group {
        flex-wrap: nowrap;
        white-space: nowrap;
    }
    
    /* Responsive fixes */
    @media (max-width: 768px) {
        .message-item .d-flex {
            flex-direction: column;
        }
        
        .message-item .d-flex .ms-3 {
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
    </style>
</head>
<body class="authenticated">
    <div class="dashboard">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main>
            <div class="content-area">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Messages</h1>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="selectAllBtn">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                            <i class="fas fa-plus me-1"></i>Compose Message
                        </button>
                    </div>
                </div>

                <?php displayFlashMessage(); ?>

                <!-- Message Navigation -->
                <div class="card mb-4">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="messageTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $action === 'list' ? 'active' : ''; ?>" 
                                        id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" type="button" role="tab">
                                    <i class="fas fa-inbox me-1"></i>Inbox
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $action === 'sent' ? 'active' : ''; ?>" 
                                        id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab">
                                    <i class="fas fa-paper-plane me-1"></i>Sent
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Messages List -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <?= $action === 'sent' ? 'Sent Messages' : 'Inbox' ?>
                        </h5>
                        <div class="bulk-actions" style="display: none; background-color: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #dee2e6;">
                            <span class="me-2"><strong>Bulk Actions:</strong></span>
                            <button type="button" class="btn btn-danger btn-sm" id="deleteSelectedBtn">
                                <i class="fas fa-trash"></i> Delete Selected
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="cancelSelectionBtn">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="bulkDeleteForm" method="POST">
                            <input type="hidden" name="delete_messages" value="1">
                        <div class="tab-content" id="messageTabsContent">
                            <!-- Inbox -->
                            <div class="tab-pane fade <?php echo $action === 'list' ? 'show active' : ''; ?>" id="inbox" role="tabpanel">
                                <?php if (empty($messages) && $action === 'list'): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No messages</h5>
                                        <p class="text-muted">You don't have any messages yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message-item border-bottom pb-3 mb-3" style="width: 100%; display: block;">
                                            <div class="d-flex align-items-start" style="width: 100%;">
                                                <div class="form-check me-3 mt-1">
                                                    <input class="form-check-input message-checkbox" type="checkbox" 
                                                           name="message_ids[]" value="<?= $message['id'] ?>" 
                                                           id="msg_<?= $message['id'] ?>"
                                                           style="transform: scale(1.2);">
                                                    <label class="form-check-label" for="msg_<?= $message['id'] ?>" style="font-size: 12px; color: #6c757d;">
                                                        Select
                                                    </label>
                                                </div>
                                                <div class="flex-grow-1" style="width: 100%; min-width: 0;">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                            <p class="text-muted mb-1">From: <?php echo htmlspecialchars($message['sender_name']); ?></p>
                                                            <p class="mb-2"><?php echo htmlspecialchars(substr($message['message'], 0, 150)); ?><?php echo strlen($message['message']) > 150 ? '...' : ''; ?></p>
                                                            <small class="text-muted"><?php echo formatDateTime($message['created_at']); ?></small>
                                                        </div>
                                                        <div class="ms-3">
                                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewMessageModal"
                                                                    data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                                    data-sender="<?php echo htmlspecialchars($message['sender_name']); ?>"
                                                                    data-message="<?php echo htmlspecialchars($message['message']); ?>"
                                                                    data-date="<?php echo formatDateTime($message['created_at']); ?>">
                                                                <i class="fas fa-eye me-1"></i>View
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#replyMessageModal"
                                                                    data-sender-id="<?php echo $message['sender_id']; ?>"
                                                                    data-sender-name="<?php echo htmlspecialchars($message['sender_name']); ?>"
                                                                    data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                                    data-original-message="<?php echo htmlspecialchars($message['message']); ?>">
                                                                <i class="fas fa-reply me-1"></i>Reply
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Sent -->
                            <div class="tab-pane fade <?php echo $action === 'sent' ? 'show active' : ''; ?>" id="sent" role="tabpanel">
                                <?php if (empty($messages) && $action === 'sent'): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-paper-plane fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No sent messages</h5>
                                        <p class="text-muted">You haven't sent any messages yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message-item border-bottom pb-3 mb-3" style="width: 100%; display: block;">
                                            <div class="d-flex align-items-start" style="width: 100%;">
                                                <div class="form-check me-3 mt-1">
                                                    <input class="form-check-input message-checkbox" type="checkbox" 
                                                           name="message_ids[]" value="<?= $message['id'] ?>" 
                                                           id="msg_<?= $message['id'] ?>"
                                                           style="transform: scale(1.2);">
                                                    <label class="form-check-label" for="msg_<?= $message['id'] ?>" style="font-size: 12px; color: #6c757d;">
                                                        Select
                                                    </label>
                                                </div>
                                                <div class="flex-grow-1" style="width: 100%; min-width: 0;">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                            <p class="text-muted mb-1">To: <?php echo htmlspecialchars($message['recipient_name'] ?? 'Unknown'); ?></p>
                                                            <p class="mb-2"><?php echo htmlspecialchars(substr($message['message'], 0, 150)); ?><?php echo strlen($message['message']) > 150 ? '...' : ''; ?></p>
                                                            <small class="text-muted"><?php echo formatDateTime($message['created_at']); ?></small>
                                                        </div>
                                                        <div class="ms-3">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewMessageModal"
                                                                    data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                                    data-sender="<?php echo htmlspecialchars($message['recipient_name'] ?? 'Unknown'); ?>"
                                                                    data-message="<?php echo htmlspecialchars($message['message']); ?>"
                                                                    data-date="<?php echo formatDateTime($message['created_at']); ?>">
                                                                <i class="fas fa-eye me-1"></i>View
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compose Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="send_message" value="1">
                        
                        <div class="mb-3">
                            <label for="recipient_id" class="form-label">To</label>
                            <select class="form-select" id="recipient_id" name="recipient_id" required>
                                <option value="">Select recipient...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
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
                    <h5 class="modal-title">View Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Subject:</strong>
                        <p id="modalSubject"></p>
                    </div>
                    <div class="mb-3">
                        <strong>From:</strong>
                        <p id="modalSender"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong>
                        <p id="modalDate"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Message:</strong>
                        <p id="modalMessage"></p>
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
                        <input type="hidden" id="reply_recipient_id" name="recipient_id">
                        
                        <div class="mb-3">
                            <label for="reply_recipient_name" class="form-label">To</label>
                            <input type="text" class="form-control" id="reply_recipient_name" readonly>
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

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        // View message modal event handler
        document.getElementById('viewMessageModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const subject = button.getAttribute('data-subject');
            const sender = button.getAttribute('data-sender');
            const message = button.getAttribute('data-message');
            const date = button.getAttribute('data-date');
            
            document.getElementById('modalSubject').textContent = subject;
            document.getElementById('modalSender').textContent = sender;
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalMessage').textContent = message;
        });

        // Reply message modal event handler
        document.getElementById('replyMessageModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const senderId = button.getAttribute('data-sender-id');
            const senderName = button.getAttribute('data-sender-name');
            const subject = button.getAttribute('data-subject');
            const originalMessage = button.getAttribute('data-original-message');
            
            // Set the recipient (original sender)
            document.getElementById('reply_recipient_id').value = senderId;
            document.getElementById('reply_recipient_name').value = senderName;
            
            // Set the subject with "Re:" prefix
            const replySubject = subject.startsWith('Re:') ? subject : 'Re: ' + subject;
            document.getElementById('reply_subject').value = replySubject;
            
            // Show original message preview
            document.getElementById('original_message_preview').textContent = originalMessage;
            
            // Clear the message field
            document.getElementById('reply_message').value = '';
        });

        // Bulk delete functionality
        document.addEventListener('DOMContentLoaded', function() {
            const messageCheckboxes = document.querySelectorAll('.message-checkbox');
            const bulkActions = document.querySelector('.bulk-actions');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            const cancelSelectionBtn = document.getElementById('cancelSelectionBtn');
            const bulkDeleteForm = document.getElementById('bulkDeleteForm');
            const selectAllBtn = document.getElementById('selectAllBtn');

            // Show/hide bulk actions based on checkbox selection
            function updateBulkActions() {
                const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
                if (checkedBoxes.length > 0) {
                    bulkActions.style.display = 'block';
                    deleteSelectedBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${checkedBoxes.length})`;
                } else {
                    bulkActions.style.display = 'none';
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
            let allSelected = false;
            selectAllBtn.addEventListener('click', function() {
                allSelected = !allSelected;
                messageCheckboxes.forEach(checkbox => {
                    checkbox.checked = allSelected;
                });
                updateBulkActions();
                
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
        });
    </script>
</body>
</html> 