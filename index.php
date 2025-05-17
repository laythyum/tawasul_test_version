<?php
session_start();
require 'db.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Content validation function
function validateContent($content) {
    $content = trim(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    
    if (empty($content)) {
        return ['valid' => false, 'error' => "Post content cannot be empty"];
    }
    
    if (strlen($content) < 5) {
        return ['valid' => false, 'error' => "Post content is too short"];
    }
    
    $bannedWords = ['nigger', 'racist', 'hate']; // Add more as needed
    foreach ($bannedWords as $word) {
        if (stripos($content, $word) !== false) {
            return ['valid' => false, 'error' => "Post contains inappropriate language"];
        }
    }
    
    return ['valid' => true, 'content' => $content];
}

// Handle new post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $validation = validateContent($_POST['content']);
    if (!$validation['valid']) {
        $_SESSION['error'] = $validation['error'];
        header("Location: index.php");
        exit();
    }
    
    $content = $validation['content'];
    $user_id = (int)$_SESSION['user_id'];
    $faculty_id = $_SESSION['faculty_id'] ?? null;
    $media_url = null;

    // Handle media upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png', 
            'image/gif' => 'gif',
            'video/mp4' => 'mp4'
        ];
        
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $_FILES['media']['tmp_name']);
        
        // 5MB file size limit
        if ($_FILES['media']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = "File too large (max 5MB)";
            header("Location: index.php");
            exit();
        }

        if (array_key_exists($mimeType, $allowedTypes)) {
            $extension = $allowedTypes[$mimeType];
            $filename = 'post_' . $user_id . '_' . uniqid() . '.' . $extension;
            $media_url = $uploadDir . $filename;

            if (!move_uploaded_file($_FILES['media']['tmp_name'], $media_url)) {
                $_SESSION['error'] = "Failed to upload media file";
                header("Location: index.php");
                exit();
            }
            
            // Create thumbnail for images
            if (strpos($mimeType, 'image') === 0) {
                createThumbnail($media_url, $uploadDir . 'thumb_' . $filename, 300);
            }
        } else {
            $_SESSION['error'] = "Only JPG, PNG, GIF images and MP4 videos allowed";
            header("Location: index.php");
            exit();
        }
    }

    // Insert post
    try {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, faculty_id, content, media_url, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $user_id, $faculty_id, $content, $media_url);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Post created successfully";
        } else {
            throw new Exception("Database error: " . $stmt->error);
        }
    } catch (Exception $e) {
        // Clean up uploaded files if post creation failed
        if ($media_url && file_exists($media_url)) {
            unlink($media_url);
            $thumbPath = $uploadDir . 'thumb_' . basename($media_url);
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }
        $_SESSION['error'] = "Error creating post: " . $e->getMessage();
    } finally {
        $stmt->close();
        header("Location: index.php");
        exit();
    }
}

// Fetch posts
$posts = $conn->query("
    SELECT p.*, u.username, u.profile_pic,
    IFNULL((SELECT COUNT(*) FROM likes WHERE post_id = p.id), 0) AS like_count,
    IFNULL((SELECT COUNT(*) FROM comments WHERE post_id = p.id), 0) AS comment_count,
    EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = {$_SESSION['user_id']}) AS user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tawasul - Home</title>
    <style>
        :root {
            --bg-dark: #1d1f22;
            --sidebar-dark: #14171a;
            --text-light: #e1e8ed;
            --primary-blue: #1da1f2;
            --card-dark: #2f3336;
            --border-dark: #444d56;
            --like-red: #e0245e;
            --success-green: #17bf63;
            --error-red: #e74c3c;
        }
        
        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            background-color: var(--sidebar-dark);
            width: 250px;
            padding: 20px;
            position: fixed;
            height: 100%;
            border-right: 1px solid var(--border-dark);
        }
        
        .sidebar h2 {
            color: var(--primary-blue);
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sidebar nav ul {
            list-style: none;
            padding: 0;
        }
        
        .sidebar nav a {
            display: block;
            padding: 10px;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        
        .sidebar nav a:hover {
            background-color: rgba(255,255,255,0.05);
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        
        .post {
            background-color: var(--card-dark);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-dark);
        }
        
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .post-author-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            border: 2px solid var(--primary-blue);
        }
        
        .post-username {
            color: var(--primary-blue);
            font-weight: bold;
            text-decoration: none;
        }
        
        .post-username:hover {
            text-decoration: underline;
        }
        
        .post-time {
            color: #657786;
            font-size: 0.9em;
            margin-left: auto;
        }
        
        .post-content {
            margin: 10px 0;
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .post-media {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 10px;
            display: block;
        }
        
        .post-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border-dark);
        }
        
        .action-btn {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .action-btn:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .like-btn.liked {
            color: var(--like-red);
        }
        
        .comment-btn:hover {
            color: var(--primary-blue);
        }
        
        .report-btn {
            color: var(--error-red);
            margin-left: auto;
            cursor: pointer;
        }
        
        .report-btn:hover {
            text-decoration: underline;
        }
        
        .add-post-form {
            margin-bottom: 30px;
            background-color: var(--card-dark);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-dark);
        }
        
        textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border-dark);
            background-color: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            resize: vertical;
            font-family: inherit;
            font-size: 1rem;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(29, 161, 242, 0.3);
        }
        
        .file-upload {
            margin: 10px 0;
        }
        
        .file-upload input {
            color: var(--text-light);
        }
        
        .btn {
            background-color: var(--primary-blue);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background-color: #1991da;
        }
        
        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-red);
            border: 1px solid var(--error-red);
        }
        
        .alert-success {
            background-color: rgba(23, 191, 99, 0.1);
            color: var(--success-green);
            border: 1px solid var(--success-green);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a {
            color: var(--primary-blue);
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .pagination a:hover {
            background-color: rgba(29, 161, 242, 0.1);
        }
        
        .pagination .current {
            font-weight: bold;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <nav>
            <ul>
                <li><a href="profile.php?user_id=<?php echo $_SESSION['user_id']; ?>">My Profile</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="main-content">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form class="add-post-form" method="POST" enctype="multipart/form-data">
            <textarea name="content" placeholder="What's on your mind?" required></textarea>
            <div class="file-upload">
                <input type="file" name="media" accept="image/*,video/mp4">
                <small>Max file size: 5MB (JPG, PNG, GIF, MP4)</small>
            </div>
            <button type="submit" class="btn">Post</button>
        </form>

        <div class="posts-container">
            <?php if ($posts->num_rows > 0): ?>
                <?php while ($post = $posts->fetch_assoc()): ?>
                    <div class="post" id="post-<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <img src="uploads/profile_pics/<?php echo htmlspecialchars($post['profile_pic'] ?? 'default.png'); ?>" 
                                 class="post-author-pic"
                                 alt="<?php echo htmlspecialchars($post['username']); ?>">
                            <div>
                                <a href="profile.php?user_id=<?php echo $post['user_id']; ?>" class="post-username">
                                    <?php echo htmlspecialchars($post['username']); ?>
                                </a>
                                <span class="post-time">
                                    <?php echo date('M j, Y g:i a', strtotime($post['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                        
                        <?php if (!empty($post['media_url'])): ?>
                            <?php if (strpos($post['media_url'], '.mp4') !== false): ?>
                                <video controls class="post-media">
                                    <source src="<?php echo htmlspecialchars($post['media_url']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php else: ?>
                                <img src="<?php echo htmlspecialchars($post['media_url']); ?>" 
                                     class="post-media" 
                                     alt="Post media">
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="post-actions">
                            <button class="action-btn like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" 
                                    data-post-id="<?php echo $post['id']; ?>"
                                    onclick="toggleLike(this)">
                                ♥ <span class="like-count"><?php echo $post['like_count']; ?></span>
                            </button>
                            <button class="action-btn comment-btn"
                                    onclick="focusCommentBox(<?php echo $post['id']; ?>)">
                                💬 <?php echo $post['comment_count']; ?>
                            </button>
                            <span class="report-btn" 
                                  data-post-id="<?php echo $post['id']; ?>"
                                  onclick="reportPost(<?php echo $post['id']; ?>)">
                                Report
                            </span>
                        </div>
                        
                        <!-- Comment section would go here -->
                    </div>
                <?php endwhile; ?>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" <?php if ($i == $page) echo 'class="current"'; ?>>
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="post">
                    <p>No posts found. Be the first to post!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Like functionality
function toggleLike(postId, button) {
    const isLiked = button.classList.contains('liked');
    const action = isLiked ? 'unlike' : 'like';
    
    fetch('like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `post_id=${postId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('liked');
            button.querySelector('.like-count').textContent = data.like_count;
        }
    })
    .catch(error => console.error('Error:', error));
}

// Comment functionality
function addComment(postId) {
    const content = document.querySelector(`#comment-input-${postId}`).value;
    
    if (!content.trim()) return;
    
    fetch('comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `post_id=${postId}&content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add the new comment to the UI
            const commentSection = document.querySelector(`#comments-${postId}`);
            const newComment = document.createElement('div');
            newComment.className = 'comment';
            newComment.innerHTML = `
                <strong>${data.comment.username}</strong>
                <p>${data.comment.content}</p>
                <small>${new Date(data.comment.created_at).toLocaleString()}</small>
            `;
            commentSection.prepend(newComment);
            
            // Update comment count
            document.querySelector(`.comment-count[data-post="${postId}"]`).textContent = data.comment_count;
            
            // Clear input
            document.querySelector(`#comment-input-${postId}`).value = '';
        }
    })
    .catch(error => console.error('Error:', error));
}
    
    // Report functionality
    async function reportPost(postId) {
        if (confirm('Are you sure you want to report this post?')) {
            try {
                const response = await fetch('report_post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `post_id=${postId}`
                });
                
                if (response.ok) {
                    alert('Post reported. Thank you!');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
    }
    
    // Focus comment box
    function focusCommentBox(postId) {
        const postElement = document.getElementById(`post-${postId}`);
        const commentBox = postElement.querySelector('.comment-box');
        
        if (commentBox) {
            commentBox.style.display = 'block';
            commentBox.querySelector('textarea').focus();
        } else {
            // Load comment section via AJAX if not already loaded
            loadCommentSection(postId);
        }
    }
    
    // Load comment section (would be implemented separately)
    function loadCommentSection(postId) {
        console.log(`Loading comments for post ${postId}`);
        // Implement AJAX loading of comments here
    }
    </script>
</body>
</html>