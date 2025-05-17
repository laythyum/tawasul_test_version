<?php
session_start();
require 'db.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}


$profile_user_id = (int)($_GET['user_id'] ?? $_SESSION['user_id']);


$stmt = $conn->prepare("SELECT id, username, profile_pic, status, followers_count, following_count FROM users WHERE id = ?");
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$profile_user = $stmt->get_result()->fetch_assoc() or die("User not found");


$is_current_user = ($_SESSION['user_id'] == $profile_user_id);


$stmt = $conn->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$posts = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile_user['username']) ?>'s Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .profile-header {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1da1f2;
        }
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 15px 0;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-primary {
            background: #1da1f2;
            color: white;
        }
        .post {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .post-time {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="profile-header">
        <img src="uploads/profile_pics/<?= htmlspecialchars($profile_user['profile_pic'] ?? 'default.png') ?>" 
             class="profile-pic" 
             alt="Profile picture"
             onerror="this.src='uploads/profile_pics/default.png'">
        
        <h1><?= htmlspecialchars($profile_user['username']) ?></h1>
        
        <?php if (!empty($profile_user['status'])): ?>
            <p><?= htmlspecialchars($profile_user['status']) ?></p>
        <?php endif; ?>
        
        <div class="profile-stats">
            <div><strong><?= $profile_user['followers_count'] ?></strong> Followers</div>
            <div><strong><?= $profile_user['following_count'] ?></strong> Following</div>
        </div>
        
        <?php if ($is_current_user): ?>
            <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
        <?php else: ?>
            <button class="btn btn-primary">Follow</button>
        <?php endif; ?>
    </div>
    
    <h2><?= $is_current_user ? 'Your Posts' : 'Posts' ?></h2>
    
    <?php if ($posts->num_rows > 0): ?>
        <?php while ($post = $posts->fetch_assoc()): ?>
            <div class="post">
                <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                <p class="post-time">Posted on <?= date('M j, Y g:i a', strtotime($post['created_at'])) ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p><?= $is_current_user ? 'You have' : 'This user has' ?> no posts yet.</p>
    <?php endif; ?>
</body>
</html>