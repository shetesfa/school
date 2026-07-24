<?php
session_start();

// Simple direct login test
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=school_management;charset=utf8mb4", "root", "");
        
        // Check if email or student ID
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            echo "<script>alert('Login SUCCESS! Role: " . $user['role'] . "');</script>";
            
            // Redirect based on role
            if ($user['role'] == 'superadmin') header("Location: superadmin_dashboard.php");
            elseif ($user['role'] == 'teacher') header("Location: teacher_dashboard.php");
            elseif ($user['role'] == 'parent') header("Location: parent_dashboard.php");
            exit();
        } else {
            $error = "Wrong username or password!";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Login Test</title>
    <style>
        body { font-family: Arial; padding: 50px; background: #4361ee; }
        .login-box { max-width: 400px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.2); }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 2px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 12px; background: #4361ee; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .credentials { background: #e8f5e9; padding: 15px; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Simple Login Test</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Email or Student ID" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login Test</button>
        </form>
        
        <div class="credentials">
            <h4>Try these:</h4>
            <p><strong>SuperAdmin:</strong> superadmin@school.com / superadmin123</p>
            <p><strong>Teacher:</strong> james@school.com / teacher123</p>
            <p><strong>Parent:</strong> STU001 / parent123</p>
        </div>
        
        <div style="text-align:center; margin-top:20px;">
            <a href="fix_passwords.php" style="color:#4361ee;">🔧 Fix Passwords First</a> | 
            <a href="login.php" style="color:#4361ee;">Go to Main Login</a>
        </div>
    </div>
</body>
</html>