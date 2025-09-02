<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($material_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit();
}

try {
    if ($user_role === 'teacher') {
        // Teacher can only access their own materials
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as class_name 
            FROM materials m 
            JOIN classes c ON m.class_id = c.id 
            WHERE m.id = ? AND m.teacher_id = ?
        ");
        $stmt->execute([$material_id, $user_id]);
    } elseif ($user_role === 'admin') {
        // Admin can access all materials
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as class_name 
            FROM materials m 
            JOIN classes c ON m.class_id = c.id 
            WHERE m.id = ?
        ");
        $stmt->execute([$material_id]);
    } else {
        // Students cannot edit materials
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $material = $stmt->fetch();
    
    if ($material) {
        echo json_encode([
            'success' => true,
            'material' => $material
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Material not found']);
    }
    
} catch (Exception $e) {
    error_log("Get Material API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
