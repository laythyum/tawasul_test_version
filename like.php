<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

if (!isset($_POST['post_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing parameters']));
}

$post_id = (int)$_POST['post_id'];
$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] === 'like' ? 'like' : 'unlike';

try {
    if ($action === 'like') {
        // Check if already liked
        $checkStmt = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $checkStmt->bind_param("ii", $post_id, $user_id);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $post_id, $user_id);
            $stmt->execute();
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
    }
    
    // Get updated like count
    $countStmt = $conn->prepare("SELECT COUNT(*) AS like_count FROM likes WHERE post_id = ?");
    $countStmt->bind_param("i", $post_id);
    $countStmt->execute();
    $result = $countStmt->get_result();
    $data = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'like_count' => $data['like_count']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>