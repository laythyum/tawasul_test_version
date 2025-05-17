<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1d1f22;
            color: #e1e8ed;
            padding: 20px;
        }
        .profile-form {
            max-width: 600px;
            margin: 0 auto;
            background: #2f3336;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #1da1f2;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            background: #333739;
            border: 1px solid #444d56;
            color: #e1e8ed;
            border-radius: 4px;
        }
        .email-display {
            padding: 10px;
            background: #333739;
            border: 1px solid #444d56;
            border-radius: 4px;
            color: #888;
        }
        .profile-pic-container {
            text-align: center;
            margin: 20px 0;
        }
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1da1f2;
        }
        .btn {
            background: #1da1f2;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        .btn:hover {
            background: #1991da;
        }
        .error {
            color: #ff6b6b;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(255,0,0,0.1);
            border-radius: 4px;
        }
        .success {
            color: #4caf50;
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(0,255,0,0.1);
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="profile-form">
        <h2>Edit Profile</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); 
            unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); 
            unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="profile-pic-container">
            <img src="uploads/profile_pics/<?php echo htmlspecialchars($user['profile_pic']); ?>" 
                 class="profile-pic"
                 onerror="this.src='uploads/profile_pics/default.png'">
        </div>
        
        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Profile Picture:</label>
                <input type="file" name="profile_pic" accept="image/*">
                <small>Leave blank to keep current picture</small>
            </div>
            
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <div class="email-display"><?php echo htmlspecialchars($user['email']); ?></div>
                <small>Email cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Bio/Status:</label>
                <textarea name="status" rows="3" placeholder="Tell others about yourself"><?php 
                    echo htmlspecialchars($user['status']); 
                ?></textarea>
            </div>
            
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>
</body>
</html>