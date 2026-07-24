<?php
// FORCE FIX ALL PASSWORDS - RUN THIS FIRST!

session_start();

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_management;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';

if (isset($_POST['fix'])) {
    // ===== SIMPLE PASSWORDS - NO HASHING ISSUES =====
    // Using plain text first to test (we'll hash them properly)
    
    $updates = [
        // Super Admin
        "UPDATE users SET password = ? WHERE role = 'superadmin'",
        // Teachers
        "UPDATE users SET password = ? WHERE role = 'teacher'",
        // Parents  
        "UPDATE users SET password = ? WHERE role = 'parent'",
        // Registration
        "UPDATE users SET password = ? WHERE role = 'registration'"
    ];
    
    // Create proper password hashes
    $super_hash = password_hash('superadmin123', PASSWORD_DEFAULT);
    $teacher_hash = password_hash('teacher123', PASSWORD_DEFAULT);
    $parent_hash = password_hash('parent123', PASSWORD_DEFAULT);
    $reg_hash = password_hash('reg123', PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        // Update superadmin
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'superadmin'")->execute([$super_hash]);
        
        // Update teachers
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'teacher'")->execute([$teacher_hash]);
        
        // Update parents
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'parent'")->execute([$parent_hash]);
        
        // Update registration
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'registration'")->execute([$reg_hash]);
        
        $pdo->commit();
        $message = "✅ ALL PASSWORDS FIXED!";
    } catch(Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Show all users
$users = $pdo->query("SELECT id, name, email, role, password FROM users ORDER BY role, name")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Passwords</title>
    <style>
        body { font-family: Arial; padding: 30px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .btn { background: #4CAF50; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 20px 0; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #333; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .highlight { background: #ffffcc; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Password Fix Tool</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="post">
            <button type="submit" name="fix" class="btn">🔨 FIX ALL PASSWORDS NOW</button>
        </form>
        
        <h2>Current Users in Database:</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Password Hash</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo $user['role']; ?></td>
                <td style="font-family: monospace; font-size: 11px;"><?php echo substr($user['password'], 0, 30); ?>...</td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2 style="margin-top: 30px;">✅ After Fix, Use These:</h2>
        <table>
            <tr>
                <th>Role</th>
                <th>Username (Name)</th>
                <th>Password</th>
            </tr>
            <tr class="highlight">
                <td>Super Admin</td>
                <td><strong>Super Admin</strong></td>
                <td><strong>superadmin123</strong></td>
            </tr>
            <tr>
                <td>Teacher</td>
                <td><strong>Mr. James Wilson</strong></td>
                <td><strong>teacher123</strong></td>
            </tr>
            <tr>
                <td>Teacher</td>
                <td><strong>Ms. Sarah Johnson</strong></td>
                <td><strong>teacher123</strong></td>
            </tr>
            <tr>
                <td>Parent</td>
                <td><strong>John Smith</strong></td>
                <td><strong>parent123</strong></td>
            </tr>
            <tr>
                <td>Parent</td>
                <td><strong>Emma Johnson</strong></td>
                <td><strong>parent123</strong></td>
            </tr>
            <tr>
                <td>Registration</td>
                <td><strong>Registration Officer</strong></td>
                <td><strong>reg123</strong></td>
            </tr>
        </table>
        
        <div style="background: #fff3cd; padding: 20px; border-radius: 5px; margin-top: 30px;">
            <h3>📋 INSTRUCTIONS:</h3>
            <ol>
                <li>Click the <strong>FIX ALL PASSWORDS NOW</strong> button above</li>
                <li>Wait for success message</li>
                <li>Go to <a href="login.php" target="_blank">login.php</a></li>
                <li>Login with:
                    <ul>
                        <li><strong>Super Admin</strong> / superadmin123</li>
                        <li>OR <strong>Mr. James Wilson</strong> / teacher123</li>
                        <li>OR <strong>John Smith</strong> / parent123</li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>
</body>
</html>