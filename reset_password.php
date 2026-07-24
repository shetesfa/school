<?php
require_once 'config.php';

// Redirect if already logged in


$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// Check if token is provided
if (empty($token)) {
    header("Location: forgot_password.php");
    exit();
}

// Verify token and get user
$user_id = null;
$user_name = null;

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Find valid token
    $stmt = $pdo->prepare("SELECT pr.*, u.name, u.email FROM password_resets pr 
                          JOIN users u ON pr.user_id = u.id 
                          WHERE pr.expires_at > NOW() AND pr.used = 0");
    $stmt->execute();
    $tokens = $stmt->fetchAll();
    
    foreach ($tokens as $token_record) {
        if (password_verify($token, $token_record['token_hash'])) {
            $user_id = $token_record['user_id'];
            $user_name = $token_record['name'];
            $user_email = $token_record['email'];
            break;
        }
    }
    
    if (!$user_id) {
        $error = 'Invalid or expired reset link. Please request a new one.';
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Both password fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Re-verify token
        $stmt = $pdo->prepare("SELECT pr.* FROM password_resets pr 
                              WHERE pr.expires_at > NOW() AND pr.used = 0");
        $stmt->execute();
        $tokens = $stmt->fetchAll();
        
        $valid_token = false;
        $token_id = null;
        
        foreach ($tokens as $token_record) {
            if (password_verify($token, $token_record['token_hash'])) {
                $valid_token = true;
                $user_id = $token_record['user_id'];
                $token_id = $token_record['id'];
                break;
            }
        }
        
        if (!$valid_token) {
            $error = 'Invalid or expired reset link. Please request a new one.';
        } else {
            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($stmt->execute([$hashedPassword, $user_id])) {
                // Mark token as used
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$token_id]);
                
                $success = '✅ Password reset successfully! You can now <a href="login.php" style="color:#4361ee;font-weight:bold;">login</a> with your new password.';
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EduManage Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: gradientShift 15s ease infinite;
            background-size: 400% 400%;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 50px;
            box-shadow: 0 25px 75px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.8s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 1rem;
        }
        
        .user-info {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(67, 97, 238, 0.1));
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 4px solid #4CAF50;
        }
        
        .user-info p {
            margin: 8px 0;
            color: #2e7d32;
        }
        
        .user-info strong {
            color: #333;
        }
        
        .expiry-warning {
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(67, 97, 238, 0.1));
            border-left: 4px solid #FF9800;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            color: #ef6c00;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(247, 37, 133, 0.1));
            border-left: 4px solid #f72585;
            color: #d32f2f;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(67, 97, 238, 0.1));
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .input-group {
            position: relative;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 16px 20px 16px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        input:focus {
            border-color: #4361ee;
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }
        
        .password-strength {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .strength-bar {
            flex: 1;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }
        
        .strength-text {
            font-size: 0.85rem;
            color: #666;
            min-width: 80px;
        }
        
        .btn-reset {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
        }
        
        .links {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eee;
        }
        
        .links a {
            color: #4361ee;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 500;
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .links a:hover {
            color: #3a0ca3;
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .reset-container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
            
            .links a {
                display: block;
                margin: 10px 0;
            }
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #666;
        }
        
        .password-requirements ul {
            padding-left: 20px;
            margin: 10px 0;
        }
    </style>
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthText = document.getElementById('strength-text');
            const strengthBar = document.getElementById('strength-bar');
            const matchText = document.getElementById('password-match');
            const confirm = document.getElementById('confirm_password').value;
            
            // Strength calculation
            if (password.length === 0) {
                strengthText.innerHTML = '';
                strengthBar.style.width = '0%';
                strengthBar.style.background = '#e0e0e0';
            } else {
                let score = 0;
                if (password.length >= 6) score++;
                if (password.length >= 8) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
                const colors = ['#f44336', '#ff9800', '#ffeb3b', '#8bc34a', '#4caf50', '#2e7d32'];
                
                strengthText.innerHTML = strengthLevels[score];
                strengthBar.style.width = (score * 20) + '%';
                strengthBar.style.background = colors[score];
            }
            
            // Match check
            if (confirm.length === 0) {
                matchText.innerHTML = '';
            } else if (password === confirm) {
                matchText.innerHTML = '✓ Passwords match';
                matchText.style.color = '#4CAF50';
            } else {
                matchText.innerHTML = '✗ Passwords do not match';
                matchText.style.color = '#f44336';
            }
        }
    </script>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <h1><i class="fas fa-redo"></i> Reset Password</h1>
            <p>Create your new password</p>
        </div>
        
        <?php if ($error && !$user_id && $_SERVER['REQUEST_METHOD'] == 'GET'): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
            <div class="links">
                <a href="forgot_password.php">
                    <i class="fas fa-key"></i> Request New Link
                </a>
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
            </body></html>
            <?php exit(); ?>
        <?php elseif ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <div class="links">
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </a>
                <a href="index.php">
                    <i class="fas fa-home"></i> Home
                </a>
            </div>
            </body></html>
            <?php exit(); ?>
        <?php endif; ?>
        
        <?php if ($user_id && $_SERVER['REQUEST_METHOD'] == 'GET'): ?>
        <div class="user-info">
            <p><strong>Account:</strong> <?php echo htmlspecialchars($user_name); ?></p>
            <p><strong>Reset for:</strong> 
                <?php 
                if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                    echo htmlspecialchars($user_email);
                } else {
                    echo "Student ID: " . htmlspecialchars($user_email);
                }
                ?>
            </p>
        </div>
        
        <div class="expiry-warning">
            <i class="fas fa-clock"></i>
            <span><strong>Hurry!</strong> This reset link expires in 15 minutes.</span>
        </div>
        <?php endif; ?>
        
        <?php if ($error && $_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter new password" onkeyup="checkPasswordStrength()">
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strength-bar"></div>
                    </div>
                    <span class="strength-text" id="strength-text"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm new password" onkeyup="checkPasswordStrength()">
                </div>
                <div class="password-strength">
                    <span class="strength-text" id="password-match"></span>
                </div>
            </div>
            
            <div class="password-requirements">
                <p><strong>Password Requirements:</strong></p>
                <ul>
                    <li>At least 6 characters long</li>
                    <li>Include uppercase and lowercase letters</li>
                    <li>Include numbers for better security</li>
                    <li>Special characters recommended</li>
                </ul>
            </div>
            
            <button type="submit" class="btn-reset">
                <i class="fas fa-save"></i> Reset Password
            </button>
        </form>
        
        <div class="links">
            <a href="login.php">
                <i class="fas fa-sign-in-alt"></i> Back to Login
            </a>
            <a href="index.php">
                <i class="fas fa-home"></i> Home
            </a>
        </div>
    </div>
</body>
</html>