<?php
// ============================================
// FIX PARENT NAMES - Make them login with student names
// ============================================

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=school_management;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(Exception $e) {
    die("Database error: " . $e->getMessage());
}

$message = '';

if (isset($_POST['fix_names'])) {
    // Update parent names to match student names
    $updates = [
        ['Parent of John Smith', 'John Smith'],
        ['Parent of Emma Johnson', 'Emma Johnson'],
        ['Parent of Michael Brown', 'Michael Brown'],
        ['Parent of Sophia Williams', 'Sophia Williams'],
        ['Parent of William Jones', 'William Jones'],
        ['Parent of Olivia Garcia', 'Olivia Garcia'],
        ['Parent of James Miller', 'James Miller'],
        ['Parent of Ava Davis', 'Ava Davis'],
        ['Parent of Benjamin Rodriguez', 'Benjamin Rodriguez'],
        ['Parent of Mia Martinez', 'Mia Martinez'],
    ];
    
    $pdo->beginTransaction();
    try {
        foreach ($updates as $update) {
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE name = ? AND role = 'parent'");
            $stmt->execute([$update[1], $update[0]]);
        }
        
        // Fix all passwords
        $super_pass = password_hash('superadmin123', PASSWORD_DEFAULT);
        $teacher_pass = password_hash('teacher123', PASSWORD_DEFAULT);
        $parent_pass = password_hash('parent123', PASSWORD_DEFAULT);
        $reg_pass = password_hash('reg123', PASSWORD_DEFAULT);
        
        // Update superadmin
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'superadmin'")->execute([$super_pass]);
        
        // Update teachers
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'teacher'")->execute([$teacher_pass]);
        
        // Update parents
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'parent'")->execute([$parent_pass]);
        
        // Update registration
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'registration'")->execute([$reg_pass]);
        
        $pdo->commit();
        $message = "✅ All names and passwords fixed! Parents can now login with student names.";
    } catch(Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Show current users
$users = $pdo->query("SELECT id, name, email, role FROM users ORDER BY role, name")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Parent Names</title>
    <style>
        body { font-family: Arial; padding: 30px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .btn { background: #4CAF50; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #333; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Parent Login Names</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <button type="submit" name="fix_names" class="btn" onclick="return confirm('This will update parent names and reset all passwords. Continue?')">
                🔄 Fix All Names & Passwords
            </button>
        </form>
        
        <h2>Current Users in Database:</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Name (Use this to login)</th>
                <th>Email/Roll</th>
                <th>Role</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo $user['role']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h2 style="margin-top: 30px;">✅ After Fix, Use These Logins:</h2>
        <table>
            <tr>
                <th>Role</th>
                <th>Login Name</th>
                <th>Password</th>
            </tr>
            <tr><td>Super Admin</td><td>Super Admin</td><td>superadmin123</td></tr>
            <tr><td>School Principal</td><td>School Principal</td><td>superadmin123</td></tr>
            <tr><td>Teacher</td><td>Mr. James Wilson</td><td>teacher123</td></tr>
            <tr><td>Teacher</td><td>Ms. Sarah Johnson</td><td>teacher123</td></tr>
            <tr><td>Teacher</td><td>Mr. Robert Brown</td><td>teacher123</td></tr>
            <tr><td>Teacher</td><td>Ms. Emily Davis</td><td>teacher123</td></tr>
            <tr><td>Teacher</td><td>Mr. Michael Lee</td><td>teacher123</td></tr>
            <tr><td>Teacher</td><td>TESFA</td><td>teacher123</td></tr>
            <tr><td>Parent</td><td>John Smith</td><td>parent123</td></tr>
            <tr><td>Parent</td><td>Emma Johnson</td><td>parent123</td></tr>
            <tr><td>Parent</td><td>Michael Brown</td><td>parent123</td></tr>
            <tr><td>Parent</td><td>bayih atinaf</td><td>parent123</td></tr>
            <tr><td>Parent</td><td>megnot bayih</td><td>parent123</td></tr>
            <tr><td>Registrar</td><td>Registration Officer</td><td>reg123</td></tr>
            <tr><td>Registrar</td><td>Assistant Registrar</td><td>reg123</td></tr>
        </table>
        
        <div style="background: #fff3cd; padding: 20px; border-radius: 5px; margin-top: 30px;">
            <h3>⚠️ Important:</h3>
            <p><strong>Parents must login with the STUDENT'S NAME, not "Parent of..."</strong></p>
            <p>Example: If student is "John Smith", parent logs in as "John Smith" with password "parent123"</p>
        </div>
    </div>
</body>
</html>