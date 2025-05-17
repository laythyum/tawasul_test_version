<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['content'])) {
        // Create new post
        $content = trim($_POST['content']);
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $content);
        $stmt->execute();
        
        header("Location: post.php");
        exit();
    }
    elseif (isset($_POST['like_action'], $_POST['post_id'])) {
        // Handle like/unlike
        $post_id = (int)$_POST['post_id'];
        $user_id = (int)$_SESSION['user_id'];
        
        if ($_POST['like_action'] === 'like') {
            $stmt = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
        } else {
            $stmt = $conn->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?");
        }
        
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        
        // Return updated like count
        $like_count = $conn->query("SELECT COUNT(*) FROM likes WHERE post_id = $post_id")->fetch_row()[0];
        echo $like_count;
        exit();
    }
}

// Fetch all posts
$posts = $conn->query("
    SELECT p.*, u.username, 
    (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS like_count,
    EXISTS(SELECT 1 FROM likes WHERE post_id = p.id AND user_id = {$_SESSION['user_id']}) AS user_liked
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Posts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .post-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .post-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 10px;
        }
        .post {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .post-actions {
            margin-top: 10px;
        }
        .like-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #657786;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .like-btn:hover {
            background: rgba(0,0,0,0.05);
        }
        .like-btn.liked {
            color: #e0245e;
        }
    </style>
</head>
<body>
    <h1>Create Post</h1>
    
    <form class="post-form" method="POST">
        <textarea name="content" placeholder="What's on your mind?" required></textarea>
        <button type="submit">Post</button>
    </form>
    
    <h2>Recent Posts</h2>
    
    <?php while ($post = $posts->fetch_assoc()): ?>
        <div class="post">
            <strong><?php echo htmlspecialchars($post['username']); ?></strong>
            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
            <div class="post-actions">
                <button class="like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" 
                        data-post-id="<?php echo $post['id']; ?>"
                        onclick="toggleLike(this)">
                    ♥ <?php echo $post['like_count']; ?>
                </button>
            </div>
        </div>
    <?php endwhile; ?>
    
    <script>
    function toggleLike(button) {
        const postId = button.dataset.postId;
        const isLiked = button.classList.contains('liked');
        const action = isLiked ? 'unlike' : 'like';
        
        fetch('post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `post_id=${postId}&like_action=${action}`
        })
        .then(response => response.text())
        .then(count => {
            button.classList.toggle('liked');
            button.innerHTML = '♥ ' + count;
        })
        .catch(error => console.error('Error:', error));
    }
    </script>
</body>
</html>