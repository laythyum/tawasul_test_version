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

if (!isset($_POST['post_id']) || !isset($_POST['content'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Missing parameters']));
}

$post_id = (int)$_POST['post_id'];
$content = trim($_POST['content']);
$user_id = (int)$_SESSION['user_id'];

if (empty($content)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Comment cannot be empty']));
}

// Validate content length
if (strlen($content) > 500) {
    http_response_code(400);
    exit(json_encode(['error' => 'Comment too long (max 500 characters)']));
}

try {
    // Insert comment
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $post_id, $user_id, $content);
    $stmt->execute();
    
    // Get comment count
    $countStmt = $conn->prepare("SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?");
    $countStmt->bind_param("i", $post_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countData = $countResult->fetch_assoc();
    
    // Get username
    $userStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userData = $userResult->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'comment_count' => $countData['comment_count'],
        'username' => $userData['username']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>