<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notification_system.php';

requireRole('student');

$user_id = $_SESSION['user_id'];

// Handle class joining
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_code = strtoupper(trim($_POST['class_code']));
    
    if (empty($class_code)) {
        setFlashMessage('error', 'Please enter a class code.');
    } elseif (!validateClassCode($class_code)) {
        setFlashMessage('error', 'Invalid class code format. Class codes are 6 characters long.');
    } else {
        // Check if class exists and is active
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE class_code = ? AND status = 'active'");
        $stmt->execute([$class_code]);
        $class = $stmt->fetch();
        
        if (!$class) {
            setFlashMessage('error', 'Class not found or inactive.');
        } else {
            // Check if student is already enrolled
            $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND student_id = ?");
            $stmt->execute([$class['id'], $user_id]);
            
            if ($stmt->fetch()) {
                setFlashMessage('error', 'You are already enrolled in this class.');
            } else {
                // Enroll student
                $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id) VALUES (?, ?)");
                
                if ($stmt->execute([$class['id'], $user_id])) {
                    // Send comprehensive notifications for student joining class
                    notifyStudentJoinedClass($pdo, $class['id'], $user_id);
                    
                    setFlashMessage('success', 'Successfully joined class: ' . $class['name']);
                    header('Location: my_classes.php');
                    exit();
                } else {
                    setFlashMessage('error', 'Failed to join class. Please try again.');
                }
            }
        }
    }
}

// Get recently joined classes
$stmt = $pdo->prepare("
    SELECT c.*, u.name as teacher_name, ce.enrolled_at
    FROM class_enrollments ce 
    JOIN classes c ON ce.class_id = c.id 
    JOIN users u ON c.teacher_id = u.id
    WHERE ce.student_id = ? AND c.status = 'active' 
    ORDER BY ce.enrolled_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_classes = $stmt->fetchAll();

$page_title = "Join Class";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Join Class</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="my_classes.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-1"></i>My Classes
                    </a>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <div class="row">
                <!-- Join Class Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Join a Class</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="class_code" class="form-label">Class Code</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-lg text-center" 
                                               id="class_code" name="class_code" 
                                               placeholder="Enter 6-digit code" 
                                               maxlength="6" required 
                                               style="font-size: 1.5rem; font-weight: bold; letter-spacing: 0.5rem;">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-sign-in-alt me-1"></i>Join Class
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Enter the 6-digit class code provided by your teacher
                                    </div>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <div class="text-center">
                                <h6 class="text-muted mb-3">Or scan QR code</h6>
                                <div class="qr-scanner-container">
                                    <div id="qr-reader" style="width: 100%; max-width: 300px; margin: 0 auto;"></div>
                                    <small class="text-muted">Point your camera at the class QR code</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="col-lg-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">How to Join a Class</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-keyboard fa-3x text-primary mb-3"></i>
                                        <h6>Enter Class Code</h6>
                                        <p class="text-muted small">
                                            Ask your teacher for the 6-digit class code and enter it above
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="text-center">
                                        <i class="fas fa-qrcode fa-3x text-success mb-3"></i>
                                        <h6>Scan QR Code</h6>
                                        <p class="text-muted small">
                                            Use your camera to scan the QR code displayed by your teacher
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6>What happens after joining?</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    You'll have access to class materials and assignments
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Your teacher will be notified of your enrollment
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    You can start submitting assignments and viewing grades
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    You'll receive notifications for new content and announcements
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recently Joined Classes -->
            <?php if (!empty($recent_classes)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Recently Joined Classes</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($recent_classes as $class): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card class-card h-100 border-0 shadow-sm">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h6>
                                                    <p class="text-muted mb-2">
                                                        <?php echo htmlspecialchars($class['subject']); ?> • 
                                                        <?php echo htmlspecialchars($class['teacher_name']); ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="badge bg-primary"><?php echo $class['class_code']; ?></span>
                                                        <small class="text-muted">
                                                            Joined <?php echo getTimeAgo($class['enrolled_at']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-transparent">
                                                    <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm w-100">
                                                        <i class="fas fa-eye me-1"></i>View Class
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    // QR Code Scanner
    function onScanSuccess(decodedText, decodedResult) {
        // Extract class code from QR code data
        const classCode = decodedText.trim().toUpperCase();
        
        // Validate class code format
        if (/^[A-Z0-9]{6}$/.test(classCode)) {
            document.getElementById('class_code').value = classCode;
            document.querySelector('form').submit();
        } else {
            alert('Invalid QR code. Please scan a valid class QR code.');
        }
    }

    function onScanFailure(error) {
        // Handle scan failure, usually better to ignore and keep scanning
        console.warn(`QR scan error = ${error}`);
    }

    // Initialize QR scanner
    let html5QrcodeScanner = new Html5QrcodeScanner(
        "qr-reader",
        { fps: 10, qrbox: {width: 250, height: 250} },
        /* verbose= */ false);
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);

    // Auto-focus on class code input
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('class_code').focus();
    });

    // Auto-format class code input
    document.getElementById('class_code').addEventListener('input', function(e) {
        let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        e.target.value = value;
    });
</script>

<?php include '../includes/footer.php'; ?>
