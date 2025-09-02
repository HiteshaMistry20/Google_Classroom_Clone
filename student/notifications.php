<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification_id = $_POST['notification_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            setFlashMessage('success', 'Notification marked as read!');
        } catch (Exception $e) {
            error_log("Mark read error: " . $e->getMessage());
            setFlashMessage('error', 'Failed to mark notification as read.');
        }
        
        header('Location: notifications.php');
        exit();
    }
    
    if (isset($_POST['mark_all_read'])) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            setFlashMessage('success', 'All notifications marked as read!');
        } catch (Exception $e) {
            error_log("Mark all read error: " . $e->getMessage());
            setFlashMessage('error', 'Failed to mark all notifications as read.');
        }
        
        header('Location: notifications.php');
        exit();
    }
    
    if (isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
            setFlashMessage('success', 'Notification deleted!');
        } catch (Exception $e) {
            error_log("Delete notification error: " . $e->getMessage());
            setFlashMessage('error', 'Failed to delete notification.');
        }
        
        header('Location: notifications.php');
        exit();
    }
    
    if (isset($_POST['bulk_delete'])) {
        $notification_ids = $_POST['notification_ids'] ?? [];
        
        if (!empty($notification_ids)) {
            try {
                $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?");
                $params = array_merge($notification_ids, [$user_id]);
                $stmt->execute($params);
                setFlashMessage('success', count($notification_ids) . ' notifications deleted successfully!');
            } catch (Exception $e) {
                error_log("Bulk delete error: " . $e->getMessage());
                setFlashMessage('error', 'Failed to delete notifications.');
            }
        }
        
        header('Location: notifications.php');
        exit();
    }
}

// Get notifications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_notifications = $stmt->fetchColumn();
    
    $total_pages = ceil($total_notifications / $limit);
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            CASE 
                WHEN n.type = 'assignment' THEN a.title
                WHEN n.type = 'material' THEN m.title
                WHEN n.type = 'announcement' THEN ann.title
                ELSE NULL
            END as related_title,
            CASE 
                WHEN n.type = 'assignment' THEN CONCAT('../student/assignment_details.php?id=', a.id)
                WHEN n.type = 'material' THEN CONCAT('../student/material_details.php?id=', m.id)
                WHEN n.type = 'announcement' THEN CONCAT('../student/class_details.php?id=', ann.class_id)
                ELSE '#'
            END as related_link
        FROM notifications n
        LEFT JOIN assignments a ON n.type = 'assignment' AND n.related_id = a.id
        LEFT JOIN materials m ON n.type = 'material' AND n.related_id = m.id
        LEFT JOIN announcements ann ON n.type = 'announcement' AND n.related_id = ann.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    $notifications = $stmt->fetchAll();
    
    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Notifications fetch error: " . $e->getMessage());
    $notifications = [];
    $total_notifications = 0;
    $total_pages = 0;
    $unread_count = 0;
}

$page_title = "Notifications";
include '../includes/header.php';
?>

<style>
    /* Enhanced Notification Styles */
    .notification-item {
        transition: all 0.3s ease;
        border-radius: 8px;
        margin-bottom: 0.5rem;
        padding: 1rem;
    }
    
    .notification-item:hover {
        background-color: #f8f9fa !important;
        transform: translateX(5px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .notification-item.bg-light {
        background-color: #e3f2fd !important;
        border-left: 4px solid #2196f3;
    }
    
    .notification-checkbox {
        margin-top: 0.5rem;
    }
    
    .notification-content h6 {
        color: #333;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    
    .notification-content p {
        color: #666;
        line-height: 1.4;
    }
    
    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 4px;
    }
    
    .badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }
    
    .card {
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 12px;
    }
    
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 0 !important;
        border: none;
    }
    
    .btn-toolbar {
        gap: 0.5rem;
    }
    
    .btn {
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-outline-info {
        border-color: #17a2b8;
        color: #17a2b8;
    }
    
    .btn-outline-info:hover {
        background-color: #17a2b8;
        border-color: #17a2b8;
    }
    
    .btn-outline-danger {
        border-color: #dc3545;
        color: #dc3545;
    }
    
    .btn-outline-danger:hover {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    .spinner-border-sm {
        width: 1rem;
        height: 1rem;
    }
    
    /* Responsive improvements */
    @media (max-width: 768px) {
        .btn-toolbar {
            flex-direction: column;
            align-items: stretch;
        }
        
        .btn-group {
            justify-content: center;
        }
        
        .notification-item .row {
            flex-direction: column;
        }
        
        .notification-item .col-md-1,
        .notification-item .col-md-8,
        .notification-item .col-md-3 {
            width: 100%;
            margin-bottom: 0.5rem;
        }
    }
</style>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Notifications</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if ($unread_count > 0): ?>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-check-double"></i> Mark All Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <button type="button" id="select-all-btn" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button type="button" id="bulk-delete-btn" class="btn btn-sm btn-outline-danger" style="display: none;">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                    </div>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <!-- Notification Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Notifications</h5>
                            <p class="card-text display-6" id="total-notifications"><?= $total_notifications ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Unread</h5>
                            <p class="card-text display-6 text-warning" id="unread-count"><?= $unread_count ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Read</h5>
                            <p class="card-text display-6 text-success" id="read-count"><?= $total_notifications - $unread_count ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Your Notifications</h5>
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm text-primary me-2" id="loading-spinner" style="display: none;"></div>
                        <small class="text-muted" id="last-updated">Last updated: Just now</small>
                    </div>
                </div>
                <div class="card-body" id="notifications-container">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-4" id="no-notifications">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No notifications yet.</p>
                            <p class="text-muted">You'll see notifications here when teachers post new content or update your grades.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="bulk-delete-form" action="">
                            <input type="hidden" name="bulk_delete" value="1">
                            <div class="notifications-list" id="notifications-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item border-bottom py-3 <?= $notification['is_read'] ? '' : 'bg-light' ?>" data-id="<?= $notification['id'] ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-1">
                                                <input type="checkbox" name="notification_ids[]" value="<?= $notification['id'] ?>" class="notification-checkbox form-check-input">
                                            </div>
                                            <div class="col-md-1">
                                            <?php
                                            $type_icons = [
                                                'assignment' => 'fas fa-clipboard-list text-primary',
                                                'material' => 'fas fa-book text-success',
                                                'grade' => 'fas fa-chart-line text-warning',
                                                'announcement' => 'fas fa-bullhorn text-info',
                                                'message' => 'fas fa-envelope text-secondary',
                                                'class_created' => 'fas fa-plus-circle text-success',
                                                'student_joined' => 'fas fa-user-plus text-info',
                                                'class_joined' => 'fas fa-sign-in-alt text-primary',
                                                'submission' => 'fas fa-upload text-warning',
                                                'submission_confirmed' => 'fas fa-check-circle text-success',
                                                'attendance' => 'fas fa-calendar-check text-info',
                                                'assignment_reminder' => 'fas fa-clock text-warning'
                                            ];
                                            $icon_class = $type_icons[$notification['type']] ?? 'fas fa-bell text-muted';
                                            ?>
                                            <i class="<?= $icon_class ?> fa-2x"></i>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="notification-content">
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($notification['title']) ?>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-warning ms-2">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="mb-1 text-muted"><?= htmlspecialchars($notification['message']) ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock"></i>
                                                    <?= getTimeAgo($notification['created_at']) ?>
                                                </small>
                                                <?php if ($notification['related_title'] && $notification['related_link'] !== '#'): ?>
                                                    <br>
                                                    <small>
                                                        <a href="<?= $notification['related_link'] ?>" class="text-decoration-none">
                                                            <i class="fas fa-external-link-alt"></i>
                                                            View: <?= htmlspecialchars($notification['related_title']) ?>
                                                        </a>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="btn-group btn-group-sm">
                                                <?php if (!$notification['is_read']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                        <button type="submit" name="mark_read" class="btn btn-outline-success" title="Mark as Read">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this notification?')">
                                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                    <button type="submit" name="delete_notification" class="btn btn-outline-danger" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Notifications pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Real-time notification system
let lastNotificationCount = <?= $unread_count ?>;
let lastNotificationIds = [<?= implode(',', array_column($notifications, 'id')) ?>];

// Function to update notification counts
function updateNotificationCounts(data) {
    if (data.total !== undefined) {
        document.getElementById('total-notifications').textContent = data.total;
    }
    if (data.unread !== undefined) {
        document.getElementById('unread-count').textContent = data.unread;
    }
    if (data.read !== undefined) {
        document.getElementById('read-count').textContent = data.read;
    }
    
    // Update header notification count
    const headerBadge = document.querySelector('.notification-badge');
    if (headerBadge && data.unread !== undefined) {
        if (data.unread > 0) {
            headerBadge.textContent = data.unread;
            headerBadge.style.display = 'flex';
        } else {
            headerBadge.style.display = 'none';
        }
    }
}

// Function to update notifications list
function updateNotificationsList(data) {
    const container = document.getElementById('notifications-container');
    const noNotifications = document.getElementById('no-notifications');
    const notificationsList = document.getElementById('notifications-list');
    
    if (data.notifications && data.notifications.length > 0) {
        // Hide no notifications message
        if (noNotifications) {
            noNotifications.style.display = 'none';
        }
        
        // Show notifications list
        if (notificationsList) {
            notificationsList.style.display = 'block';
        }
        
        // Update the list content
        let html = '<form method="POST" id="bulk-delete-form">';
        html += '<div class="notifications-list" id="notifications-list">';
        
        data.notifications.forEach(notification => {
            const iconClass = getNotificationIcon(notification.type);
            const timeAgo = getTimeAgo(notification.created_at);
            const newBadge = !notification.is_read ? '<span class="badge bg-warning ms-2">New</span>' : '';
            
            html += `
                <div class="notification-item border-bottom py-3 ${notification.is_read ? '' : 'bg-light'}" data-id="${notification.id}">
                    <div class="row align-items-center">
                        <div class="col-md-1">
                            <input type="checkbox" name="notification_ids[]" value="${notification.id}" class="notification-checkbox form-check-input">
                        </div>
                        <div class="col-md-1">
                            <i class="${iconClass} fa-2x"></i>
                        </div>
                        <div class="col-md-8">
                            <div class="notification-content">
                                <h6 class="mb-1">
                                    ${escapeHtml(notification.title)} ${newBadge}
                                </h6>
                                <p class="mb-1 text-muted">${escapeHtml(notification.message)}</p>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> ${timeAgo}
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3 text-end">
                            <div class="btn-group btn-group-sm">
                                ${!notification.is_read ? `
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="notification_id" value="${notification.id}">
                                        <button type="submit" name="mark_read" class="btn btn-outline-success" title="Mark as Read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                ` : ''}
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this notification?')">
                                    <input type="hidden" name="notification_id" value="${notification.id}">
                                    <button type="submit" name="delete_notification" class="btn btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div></form>';
        
        if (notificationsList) {
            notificationsList.innerHTML = html;
        } else {
            container.innerHTML = html;
        }
        
        // Reinitialize bulk delete functionality
        initializeBulkDelete();
    } else {
        // Show no notifications message
        if (noNotifications) {
            noNotifications.style.display = 'block';
        }
        if (notificationsList) {
            notificationsList.style.display = 'none';
        }
    }
}

// Helper function to get notification icon
function getNotificationIcon(type) {
    const icons = {
        'assignment': 'fas fa-clipboard-list text-primary',
        'material': 'fas fa-book text-success',
        'grade': 'fas fa-chart-line text-warning',
        'announcement': 'fas fa-bullhorn text-info',
        'message': 'fas fa-envelope text-secondary',
        'class_created': 'fas fa-plus-circle text-success',
        'student_joined': 'fas fa-user-plus text-info',
        'class_joined': 'fas fa-sign-in-alt text-primary',
        'submission': 'fas fa-upload text-warning',
        'submission_confirmed': 'fas fa-check-circle text-success',
        'attendance': 'fas fa-calendar-check text-info',
        'assignment_reminder': 'fas fa-clock text-warning'
    };
    return icons[type] || 'fas fa-bell text-muted';
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to get time ago
function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diffInSeconds = Math.floor((now - time) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}

// Function to check for new notifications
function checkForNewNotifications() {
    const spinner = document.getElementById('loading-spinner');
    const lastUpdated = document.getElementById('last-updated');
    
    spinner.style.display = 'inline-block';
    
    fetch('../api/notifications.php?action=get_realtime&user_id=<?= $user_id ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update counts
                updateNotificationCounts(data);
                
                // Check if there are new notifications
                if (data.unread > lastNotificationCount) {
                    // Update the notifications list
                    updateNotificationsList(data);
                    
                    // Update last known values
                    lastNotificationCount = data.unread;
                    if (data.notifications) {
                        lastNotificationIds = data.notifications.map(n => n.id);
                    }
                }
                
                // Update last updated time
                const now = new Date();
                lastUpdated.textContent = `Last updated: ${now.toLocaleTimeString()}`;
            }
        })
        .catch(error => {
            console.log('Notification check failed:', error);
        })
        .finally(() => {
            spinner.style.display = 'none';
        });
}

// Bulk delete functionality
let selectedNotifications = new Set();

// Global function to update bulk delete button
function updateBulkDeleteButton() {
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    if (bulkDeleteBtn) {
        if (selectedNotifications.size > 0) {
            bulkDeleteBtn.style.display = 'inline-block';
            bulkDeleteBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${selectedNotifications.size})`;
        } else {
            bulkDeleteBtn.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initializeBulkDelete();
    
    // Start checking for new notifications every 5 seconds
    setInterval(checkForNewNotifications, 5000);
    
    // Check immediately on page load
    setTimeout(checkForNewNotifications, 1000);
});

// Function to initialize bulk delete functionality
function initializeBulkDelete() {
    const selectAllBtn = document.getElementById('select-all-btn');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    
    // Clear previous selections
    selectedNotifications.clear();
    
    if (selectAllBtn) {
        // Remove existing event listeners
        selectAllBtn.replaceWith(selectAllBtn.cloneNode(true));
        const newSelectAllBtn = document.getElementById('select-all-btn');
        
        newSelectAllBtn.addEventListener('click', function() {
            const currentCheckboxes = document.querySelectorAll('.notification-checkbox');
            const isAllSelected = Array.from(currentCheckboxes).every(cb => cb.checked);
            
            currentCheckboxes.forEach(cb => {
                cb.checked = !isAllSelected;
                if (!isAllSelected) {
                    selectedNotifications.add(cb.value);
                } else {
                    selectedNotifications.delete(cb.value);
                }
            });
            
            updateBulkDeleteButton();
            newSelectAllBtn.innerHTML = isAllSelected ? 
                '<i class="fas fa-check-square"></i> Select All' : 
                '<i class="fas fa-square"></i> Deselect All';
        });
    }
    
    // Individual checkbox functionality
    checkboxes.forEach(cb => {
        // Remove existing event listeners
        cb.replaceWith(cb.cloneNode(true));
        const newCb = document.querySelector(`input[value="${cb.value}"]`);
        
        newCb.addEventListener('change', function() {
            if (this.checked) {
                selectedNotifications.add(this.value);
            } else {
                selectedNotifications.delete(this.value);
            }
            updateBulkDeleteButton();
        });
    });
    
    // Bulk delete button functionality
    if (bulkDeleteBtn) {
        // Remove existing event listeners
        bulkDeleteBtn.replaceWith(bulkDeleteBtn.cloneNode(true));
        const newBulkDeleteBtn = document.getElementById('bulk-delete-btn');
        
        newBulkDeleteBtn.addEventListener('click', function() {
            if (selectedNotifications.size > 0) {
                if (confirm(`Are you sure you want to delete ${selectedNotifications.size} notification(s)?`)) {
                    const currentCheckboxes = document.querySelectorAll('.notification-checkbox');
                    
                    // Remove unchecked checkboxes from form
                    currentCheckboxes.forEach(cb => {
                        if (!cb.checked) {
                            cb.remove();
                        }
                    });
                    
                    // Add bulk delete hidden input
                    const bulkDeleteInput = document.createElement('input');
                    bulkDeleteInput.type = 'hidden';
                    bulkDeleteInput.name = 'bulk_delete';
                    bulkDeleteInput.value = '1';
                    bulkDeleteForm.appendChild(bulkDeleteInput);
                    bulkDeleteForm.submit();
                }
            }
        });
    }
}


</script>

<?php include '../includes/footer.php'; ?>
