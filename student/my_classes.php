<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];

// Get enrolled classes
$stmt = $pdo->prepare("
    SELECT c.*, u.name as teacher_name, 
           (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count,
           (SELECT COUNT(*) FROM materials WHERE class_id = c.id) as material_count,
           (SELECT COUNT(*) FROM assignments WHERE class_id = c.id AND status = 'active') as assignment_count
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE ce.student_id = ? AND c.status = 'active'
    ORDER BY c.created_at DESC
");
$stmt->execute([$user_id]);
$classes = $stmt->fetchAll();

$page_title = "My Classes";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Google Classroom</title>
    
    <!-- CSS Files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        /* Enhanced Google Classroom Mobile Style - Horizontal Cards */
        .classroom-header {
            background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 25px 25px;
            box-shadow: 0 8px 32px rgba(66, 133, 244, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .classroom-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .classroom-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .classroom-header .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .join-class-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .join-class-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        /* Enhanced Responsive Grid Container */
        .classes-container {
            padding: 0 1.5rem;
            margin-bottom: 3rem;
            width: 100%;
        }
        
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            padding: 1rem 0;
            width: 100%;
        }
        
        /* Enhanced Grid Class Cards */
        .class-card {
            width: 100%;
            height: 240px;
            background: white;
            border-radius: 20px;
            overflow: visible;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }
        
        .class-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 20px;
            z-index: 2;
        }
        
        .class-card:hover::before {
            opacity: 1;
        }
        
        .class-card-header {
            height: 120px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            padding: 1.5rem 1rem;
            text-align: center;
            overflow: visible;
        }
        
        .class-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            z-index: 1;
        }
        
        .class-card-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            z-index: 2;
        }
        
        .class-card-header-content {
            position: relative;
            z-index: 3;
            text-align: center;
            width: 100%;
            padding: 0.5rem;
        }
        
        .class-card-header .class-icon {
            font-size: 1.8rem;
            margin-bottom: 0.3rem;
            opacity: 0.9;
            transition: transform 0.3s ease;
            display: block;
        }
        
        .class-card:hover .class-icon {
            transform: scale(1.1);
        }
        
        .class-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            line-height: 1.2;
            word-wrap: break-word;
            overflow-wrap: break-word;
            display: block;
        }
        
        .class-section {
            font-size: 0.85rem;
            opacity: 0.8;
            font-weight: 500;
            display: block;
        }
        
        .class-card-body {
            padding: 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: white;
            position: relative;
            z-index: 3;
            border-radius: 0 0 20px 20px;
        }
        
        .class-info {
            margin-bottom: 0.8rem;
        }
        
        .class-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
            color: #555;
            font-weight: 500;
        }
        
        .class-info-item i {
            width: 16px;
            margin-right: 0.6rem;
            color: #4285f4;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .class-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .class-action-btn {
            flex: 1;
            padding: 0.6rem 0.8rem;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .class-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .class-action-btn:hover::before {
            left: 100%;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #4285f4, #3367d6);
            color: white;
            box-shadow: 0 4px 15px rgba(66, 133, 244, 0.3);
        }
        
        .btn-view:hover {
            background: linear-gradient(135deg, #3367d6, #2a56c6);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 133, 244, 0.4);
        }
        
        .btn-materials {
            background: linear-gradient(135deg, #34a853, #2d8f47);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 168, 83, 0.3);
        }
        
        .btn-materials:hover {
            background: linear-gradient(135deg, #2d8f47, #1e7e34);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 168, 83, 0.4);
        }
        
        /* Enhanced Gradient variations for different classes */
        .class-card:nth-child(1) .class-card-header {
            --gradient-start: #ff6b6b;
            --gradient-end: #ee5a24;
        }
        
        .class-card:nth-child(2) .class-card-header {
            --gradient-start: #4ecdc4;
            --gradient-end: #44a08d;
        }
        
        .class-card:nth-child(3) .class-card-header {
            --gradient-start: #45b7d1;
            --gradient-end: #96c93d;
        }
        
        .class-card:nth-child(4) .class-card-header {
            --gradient-start: #f093fb;
            --gradient-end: #f5576c;
        }
        
        .class-card:nth-child(5) .class-card-header {
            --gradient-start: #4facfe;
            --gradient-end: #00f2fe;
        }
        
        .class-card:nth-child(6) .class-card-header {
            --gradient-start: #43e97b;
            --gradient-end: #38f9d7;
        }
        
        .class-card:nth-child(7) .class-card-header {
            --gradient-start: #fa709a;
            --gradient-end: #fee140;
        }
        
        .class-card:nth-child(8) .class-card-header {
            --gradient-start: #a8edea;
            --gradient-end: #fed6e3;
        }
        
        /* Enhanced Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            margin: 2rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .empty-state h3 {
            color: #999;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .empty-state p {
            color: #777;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        /* Enhanced Success message styling */
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: none;
            border-radius: 15px;
            color: #155724;
            padding: 1.2rem 1.8rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.15);
            border-left: 4px solid #28a745;
        }
        
        /* Enhanced Floating Action Button */
        .fab {
            position: fixed;
            bottom: 2.5rem;
            right: 2.5rem;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4285f4, #3367d6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.6rem;
            box-shadow: 0 8px 25px rgba(66, 133, 244, 0.4);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
            text-decoration: none;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .fab:hover {
            background: linear-gradient(135deg, #3367d6, #2a56c6);
            color: white;
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 30px rgba(66, 133, 244, 0.5);
        }
        
        /* Enhanced Scroll indicator */
        .scroll-indicator {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 1rem;
            opacity: 0.7;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .scroll-indicator i {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateX(0); }
            40% { transform: translateX(-5px); }
            60% { transform: translateX(5px); }
        }
        
        /* Animation Enhancements */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .classroom-header {
            animation: slideInUp 0.8s ease-out;
        }
        
        .class-card {
            animation: fadeInScale 0.6s ease-out;
        }
        
        .class-card:nth-child(1) { animation-delay: 0.1s; }
        .class-card:nth-child(2) { animation-delay: 0.2s; }
        .class-card:nth-child(3) { animation-delay: 0.3s; }
        .class-card:nth-child(4) { animation-delay: 0.4s; }
        .class-card:nth-child(5) { animation-delay: 0.5s; }
        .class-card:nth-child(6) { animation-delay: 0.6s; }
        
        /* Enhanced Responsive adjustments */
        @media (max-width: 1200px) {
            .classes-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .classroom-header {
                padding: 1.5rem 0;
                border-radius: 0 0 20px 20px;
            }
            
            .classroom-header h1 {
                font-size: 1.8rem;
            }
            
            .classes-container {
                padding: 0 1rem;
            }
            
            .classes-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1rem;
            }
            
            .class-card {
                height: 280px;
            }
            
            .class-card-header {
                height: 140px;
                padding: 1.5rem 1rem;
            }
            
            .class-card-header .class-icon {
                font-size: 1.6rem;
            }
            
            .class-title {
                font-size: 0.95rem;
            }
            
            .class-card-body {
                padding: 1rem;
            }
            
            .class-info-item {
                font-size: 0.8rem;
                margin-bottom: 0.4rem;
            }
            
            .class-action-btn {
                padding: 0.6rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .fab {
                bottom: 2rem;
                right: 2rem;
                width: 55px;
                height: 55px;
                font-size: 1.4rem;
            }
        }
        
        @media (max-width: 576px) {
            .classroom-header h1 {
                font-size: 1.6rem;
            }
            
            .classes-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .class-card {
                height: 260px;
            }
            
            .class-card-header {
                height: 120px;
                padding: 1rem 0.8rem;
            }
            
            .class-card-header .class-icon {
                font-size: 1.4rem;
            }
            
            .class-title {
                font-size: 0.9rem;
            }
            
            .class-card-body {
                padding: 0.8rem;
            }
            
            .class-info-item {
                font-size: 0.75rem;
                margin-bottom: 0.3rem;
            }
            
            .class-action-btn {
                padding: 0.5rem 0.6rem;
                font-size: 0.75rem;
            }
        }
        
        /* Grid layout optimization */
        .content-area {
            overflow-x: hidden;
        }
        
        .classes-grid {
            scroll-behavior: smooth;
        }
        
        /* Loading animation for cards */
        .class-card.loading {
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
                <!-- Google Classroom Style Header -->
                <div class="classroom-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1><i class="fas fa-chalkboard me-2"></i>My Classes</h1>
                                <p class="subtitle"><?php echo count($classes); ?> class<?php echo count($classes) !== 1 ? 'es' : ''; ?> enrolled</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="join_class.php" class="btn join-class-btn">
                                    <i class="fas fa-plus me-1"></i>Join Class
                                </a>
                            </div>
                        </div>
                    </div>
            </div>

            <?php displayFlashMessage(); ?>

                <!-- Classes Grid Layout -->
                <div class="classes-container">
                    <?php if (empty($classes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-chalkboard"></i>
                            <h3>No classes yet</h3>
                            <p>Join your first class to get started with your learning journey.</p>
                    <a href="join_class.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Join Your First Class
                    </a>
                </div>
            <?php else: ?>
                        <div class="classes-grid">
                            <?php foreach ($classes as $index => $class): ?>
                                <div class="class-card" onclick="window.location.href='class_details.php?id=<?php echo $class['id']; ?>'">
                                    <div class="class-card-header">
                                        <div class="class-card-header-content">
                                            <div class="class-icon">
                                                <i class="fas fa-<?php echo getClassIcon($class['subject']); ?>"></i>
                                </div>
                                            <div class="class-title"><?php echo htmlspecialchars($class['name']); ?></div>
                                            <div class="class-section">Section <?php echo htmlspecialchars($class['section']); ?></div>
                                            </div>
                                        </div>
                                    
                                    <div class="class-card-body">
                                        <div class="class-info">
                                            <div class="class-info-item">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($class['teacher_name']); ?></span>
                                            </div>
                                            <div class="class-info-item">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo $class['student_count']; ?> students</span>
                                        </div>
                                            <div class="class-info-item">
                                                <i class="fas fa-key"></i>
                                                <span><?php echo $class['class_code']; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="class-actions">
                                            <a href="class_details.php?id=<?php echo $class['id']; ?>" class="class-action-btn btn-view" onclick="event.stopPropagation()">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                            <a href="materials.php?class_id=<?php echo $class['id']; ?>" class="class-action-btn btn-materials" onclick="event.stopPropagation()">
                                                <i class="fas fa-file-alt me-1"></i>Materials
                                            </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                        <?php if (count($classes) > 6): ?>
                            <div class="scroll-indicator">
                                <i class="fas fa-th me-1"></i><?php echo count($classes); ?> classes available
                            </div>
                        <?php endif; ?>
                                <?php endif; ?>
                    </div>

                <!-- Floating Action Button -->
                <a href="join_class.php" class="fab">
                    <i class="fas fa-plus"></i>
                                            </a>
                                        </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    
    <script>
        // Enhanced grid layout functionality
        document.addEventListener('DOMContentLoaded', function() {
            const gridContainer = document.querySelector('.classes-grid');
            if (gridContainer) {
                // Add smooth animations for cards
                const cards = gridContainer.querySelectorAll('.class-card');
                cards.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.1}s`;
                });
                
                // Add hover effects
                cards.forEach(card => {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-8px)';
                        this.style.boxShadow = '0 16px 40px rgba(0, 0, 0, 0.15)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 8px 32px rgba(0, 0, 0, 0.12)';
                    });
                });
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to get appropriate icon for subject
function getClassIcon($subject) {
    $subject = strtolower($subject);
    
    if (strpos($subject, 'math') !== false) return 'calculator';
    if (strpos($subject, 'english') !== false || strpos($subject, 'literature') !== false) return 'book';
    if (strpos($subject, 'science') !== false) return 'flask';
    if (strpos($subject, 'history') !== false || strpos($subject, 'social') !== false) return 'landmark';
    if (strpos($subject, 'art') !== false) return 'palette';
    if (strpos($subject, 'music') !== false) return 'music';
    if (strpos($subject, 'computer') !== false || strpos($subject, 'programming') !== false) return 'laptop-code';
    if (strpos($subject, 'physics') !== false) return 'atom';
    if (strpos($subject, 'chemistry') !== false) return 'flask-vial';
    if (strpos($subject, 'biology') !== false) return 'dna';
    if (strpos($subject, 'geography') !== false) return 'globe';
    if (strpos($subject, 'language') !== false) return 'language';
    if (strpos($subject, 'physical') !== false || strpos($subject, 'pe') !== false) return 'running';
    if (strpos($subject, 'iot') !== false) return 'wifi';
    if (strpos($subject, 'ai') !== false || strpos($subject, 'machine') !== false) return 'brain';
    if (strpos($subject, 'cis') !== false) return 'database';
    
    return 'chalkboard';
}
?>
