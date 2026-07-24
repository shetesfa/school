<?php
// fix_passwords.php - Fix all passwords
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Passwords</title>
    <style>
        body { font-family: Arial; padding: 50px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #f5c6cb; }
        .btn { background: #4361ee; color: white; padding: 12px 30px; border-radius: 8px; border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #3a0ca3; }
        pre { background: #333; color: #fff; padding: 15px; border-radius: 8px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Password Issues</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            try {
                // Connect to database
                $pdo = new PDO("mysql:host=localhost;dbname=school_management;charset=utf8mb4", "root", "");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo "<div class='success'>✅ Connected to database</div>";
                
                // Fix SuperAdmin password
                $superadmin_password = password_hash('superadmin123', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'superadmin@school.com'");
                $stmt->execute([$superadmin_password]);
                echo "<div class='success'>✅ Fixed SuperAdmin password: superadmin@school.com / superadmin123</div>";
                
                // Fix teacher passwords
                $teachers = [
                    'james@school.com' => 'teacher123',
                    'sarah@school.com' => 'teacher123', 
                    'robert@school.com' => 'teacher123',
                    'emily@school.com' => 'teacher123',
                    'michael@school.com' => 'teacher123'
                ];
                
                foreach ($teachers as $email => $password) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $stmt->execute([$hashed, $email]);
                    echo "<div class='success'>✅ Fixed teacher: $email / $password</div>";
                }
                
                // Fix parent passwords (STU001, STU002, STU003)
                for ($i = 1; $i <= 3; $i++) {
                    $student_id = 'STU' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $hashed = password_hash('parent123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'parent'");
                    $stmt->execute([$hashed, $student_id]);
                    echo "<div class='success'>✅ Fixed parent: $student_id / parent123</div>";
                }
                
                // Create parent accounts if they don't exist
                for ($i = 1; $i <= 3; $i++) {
                    $student_id = 'STU' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    
                    // Check if parent exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
                    $stmt->execute([$student_id]);
                    
                    if ($stmt->rowCount() == 0) {
                        $hashed = password_hash('parent123', PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'parent')");
                        $stmt->execute(["Parent of Student $i", $student_id, $hashed]);
                        echo "<div class='success'>✅ Created parent account: $student_id / parent123</div>";
                    }
                }
                
                echo "<div class='success' style='background:#4CAF50;'>🎉 ALL PASSWORDS FIXED!</div>";
                
                echo "<h3>Test Login Credentials:</h3>";
                echo "<pre>";
                echo "SuperAdmin: superadmin@school.com / superadmin123\n";
                echo "Teacher: james@school.com / teacher123\n";
                echo "Parent: STU001 / parent123\n";
                echo "Parent: STU002 / parent123\n";
                echo "Parent: STU003 / parent123\n";
                echo "</pre>";
                
                echo "<div style='margin-top:30px; padding:20px; background:#e8f5e9; border-radius:10px;'>";
                echo "<h3>📋 Next Steps:</h3>";
                echo "<p>1. <a href='login.php' style='color:#4361ee; font-weight:bold;'>Go to Login Page</a></p>";
                echo "<p>2. Try login with above credentials</p>";
                echo "<p>3. Delete this fix_passwords.php file after use</p>";
                echo "</div>";
                
            } catch (PDOException $e) {
                echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
                echo "<p>Make sure:</p>";
                echo "<ul>";
                echo "<li>XAMPP is running</li>";
                echo "<li>Database 'school_management' exists</li>";
                echo "<li>MySQL username: root, password: (empty)</li>";
                echo "</ul>";
            }
        } else {
            ?>
            <div style="padding:20px; background:#fff3e0; border-radius:10px; margin:20px 0;">
                <h3>⚠️ This script will reset all passwords to default!</h3>
                <p>It will fix:</p>
                <ul>
                    <li>SuperAdmin password</li>
                    <li>All teacher passwords</li>
                    <li>Parent account passwords</li>
                    <li>Create missing parent accounts</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" class="btn">🔧 Fix All Passwords Now</button>
            </form>
            
            <div style="margin-top:30px; padding:20px; background:#f8f9fa; border-radius:10px;">
                <h3>Default Passwords After Fix:</h3>
                <pre>
SuperAdmin: superadmin@school.com / superadmin123
Teachers: email / teacher123
Parents: STU001 / parent123
                </pre>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>