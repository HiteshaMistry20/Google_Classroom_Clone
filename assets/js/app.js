// Google Classroom Clone - Main JavaScript

// Check if ClassroomApp is already defined
if (typeof ClassroomApp === 'undefined') {
class ClassroomApp {
    constructor() {
            this.baseUrl = this.getBaseUrl();
            this.isLoggedIn = this.checkLoginStatus();
        this.setupEventListeners();
            if (this.isLoggedIn) {
                this.setupRealTimeUpdates();
    }
        }

        getBaseUrl() {
            const pathArray = window.location.pathname.split('/');
            const segments = pathArray.filter(segment => segment !== '');
            let basePath = '';
            
            // Find the project root (usually 'test' in this case)
            // API files are always in the root directory
            if (segments.length > 0) {
                // Take only the first segment (project root)
                basePath = '/' + segments[0];
            }
            
            // For debugging
            console.log('Current pathname:', window.location.pathname);
            console.log('Segments:', segments);
            console.log('Base path:', basePath);
            
            return basePath;
        }

        checkLoginStatus() {
            return document.body.classList.contains('authenticated') ||
                   window.location.pathname.includes('/admin/') ||
                   window.location.pathname.includes('/teacher/') ||
                   window.location.pathname.includes('/student/');
        }

        setupEventListeners() {
            // Login form submission
            const loginForm = document.getElementById('loginForm');
            if (loginForm && !loginForm.hasAttribute('data-listener-attached')) {
                loginForm.setAttribute('data-listener-attached', 'true');
                loginForm.addEventListener('submit', (e) => this.handleLogin(e));
            }

            // Registration form submission
            const registerForm = document.getElementById('registerForm');
            if (registerForm && !registerForm.hasAttribute('data-listener-attached')) {
                registerForm.setAttribute('data-listener-attached', 'true');
                registerForm.addEventListener('submit', (e) => this.handleRegistration(e));
            }
        }

        async handleLogin(e) {
                e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            // Prevent multiple submissions
            if (submitBtn.disabled) return;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing In...';
            this.hideError();
            
        const formData = new FormData(form);
        
        try {
                const response = await fetch(this.baseUrl + '/api/login.php', {
                method: 'POST',
                body: formData
            });

                const data = await response.json();
                if (data.success) {
                    // Show success message briefly before redirect
                    this.showSuccess('Login successful! Redirecting...');
                setTimeout(() => {
                        window.location.href = data.redirect;
                }, 1000);
            } else {
                    this.showError(data.message || 'Login failed');
            }
        } catch (error) {
                console.error('Login error:', error);
                this.showError('An error occurred during login. Please try again.');
        } finally {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }

        async handleRegistration(e) {
            e.preventDefault();
            const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            // Prevent multiple submissions
            if (submitBtn.disabled) return;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';
            this.hideError();
            
        const formData = new FormData(form);
        
        try {
                const response = await fetch(this.baseUrl + '/api/register.php', {
                method: 'POST',
                body: formData
            });

                const data = await response.json();
                if (data.success) {
                    this.showSuccess('Account created successfully! Redirecting...');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
            } else {
                    this.showError(data.message || 'Registration failed');
            }
        } catch (error) {
                console.error('Registration error:', error);
                this.showError('An error occurred during registration. Please try again.');
        } finally {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }

        showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
        }

        showSuccess(message) {
            const errorDiv = document.getElementById('errorMessage');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.className = 'alert alert-success';
                errorDiv.style.display = 'block';
    }
        }

        hideError() {
            const errorDiv = document.getElementById('errorMessage');
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.className = 'alert alert-danger';
            }
        }

        setupRealTimeUpdates() {
            // Check for new notifications every 30 seconds
            this.checkNotifications();
            setInterval(() => this.checkNotifications(), 30000);

            // Check for new messages every 30 seconds
            this.checkMessages();
            setInterval(() => this.checkMessages(), 30000);
    }

    async checkNotifications() {
        try {
                const response = await fetch(this.baseUrl + '/api/notifications.php');
                if (response.status === 401) return; // Not logged in
                
                const data = await response.json();
                // Update notification badge
                const badge = document.getElementById('notificationBadge');
                if (badge && data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline';
                }
        } catch (error) {
                if (!error.message.includes('Unexpected token')) {
            console.error('Failed to check notifications:', error);
        }
        }
    }

    async checkMessages() {
        try {
                const url = this.baseUrl + '/api/messages.php';
                console.log('Fetching messages from:', url);
                const response = await fetch(url);
                console.log('Response status:', response.status);
                
                if (response.status === 401) return; // Not logged in
                
                const data = await response.json();
                console.log('Messages data:', data);
                
                // Update message badge
                const badge = document.getElementById('messageBadge');
                if (badge && data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline';
                }
            } catch (error) {
                console.error('Failed to check messages:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack
                });
        }
    }
}

    // Initialize the app
    if (typeof window.classroomApp === 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            window.classroomApp = new ClassroomApp();
        });
    }
}
