<?php
// Authentication and session functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ../index.php');
        exit();
    }
}

function requireAnyRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $roles)) {
        header('Location: ../index.php');
        exit();
    }
}

function logout() {
    session_destroy();
    header('Location: ../index.php');
    exit();
}

// Class code generation
function generateClassCode() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function generateUniqueClassCode($pdo) {
    do {
        $code = generateClassCode();
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE class_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// QR Code generation
function generateQRCode($data) {
    $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($data);
    return $qr_url;
}

// File handling functions
function uploadFile($file, $destination_dir) {
    if (!file_exists($destination_dir)) {
        mkdir($destination_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'avi', 'mov'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    $file_name = uniqid() . '_' . $file['name'];
    $file_path = $destination_dir . '/' . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Return relative path from the root directory for consistent access
        $relative_path = str_replace('../', '', $file_path);
        return [
            'file_name' => $file['name'],
            'file_path' => $relative_path,
            'file_type' => $file['type'],
            'file_size' => $file['size']
        ];
    }
    
    return false;
}

// Helper function to extract file path from upload result
function extractFilePath($upload_result) {
    if (is_array($upload_result) && isset($upload_result['file_path'])) {
        return $upload_result['file_path'];
    }
    return $upload_result; // Return as-is if it's already a string
}

function getFileIcon($file_type) {
    $icons = [
        'application/pdf' => 'fas fa-file-pdf',
        'application/msword' => 'fas fa-file-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fas fa-file-word',
        'application/vnd.ms-powerpoint' => 'fas fa-file-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fas fa-file-powerpoint',
        'text/plain' => 'fas fa-file-alt',
        'image/jpeg' => 'fas fa-file-image',
        'image/png' => 'fas fa-file-image',
        'image/gif' => 'fas fa-file-image',
        'video/mp4' => 'fas fa-file-video',
        'video/avi' => 'fas fa-file-video',
        'video/quicktime' => 'fas fa-file-video'
    ];
    
    return isset($icons[$file_type]) ? $icons[$file_type] : 'fas fa-file';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Date and time functions
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

function isLate($due_date) {
    return strtotime($due_date) < time();
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Just now';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

// Notification functions
function createNotification($pdo, $user_id, $title, $message, $type, $related_id = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type, $related_id]);
}

function getUnreadNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function markNotificationAsRead($pdo, $notification_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    return $stmt->execute([$notification_id]);
}

// Email functions - Enhanced version using email notification system
function sendEmail($to, $subject, $message) {
    return sendEmailNotification($to, $subject, $message);
}

// Security functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Pagination functions
function getPagination($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'limit' => $records_per_page,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

// Search and filter functions
function buildSearchQuery($search_term, $search_fields) {
    if (empty($search_term)) {
        return '';
    }
    
    $conditions = [];
    foreach ($search_fields as $field) {
        $conditions[] = "$field LIKE ?";
    }
    
    return 'AND (' . implode(' OR ', $conditions) . ')';
}

function getSearchParams($search_term, $search_fields) {
    if (empty($search_term)) {
        return [];
    }
    
    $params = [];
    foreach ($search_fields as $field) {
        $params[] = "%$search_term%";
    }
    
    return $params;
}

// Export functions
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

// Validation functions
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function validateClassCode($code) {
    return preg_match('/^[A-Z0-9]{6}$/', $code);
}

// Utility functions
function getStatusBadge($status) {
    $badges = [
        'active' => 'success',
        'pending' => 'warning',
        'blocked' => 'danger',
        'archived' => 'secondary',
        'submitted' => 'info',
        'graded' => 'success',
        'late' => 'danger',
        'present' => 'success',
        'absent' => 'danger',
        'late_attendance' => 'warning'
    ];
    
    $badge_class = isset($badges[$status]) ? $badges[$status] : 'secondary';
    return "<span class='badge bg-$badge_class'>" . ucfirst($status) . "</span>";
}

function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Administrator',
        'teacher' => 'Teacher',
        'student' => 'Student'
    ];
    
    return isset($roles[$role]) ? $roles[$role] : ucfirst($role);
}

function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . '...';
}

// Error and success message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $alert_class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
        echo "<div class='alert $alert_class alert-dismissible fade show' role='alert'>";
        echo htmlspecialchars($flash['message']);
        echo "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        echo "</div>";
    }
}

// Include email functions
require_once __DIR__ . '/email_functions.php';
?>
