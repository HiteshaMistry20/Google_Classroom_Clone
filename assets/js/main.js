// Main JavaScript for Google Classroom Clone

// Get base URL for API calls
function getBaseUrl() {
    const pathArray = window.location.pathname.split('/');
    const segments = pathArray.filter(segment => segment !== '');
    let basePath = '';
    
    // Find the project root (usually 'test' in this case)
    // API files are always in the root directory
    if (segments.length > 0) {
        // Take only the first segment (project root)
        basePath = '/' + segments[0];
    }
    
    return basePath;
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initializeSidebar();
    initializeNotifications();
    initializeFileUploads();
    initializeModals();
    initializeSearch();
    initializeTooltips();
    initializeAutoSave();
    initializeRealTimeUpdates();
});

// Sidebar functionality
function initializeSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }
}

// Notification system
function initializeNotifications() {
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationCount = document.getElementById('notificationCount');
    
    // Load notifications
    loadNotifications();
    
    // Mark notification as read
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('mark-read')) {
            const notificationId = e.target.dataset.id;
            markNotificationAsRead(notificationId);
        }
    });
}

function loadNotifications() {
    // Get the base URL dynamically
    const baseUrl = getBaseUrl();
    fetch(baseUrl + '/api/notifications.php')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.count || data.unread_count);
            updateNotificationList(data.notifications);
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function markNotificationAsRead(notificationId) {
    const baseUrl = getBaseUrl();
    fetch(baseUrl + '/api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-notification-id="${notificationId}"]`).classList.remove('unread');
            updateNotificationBadge(data.unread_count);
        }
    })
    .catch(error => console.error('Error marking notification as read:', error));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationCount');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
}

function updateNotificationList(notifications) {
    const container = document.getElementById('notificationList');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-3">No notifications</div>';
        return;
    }
    
    notifications.forEach(notification => {
        const item = document.createElement('div');
        item.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
        item.dataset.notificationId = notification.id;
        
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${notification.title}</h6>
                    <p class="mb-1">${notification.message}</p>
                    <small class="notification-time">${formatTimeAgo(notification.created_at)}</small>
                </div>
                ${!notification.is_read ? '<button class="btn btn-sm btn-outline-primary mark-read" data-id="' + notification.id + '">Mark Read</button>' : ''}
            </div>
        `;
        
        container.appendChild(item);
    });
}

// File upload functionality
function initializeFileUploads() {
    const fileInputs = document.querySelectorAll('.file-input');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const previewContainer = document.getElementById('filePreview');
            
            if (previewContainer) {
                previewContainer.innerHTML = '';
                
                files.forEach(file => {
                    const preview = createFilePreview(file);
                    previewContainer.appendChild(preview);
                });
            }
        });
    });
}

function createFilePreview(file) {
    const div = document.createElement('div');
    div.className = 'file-preview d-flex align-items-center mb-2';
    
    const icon = getFileIcon(file.type);
    const size = formatFileSize(file.size);
    
    div.innerHTML = `
        <i class="${icon}"></i>
        <div class="flex-grow-1">
            <div class="fw-bold">${file.name}</div>
            <small class="text-muted">${size}</small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-file="${file.name}">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    return div;
}

function getFileIcon(mimeType) {
    const icons = {
        'application/pdf': 'fas fa-file-pdf text-danger',
        'application/msword': 'fas fa-file-word text-primary',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fas fa-file-word text-primary',
        'application/vnd.ms-powerpoint': 'fas fa-file-powerpoint text-warning',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'fas fa-file-powerpoint text-warning',
        'text/plain': 'fas fa-file-alt text-secondary',
        'image/jpeg': 'fas fa-file-image text-success',
        'image/png': 'fas fa-file-image text-success',
        'image/gif': 'fas fa-file-image text-success',
        'video/mp4': 'fas fa-file-video text-info',
        'video/avi': 'fas fa-file-video text-info',
        'video/quicktime': 'fas fa-file-video text-info'
    };
    
    return icons[mimeType] || 'fas fa-file text-muted';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Modal functionality
function initializeModals() {
    // Auto-focus on first input in modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const firstInput = this.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
    
    // Confirm delete modals
    const deleteButtons = document.querySelectorAll('[data-bs-target="#deleteModal"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const itemName = this.dataset.itemName;
            const itemType = this.dataset.itemType;
            const deleteUrl = this.dataset.deleteUrl;
            
            document.getElementById('deleteItemName').textContent = itemName;
            document.getElementById('deleteItemType').textContent = itemType;
            document.getElementById('confirmDeleteBtn').href = deleteUrl;
        });
    });
}

// Search functionality
function initializeSearch() {
    const searchInputs = document.querySelectorAll('.search-input');
    
    searchInputs.forEach(input => {
        let timeout;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const searchTerm = this.value.trim();
            
            timeout = setTimeout(() => {
                performSearch(searchTerm, this.dataset.searchType);
            }, 300);
        });
    });
}

function performSearch(searchTerm, searchType) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;
    
    if (searchTerm.length < 2) {
        searchResults.innerHTML = '';
        searchResults.style.display = 'none';
        return;
    }
    
    fetch(`api/search.php?term=${encodeURIComponent(searchTerm)}&type=${searchType}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data, searchResults);
        })
        .catch(error => console.error('Search error:', error));
}

function displaySearchResults(results, container) {
    container.innerHTML = '';
    
    if (results.length === 0) {
        container.innerHTML = '<div class="p-3 text-muted">No results found</div>';
    } else {
        results.forEach(result => {
            const item = document.createElement('div');
            item.className = 'search-result-item p-2 border-bottom';
            item.innerHTML = `
                <a href="${result.url}" class="text-decoration-none">
                    <div class="fw-bold">${result.title}</div>
                    <small class="text-muted">${result.description}</small>
                </a>
            `;
            container.appendChild(item);
        });
    }
    
    container.style.display = 'block';
}

// Tooltip initialization
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Auto-save functionality
function initializeAutoSave() {
    const autoSaveForms = document.querySelectorAll('.auto-save');
    
    autoSaveForms.forEach(form => {
        let timeout;
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    autoSaveForm(form);
                }, 1000);
            });
        });
    });
}

function autoSaveForm(form) {
    const formData = new FormData(form);
    formData.append('auto_save', '1');
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Auto-saved successfully', 'success');
        }
    })
    .catch(error => console.error('Auto-save error:', error));
}

// Real-time updates
function initializeRealTimeUpdates() {
    // Check for new notifications every 30 seconds
    setInterval(() => {
        loadNotifications();
    }, 30000);
    
    // Check for new messages every 60 seconds
    setInterval(() => {
        checkNewMessages();
    }, 60000);
    
    // Check for new assignments and announcements every 2 minutes
    setInterval(() => {
        checkNewContent();
    }, 120000);
}

// Check for new content (assignments, announcements, materials)
function checkNewContent() {
    const baseUrl = getBaseUrl();
    fetch(baseUrl + '/api/notifications.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                // Show browser notification if enabled
                if (Notification.permission === 'granted') {
                    data.notifications.forEach(notification => {
                        new Notification(notification.title, {
                            body: notification.message,
                            icon: baseUrl + '/assets/images/logo.png'
                        });
                    });
                }
                
                // Update notification badge
                updateNotificationBadge(data.count);
            }
        })
        .catch(error => console.error('Error checking new content:', error));
}

// Request browser notification permission
function requestNotificationPermission() {
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                console.log('Browser notifications enabled');
            }
        });
    }
}

function checkNewMessages() {
    fetch('api/messages.php?check_new=1')
        .then(response => response.json())
        .then(data => {
            if (data.new_count > 0) {
                showToast(`You have ${data.new_count} new message(s)`, 'info');
            }
        })
        .catch(error => console.error('Error checking messages:', error));
}

// Utility functions
function formatTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    
    return time.toLocaleDateString();
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove toast element after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Copy to clipboard functionality
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Copied to clipboard!', 'success');
    }, function(err) {
        console.error('Could not copy text: ', err);
        showToast('Failed to copy to clipboard', 'danger');
    });
}

// QR Code generation
function generateQRCode(data) {
    const qrContainer = document.getElementById('qrCodeContainer');
    if (qrContainer) {
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data)}`;
        qrContainer.innerHTML = `<img src="${qrUrl}" alt="QR Code" class="img-fluid">`;
    }
}

// Export functionality
function exportData(type, format) {
    const url = `api/export.php?type=${type}&format=${format}`;
    window.open(url, '_blank');
}

// Print functionality
function printPage() {
    window.print();
}

// Loading states
function showLoading(element) {
    if (element) {
        element.disabled = true;
        element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    }
}

function hideLoading(element, originalText) {
    if (element) {
        element.disabled = false;
        element.innerHTML = originalText;
    }
}

// Form validation
function validateForm(form) {
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Initialize form validation
document.addEventListener('submit', function(e) {
    if (e.target.classList.contains('needs-validation')) {
        if (!validateForm(e.target)) {
            e.preventDefault();
            e.stopPropagation();
        }
        e.target.classList.add('was-validated');
    }
});

// Initialize copy buttons
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('copy-btn')) {
        const text = e.target.dataset.copy;
        copyToClipboard(text);
    }
});

// Initialize QR code generation
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('generate-qr')) {
        const data = e.target.dataset.qrData;
        generateQRCode(data);
    }
});

// Initialize export buttons
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('export-btn')) {
        const type = e.target.dataset.exportType;
        const format = e.target.dataset.exportFormat;
        exportData(type, format);
    }
});

// Initialize print buttons
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('print-btn')) {
        printPage();
    }
});

// Initialize loading states
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('loading-btn')) {
        const button = e.target;
        const originalText = button.innerHTML;
        showLoading(button);
        
        // Reset loading state after a delay (for demo purposes)
        setTimeout(() => {
            hideLoading(button, originalText);
        }, 2000);
    }
});
