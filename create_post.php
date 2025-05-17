<?php
session_start();
require 'db.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to create posts";
    header('Location: login.php');
    exit();
}

// Content validation function
function validateContent($content) {
    // Trim and sanitize
    $content = trim(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    
    // Check for empty content
    if (empty($content)) {
        return ['valid' => false, 'error' => "Post content cannot be empty"];
    }
    
    // Check for minimum length
    if (strlen($content) < 5) {
        return ['valid' => false, 'error' => "Post content is too short"];
    }
    
    // Check for banned words
    $bannedWords = ['nigger', 'racist', 'hate', 'slur']; // Add more as needed
    foreach ($bannedWords as $word) {
        if (stripos($content, $word) !== false) {
            return ['valid' => false, 'error' => "Post contains inappropriate language"];
        }
    }
    
    return ['valid' => true, 'content' => $content];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['postContent'])) {
    $validation = validateContent($_POST['postContent']);
    if (!$validation['valid']) {
        $_SESSION['error'] = $validation['error'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $content = $validation['content'];
    $userId = (int)$_SESSION['user_id'];
    $facultyId = isset($_SESSION['faculty_id']) ? (int)$_SESSION['faculty_id'] : null;

    // Handle media upload
    $mediaUrl = null;
    if (isset($_FILES['postImage']) && $_FILES['postImage']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4'
        ];
        
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES['postImage']['tmp_name']);
        
        // Check file size (max 5MB)
        if ($_FILES['postImage']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = "File too large (max 5MB)";
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }

        if (array_key_exists($mimeType, $allowedTypes)) {
            $extension = $allowedTypes[$mimeType];
            $filename = 'post_' . $userId . '_' . uniqid() . '.' . $extension;
            $mediaUrl = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['postImage']['tmp_name'], $mediaUrl)) {
                $_SESSION['error'] = "Failed to upload media";
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit();
            }
            
            // For images, create a thumbnail
            if (strpos($mimeType, 'image') === 0) {
                createThumbnail($mediaUrl, $uploadDir . 'thumb_' . $filename, 300);
            }
        } else {
            $_SESSION['error'] = "Only JPG, PNG, GIF images and MP4 videos are allowed";
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
    }

    try {
        // Insert post with prepared statement
        $stmt = $conn->prepare("INSERT INTO posts (user_id, faculty_id, content, media_url, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $userId, $facultyId, $content, $mediaUrl);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Post created successfully!";
            header('Location: index.php');
            exit();
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }
    } catch (Exception $e) {
        // Clean up uploaded file if post creation failed
        if ($mediaUrl && file_exists($mediaUrl)) {
            unlink($mediaUrl);
            if (file_exists($uploadDir . 'thumb_' . basename($mediaUrl))) {
                unlink($uploadDir . 'thumb_' . basename($mediaUrl));
            }
        }
        $_SESSION['error'] = "Failed to create post: " . $e->getMessage();
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    } finally {
        if (isset($stmt)) $stmt->close();
    }
} else {
    // Not a POST request or missing postContent
    $_SESSION['error'] = "Invalid request";
    header('Location: index.php');
    exit();
}

/**
 * Create a thumbnail for uploaded images
 */
function createThumbnail($sourcePath, $destPath, $width) {
    $info = getimagesize($sourcePath);
    
    switch ($info['mime']) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    $origWidth = imagesx($source);
    $origHeight = imagesy($source);
    $height = (int)(($width / $origWidth) * $origHeight);
    
    $thumb = imagecreatetruecolor($width, $height);
    
    // Preserve transparency for PNG and GIF
    if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
    
    switch ($info['mime']) {
        case 'image/jpeg':
            imagejpeg($thumb, $destPath, 85);
            break;
        case 'image/png':
            imagepng($thumb, $destPath, 8);
            break;
        case 'image/gif':
            imagegif($thumb, $destPath);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($thumb);
    
    return true;
}