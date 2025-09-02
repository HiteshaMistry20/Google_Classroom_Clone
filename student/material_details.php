<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();
requireRole('student');

$user_id = $_SESSION['user_id'];
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($material_id <= 0) {
    setFlashMessage('error', 'Invalid material ID.');
    header('Location: materials.php');
    exit();
}

// Get material details and verify student has access
$stmt = $pdo->prepare("
    SELECT m.*, c.name as class_name, c.section, u.name as teacher_name, u.email as teacher_email
    FROM materials m
    JOIN classes c ON m.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    JOIN users u ON c.teacher_id = u.id
    WHERE m.id = ? AND ce.student_id = ? AND c.status = 'active'
");
$stmt->execute([$material_id, $user_id]);
$material = $stmt->fetch();

if (!$material) {
    setFlashMessage('error', 'Material not found or access denied.');
    header('Location: materials.php');
    exit();
}

// Get file information if exists
$file_info = null;
if ($material['file_path'] && $material['file_path'] !== 'Array') {
    // Handle the case where file_path might be corrupted (stored as "Array")
    if ($material['file_path'] === 'Array') {
        // Try to find the actual video file in the uploads directory
        $uploads_dir = '../uploads/materials/';
        if (is_dir($uploads_dir)) {
            $files = scandir($uploads_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'mp4') {
                    $file_path = $uploads_dir . $file;
                    $display_path = 'uploads/materials/' . $file;
                    break;
                }
            }
        }
    } else {
        // Check if the file path is already a full path or just filename
        if (strpos($material['file_path'], 'uploads/materials/') === 0) {
            // It's already a full path
            $file_path = '../' . $material['file_path'];
            $display_path = $material['file_path'];
        } else {
            // It's just a filename
            $file_path = '../uploads/materials/' . $material['file_path'];
            $display_path = $material['file_path'];
        }
    }
    
    if (isset($file_path) && file_exists($file_path)) {
        $file_info = [
            'name' => basename($file_path),
            'size' => filesize($file_path),
            'extension' => pathinfo($file_path, PATHINFO_EXTENSION),
            'path' => $display_path
        ];
    }
}

// Fallback: If no file info found but this is a video material, try to find any video file
if (!$file_info && $material['type'] === 'video') {
    $uploads_dir = '../uploads/materials/';
    if (is_dir($uploads_dir)) {
        $files = scandir($uploads_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
                $file_path = $uploads_dir . $file;
                $display_path = 'uploads/materials/' . $file;
                
                if (file_exists($file_path)) {
                    $file_info = [
                        'name' => basename($file_path),
                        'size' => filesize($file_path),
                        'extension' => pathinfo($file_path, PATHINFO_EXTENSION),
                        'path' => $display_path
                    ];
                    break;
                }
            }
        }
    }
}

$page_title = "Material Details - " . htmlspecialchars($material['title']);
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-book"></i> Material Details
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="materials.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Materials
                    </a>
                </div>
            </div>

            <?php displayFlashMessage(); ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Material Content -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title mb-0"><?= htmlspecialchars($material['title']) ?></h3>
                        </div>
                        <div class="card-body">
                            <?php if ($material['description']): ?>
                                <div class="mb-4">
                                    <h5>Description</h5>
                                    <p class="text-muted"><?= nl2br(htmlspecialchars($material['description'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($file_info): ?>
                                <?php if ($material['type'] === 'video' && in_array(strtolower($file_info['extension']), ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])): ?>
                                    <!-- Video Player -->
                                    <div class="mb-4">
                                        <h5>Video Content</h5>
                                                                                 <div class="ratio ratio-16x9">
                                             <video controls class="embed-responsive-item" style="width: 100%; height: 100%;">
                                                 <source src="../<?= $file_info['path'] ?>" type="video/<?= $file_info['extension'] ?>">
                                                 Your browser does not support the video tag.
                                             </video>
                                         </div>
                                        <div class="mt-3">
                                            <div class="alert alert-info">
                                                <div class="d-flex align-items-center">
                                                    <i class="<?= getFileIcon($file_info['extension']) ?> fa-2x me-3"></i>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1"><?= htmlspecialchars($file_info['name']) ?></h6>
                                                        <small class="text-muted">
                                                            Size: <?= formatFileSize($file_info['size']) ?> | 
                                                            Type: <?= strtoupper($file_info['extension']) ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <a href="../<?= $file_info['path'] ?>" 
                                                           class="btn btn-primary btn-sm" 
                                                           target="_blank"
                                                           download="<?= $file_info['name'] ?>">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Regular File -->
                                    <div class="mb-4">
                                        <h5>Attached File</h5>
                                        <div class="alert alert-info">
                                            <div class="d-flex align-items-center">
                                                <i class="<?= getFileIcon($file_info['extension']) ?> fa-2x me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?= htmlspecialchars($file_info['name']) ?></h6>
                                                    <small class="text-muted">
                                                        Size: <?= formatFileSize($file_info['size']) ?> | 
                                                        Type: <?= strtoupper($file_info['extension']) ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <a href="../<?= $file_info['path'] ?>" 
                                                       class="btn btn-primary btn-sm" 
                                                       target="_blank"
                                                       download="<?= $file_info['name'] ?>">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($material['type'] === 'link' && $material['link_url']): ?>
                                <div class="mb-4">
                                    <h5>External Link</h5>
                                    <?php 
                                    $video_url = $material['link_url'];
                                    $is_youtube = preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $youtube_id);
                                    $is_vimeo = preg_match('/vimeo\.com\/([0-9]+)/', $video_url, $vimeo_id);
                                    ?>
                                    
                                    <?php if ($is_youtube): ?>
                                        <!-- YouTube Video Embed -->
                                        <div class="ratio ratio-16x9 mb-3">
                                            <iframe src="https://www.youtube.com/embed/<?= $youtube_id[1] ?>" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                    allowfullscreen
                                                    style="width: 100%; height: 100%;">
                                            </iframe>
                                        </div>
                                    <?php elseif ($is_vimeo): ?>
                                        <!-- Vimeo Video Embed -->
                                        <div class="ratio ratio-16x9 mb-3">
                                            <iframe src="https://player.vimeo.com/video/<?= $vimeo_id[1] ?>" 
                                                    frameborder="0" 
                                                    allow="autoplay; fullscreen; picture-in-picture" 
                                                    allowfullscreen
                                                    style="width: 100%; height: 100%;">
                                            </iframe>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-external-link-alt"></i>
                                        <a href="<?= htmlspecialchars($material['link_url']) ?>" 
                                           target="_blank" 
                                           class="alert-link">
                                            <?= htmlspecialchars($material['link_url']) ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Material Information -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Material Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Class:</strong><br>
                                <span class="text-muted">
                                    <?= htmlspecialchars($material['class_name']) ?> 
                                    (<?= htmlspecialchars($material['section']) ?>)
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>Teacher:</strong><br>
                                <span class="text-muted">
                                    <?= htmlspecialchars($material['teacher_name']) ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>Type:</strong><br>
                                <span class="badge bg-<?= $material['type'] === 'document' ? 'primary' : ($material['type'] === 'video' ? 'danger' : 'success') ?>">
                                    <?= ucfirst($material['type']) ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>Posted:</strong><br>
                                <span class="text-muted">
                                    <?= formatDate($material['created_at']) ?>
                                </span>
                            </div>

                            <?php if ($file_info): ?>
                                <div class="mb-3">
                                    <strong>File Size:</strong><br>
                                    <span class="text-muted">
                                        <?= formatFileSize($file_info['size']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card shadow-sm mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($file_info): ?>
                                    <a href="../<?= $file_info['path'] ?>" 
                                       class="btn btn-primary"
                                       target="_blank"
                                       download="<?= $file_info['name'] ?>">
                                        <i class="fas fa-download"></i> Download File
                                    </a>
                                <?php endif; ?>

                                <a href="materials.php?class_id=<?= $material['class_id'] ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-list"></i> View All Materials
                                </a>

                                <a href="class_details.php?id=<?= $material['class_id'] ?>" 
                                   class="btn btn-outline-info">
                                    <i class="fas fa-chalkboard"></i> Go to Class
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.video-container {
    position: relative;
    width: 100%;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
}

.video-container video {
    width: 100%;
    height: auto;
    display: block;
}

.ratio-16x9 {
    aspect-ratio: 16 / 9;
}

.embed-responsive-item {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 0;
}

@media (max-width: 768px) {
    .video-container {
        margin: 0 -15px;
        border-radius: 0;
    }
}
</style>

<script>
// Add any additional JavaScript functionality here
document.addEventListener('DOMContentLoaded', function() {
    // Auto-download functionality (optional)
    const downloadBtn = document.querySelector('a[download]');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            // Track download if needed
            console.log('Material downloaded:', '<?= $material['title'] ?>');
        });
    }
    
    // Video player enhancement
    const videoPlayer = document.querySelector('video');
    if (videoPlayer) {
        // Add custom controls or tracking if needed
        videoPlayer.addEventListener('play', function() {
            console.log('Video started playing:', '<?= $material['title'] ?>');
        });
        
        videoPlayer.addEventListener('ended', function() {
            console.log('Video finished playing:', '<?= $material['title'] ?>');
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
