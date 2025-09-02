<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';
requireLogin();
requireRole('student');

$student_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'inbox';
$conversation_id = $_GET['conversation'] ?? null;

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
            if ($stmt->execute([$student_id, $recipient_id, $subject, $message])) {
                $message_id = $pdo->lastInsertId();
                
                // Send comprehensive notifications for new message
                notifyNewMessage($pdo, $message_id);
                
                setFlashMessage('success', 'Message sent successfully!');
                header('Location: messages_enhanced.php?conversation=' . $recipient_id);
                exit();
            } else {
                setFlashMessage('error', 'Failed to send message. Please try again.');
            }
        }
    }
    
    if (isset($_POST['mark_read'])) {
        $message_id = $_POST['message_id'];
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$message_id, $student_id]);
        header('Location: messages_enhanced.php');
        exit();
    }
}

// Get conversations (unique people the student has messaged with)
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        u.id, u.name, u.email, u.role,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
        (SELECT MAX(created_at) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) as last_message_time,
        (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT sender_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT DISTINCT receiver_id FROM messages WHERE sender_id = ?
    )
    ORDER BY last_message_time DESC
");
$stmt->execute([$student_id, $student_id, $student_id, $student_id, $student_id, $student_id, $student_id]);
$conversations = $stmt->fetchAll();

// Get teachers for new conversations
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

// Get messages for selected conversation
$messages = [];
$current_conversation = null;
if ($conversation_id) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$student_id, $conversation_id, $conversation_id, $student_id]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$conversation_id, $student_id]);
    
    // Get conversation partner info
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$conversation_id]);
    $current_conversation = $stmt->fetch();
}

$page_title = "Messages";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Google Classroom Clone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .messages-container {
            height: calc(100vh - 200px);
            display: flex;
        }
        .conversations-list {
            width: 300px;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        .conversation-item.active {
            background-color: #e3f2fd;
            border-left: 4px solid #007bff;
        }
        .conversation-item.unread {
            background-color: #fff3cd;
        }
        .conversation-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .conversation-preview {
            color: #6c757d;
            font-size: 0.9em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .unread-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7em;
            margin-left: 5px;
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        .message {
            margin-bottom: 15px;
            max-width: 70%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-bubble {
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
        }
        .message.sent .message-bubble {
            background-color: #007bff;
            color: white;
        }
        .message.received .message-bubble {
            background-color: #e9ecef;
            color: #212529;
        }
        .message-time {
            font-size: 0.75em;
            color: #6c757d;
            margin-top: 5px;
        }
        .message-input {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }
        .no-conversation {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            text-align: center;
        }
        .teacher-badge {
            background-color: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7em;
            margin-left: 5px;
        }
    </style>
</head>
<body class="authenticated">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-comments"></i> Messages</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                        <i class="fas fa-plus"></i> New Conversation
                    </button>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="messages-container">
                    <!-- Conversations List -->
                    <div class="conversations-list">
                        <?php if (empty($conversations)): ?>
                            <div class="p-3 text-center text-muted">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <p>No conversations yet</p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                                    Start a conversation
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <div class="conversation-item <?= $conversation_id == $conv['id'] ? 'active' : '' ?> <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>" 
                                     onclick="loadConversation(<?= $conv['id'] ?>)">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="conversation-name">
                                                <?= htmlspecialchars($conv['name']) ?>
                                                <?php if ($conv['role'] === 'teacher'): ?>
                                                    <span class="teacher-badge">Teacher</span>
                                                <?php endif; ?>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-preview">
                                                <?= htmlspecialchars(truncateText($conv['last_message'], 50)) ?>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?= formatDate($conv['last_message_time']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Area -->
                    <div class="chat-container">
                        <?php if ($current_conversation): ?>
                            <!-- Chat Header -->
                            <div class="chat-header">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <h5 class="mb-0">
                                            <?= htmlspecialchars($current_conversation['name']) ?>
                                            <?php if ($current_conversation['role'] === 'teacher'): ?>
                                                <span class="teacher-badge">Teacher</span>
                                            <?php endif; ?>
                                        </h5>
                                        <small class="text-muted"><?= htmlspecialchars($current_conversation['email']) ?></small>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages Area -->
                            <div class="messages-area" id="messagesArea">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center text-muted mt-5">
                                        <i class="fas fa-comment fa-2x mb-2"></i>
                                        <p>No messages yet. Start the conversation!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="message <?= $msg['sender_id'] == $student_id ? 'sent' : 'received' ?>">
                                            <div class="message-bubble">
                                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                            </div>
                                            <div class="message-time">
                                                <?= formatDateTime($msg['created_at']) ?>
                                                <?php if ($msg['sender_id'] == $student_id): ?>
                                                    <?php if ($msg['is_read']): ?>
                                                        <i class="fas fa-check-double text-primary"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-check text-muted"></i>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Message Input -->
                            <div class="message-input">
                                <form id="messageForm">
                                    <div class="input-group">
                                        <textarea class="form-control" id="messageInput" placeholder="Type your message..." rows="2" required></textarea>
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="no-conversation">
                                <div>
                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                    <h4>Select a conversation</h4>
                                    <p class="text-muted">Choose a conversation from the list or start a new one</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div class="modal fade" id="newConversationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="recipient_id" class="form-label">To:</label>
                            <select class="form-select" id="recipient_id" name="recipient_id" required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        function loadConversation(userId) {
            window.location.href = 'messages_enhanced.php?conversation=' + userId;
        }

        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            if (messagesArea) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }

        // Handle message form submission
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            // Create form data
            const formData = new FormData();
            formData.append('send_message', '1');
            formData.append('recipient_id', <?= $conversation_id ?? 'null' ?>);
            formData.append('subject', 'Message from <?= htmlspecialchars($_SESSION['user_name']) ?>');
            formData.append('message', message);
            
            // Send message via AJAX
            fetch('messages_enhanced.php?conversation=<?= $conversation_id ?? '' ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Reload the page to show new message
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send message. Please try again.');
            });
        });

        // Scroll to bottom when page loads
        window.addEventListener('load', scrollToBottom);

        // Auto-refresh conversations every 30 seconds
        setInterval(() => {
            if (!document.getElementById('newConversationModal').classList.contains('show')) {
                // Only refresh if no modal is open
                fetch('messages_enhanced.php?action=refresh&conversation=<?= $conversation_id ?? '' ?>')
                    .then(response => response.text())
                    .then(html => {
                        // Update conversations list if needed
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newConversations = doc.querySelector('.conversations-list');
                        if (newConversations) {
                            document.querySelector('.conversations-list').innerHTML = newConversations.innerHTML;
                        }
                    });
            }
        }, 30000);
    </script>
</body>
</html>
