<?php
session_start();
require 'db.php';

// Define faculties directly in PHP (no database query needed)
$faculties = [
    1 => 'Computer Science',
    2 => 'Engineering',
    3 => 'Business',
    4 => 'Medicine',
    5 => 'Arts',
    6 => 'Science'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Handle faculty selection (optional)
    $facultyId = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : NULL;
    
    // Validate faculty exists if provided
    if ($facultyId !== NULL && !array_key_exists($facultyId, $faculties)) {
        die("Invalid faculty selected");
    }

    $sql = "INSERT INTO users (username, email, password, faculty_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    try {
        $stmt->bind_param("sssi", $user, $email, $pass, $facultyId);
        $stmt->execute();
        echo "Signup successful! <a href='signin.php'>Login Now</a>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Tawasul</title>
    <style>
        body {
            background-color: #0d1117;
            color: #c9d1d9;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .form-container {
            background-color: #161b22;
            padding: 40px;
            border-radius: 10px;
            width: 300px;
            box-shadow: 0 0 10px #58a6ff;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="text"], 
        input[type="email"], 
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            background-color: #0d1117;
            border: 1px solid #30363d;
            border-radius: 5px;
            color: #c9d1d9;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #238636;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background-color: #2ea043;
        }
        .link {
            text-align: center;
            margin-top: 10px;
        }
        .link a {
            color: #58a6ff;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="form-container">
    <h2>Sign Up</h2>
    <form action="signup.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="faculty_id">
            <option value="">Select Faculty (Optional)</option>
            <?php foreach ($faculties as $id => $name): ?>
                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Create Account</button>
    </form>
    <div class="link">
        Already have an account? <a href="signin.php">Sign In</a>
    </div>
</div>
</body>
</html>