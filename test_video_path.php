<?php
// Test video file path
$test_path = 'uploads/materials/689d5d8cddac0_123.mp4';
$full_path = '../' . $test_path;

echo "<h2>Testing Video File Path</h2>";
echo "<p>Test path: $test_path</p>";
echo "<p>Full path: $full_path</p>";
echo "<p>File exists: " . (file_exists($full_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($full_path)) {
    echo "<p>File size: " . filesize($full_path) . " bytes</p>";
    echo "<p>File permissions: " . substr(sprintf('%o', fileperms($full_path)), -4) . "</p>";
    
    // Test if file is readable
    echo "<p>File readable: " . (is_readable($full_path) ? 'YES' : 'NO') . "</p>";
    
    // Test direct access via URL
    echo "<h3>Direct File Access Test</h3>";
    echo "<p>Try accessing: <a href='../$test_path' target='_blank'>../$test_path</a></p>";
    
    // Test video element
    echo "<h3>Video Player Test</h3>";
    echo "<video controls width='400'>";
    echo "<source src='../$test_path' type='video/mp4'>";
    echo "Your browser does not support the video tag.";
    echo "</video>";
} else {
    echo "<p style='color: red;'>❌ File not found!</p>";
    
    // List directory contents
    echo "<h3>Directory Contents:</h3>";
    $dir = '../uploads/materials/';
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                echo "- $file<br>";
            }
        }
    } else {
        echo "<p>Directory not found: $dir</p>";
    }
}
?>
