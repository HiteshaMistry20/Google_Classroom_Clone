<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = 'Invalid role selected';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email already registered. Please use a different email address.';
            } else {
                // Hash password and set status
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $status = ($role === 'teacher') ? 'pending' : 'active'; // Teachers need admin approval
                
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                
                if ($stmt->execute([$name, $email, $hashed_password, $role, $status])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Create notification for admin if teacher registration
                    if ($role === 'teacher') {
                        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (1, 'registration', ?, ?)");
                        $stmt->execute([
                            "New teacher registration: $name ($email)",
                            "admin/users.php"
                        ]);
                    }
                    
                    if ($role === 'teacher') {
                        $success = 'Registration successful! Your account is pending admin approval. You will be able to login once your account is approved.';
                    } else {
                        $success = 'Registration successful! You can now login to your account.';
                    }
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'An error occurred during registration. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Google Classroom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Google Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            position: relative;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: #4285f4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .logo i {
            font-size: 1.8rem;
            color: white;
        }

        .register-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: #202124;
            margin-bottom: 0.5rem;
        }

        .register-subtitle {
            color: #5f6368;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #202124;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #4285f4;
            box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.2);
        }

        .form-control::placeholder {
            color: #9aa0a6;
        }

        .role-selector {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.2rem;
        }

        .role-option {
            flex: 1;
            padding: 1rem;
            border: 1px solid #dadce0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }

        .role-option:hover {
            border-color: #4285f4;
            background: #f8f9ff;
        }

        .role-option.selected {
            border-color: #4285f4;
            background: #e8f0fe;
        }

        .role-option i {
            font-size: 1.5rem;
            color: #4285f4;
            margin-bottom: 0.5rem;
            display: block;
        }

        .role-option h6 {
            font-size: 0.9rem;
            color: #202124;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .role-option p {
            font-size: 0.8rem;
            color: #5f6368;
            margin: 0;
        }

        .btn-register {
            width: 100%;
            padding: 0.75rem;
            background: #4285f4;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
            margin-bottom: 1rem;
        }

        .btn-register:hover {
            background: #3367d6;
        }

        .btn-signin {
            width: 100%;
            padding: 0.75rem;
            background: transparent;
            border: 1px solid #dadce0;
            border-radius: 8px;
            color: #4285f4;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s ease;
        }

        .btn-signin:hover {
            background: #f8f9ff;
            border-color: #4285f4;
            color: #4285f4;
            text-decoration: none;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: none;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .alert-success {
            background: #e6f4ea;
            color: #137333;
            border-left: 4px solid #34a853;
        }

        .alert-danger {
            background: #fce8e6;
            color: #c5221f;
            border-left: 4px solid #ea4335;
        }

        .alert i {
            margin-top: 0.1rem;
            flex-shrink: 0;
        }

        .divider {
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #dadce0;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            color: #5f6368;
            font-size: 0.9rem;
        }

        .hidden-select {
            display: none;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 2rem;
                margin: 1rem;
            }

            .role-selector {
                flex-direction: column;
            }

            .register-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 class="register-title">Create Account</h1>
            <p class="register-subtitle">Join our learning community</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                       placeholder="Enter your full name" required>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       placeholder="Enter your email address" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Select Your Role</label>
                <div class="role-selector">
                    <div class="role-option" data-role="student">
                        <i class="fas fa-user-graduate"></i>
                        <h6>Student</h6>
                        <p>Join classes</p>
                    </div>
                    <div class="role-option" data-role="teacher">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h6>Teacher</h6>
                        <p>Create classes</p>
                    </div>
                </div>
                <select class="form-control hidden-select" id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Create a password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       placeholder="Confirm your password" required>
            </div>
            
            <button type="submit" class="btn-register">
                Create Account
            </button>
        </form>
        
        <div class="divider">
            <span>Already have an account?</span>
        </div>
        
        <a href="index.php" class="btn-signin">
            Sign In
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Role selector functionality
        document.addEventListener('DOMContentLoaded', function() {
            const roleOptions = document.querySelectorAll('.role-option');
            const roleSelect = document.getElementById('role');
            
            roleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    roleOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    const role = this.dataset.role;
                    roleSelect.value = role;
                });
            });
            
            // Set initial selection if form was submitted with errors
            const currentRole = roleSelect.value;
            if (currentRole) {
                const selectedOption = document.querySelector(`[data-role="${currentRole}"]`);
                if (selectedOption) {
                    selectedOption.classList.add('selected');
                }
            }
        });
    </script>
</body>
</html>
