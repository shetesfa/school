<?php
require_once 'config.php';



$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        $error = 'Please enter your email or student ID';
    } else {
        // Check if input is email or student ID
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active'");
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND role = 'parent' AND status = 'active'");
        }
        
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = generateToken(32);
            $token_hash = password_hash($token, PASSWORD_DEFAULT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Delete any existing tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
            
            // Store new token
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token_hash, $expires_at]);
            
            // Create reset link
            $reset_link = SITE_URL . "/reset_password.php?token=" . urlencode($token);
            
            // Get email for sending (for parents, email might not exist, use admin email)
            $to_email = filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? $user['email'] : 'parent@school.com';
            
            // Send email (simulated)
            $subject = "Password Reset - EduManage Pro";
            $message = "
            <html>
            <head>
                <title>Password Reset</title>
            </head>
            <body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #4361ee, #3a0ca3); padding: 30px; text-align: center;'>
                        <h1 style='color: white; margin: 0;'>🔐 Password Reset</h1>
                    </div>
                    <div style='padding: 40px;'>
                        <h2 style='color: #333;'>Hello " . htmlspecialchars($user['name']) . ",</h2>
                        <p style='color: #666; line-height: 1.6;'>You have requested to reset your password for EduManage Pro.</p>
                        
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='$reset_link' style='background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 16px 40px; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);'>
                                Reset My Password
                            </a>
                        </div>
                        
                        <p style='color: #666; font-size: 14px;'><strong>⚠️ This link will expire in 15 minutes.</strong></p>
                        
                        <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 30px;'>
                            <p style='color: #666; font-size: 12px; margin: 0;'>
                                <strong>Note for Parents:</strong> Your username is your child's Student ID.<br>
                                If you didn't request this password reset, please ignore this email.
                            </p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if (sendEmail($to_email, $subject, $message)) {
                $success = '✅ Password reset link has been sent!';
                
                // For local testing
                if ($_SERVER['HTTP_HOST'] == 'localhost') {
                    $success .= "<br><br><strong>📋 Localhost Test Link:</strong><br>";
                    $success .= "<div style='background:#f5f5f5;padding:10px;border-radius:5px;margin-top:10px;font-family:monospace;font-size:12px;word-break:break-all;'>";
                    $success .= $reset_link;
                    $success .= "</div>";
                }
            } else {
                $error = 'Failed to send email. Please contact administrator.';
            }
        } else {
            // For security, show same message
            $success = 'If your username is registered, you will receive a password reset link shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EduManage Pro</title>
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
        
        .forgot-container {
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
        
        .info-box {
            background: linear-gradient(135deg, rgba(76, 201, 240, 0.1), rgba(67, 97, 238, 0.1));
            border-left: 4px solid #4cc9f0;
            color: #0066cc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .info-box i {
            font-size: 1.5rem;
            margin-top: 2px;
        }
        
        .error {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(247, 37, 133, 0.1));
            border-left-color: #f72585;
            color: #d32f2f;
        }
        
        .success {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(67, 97, 238, 0.1));
            border-left-color: #4CAF50;
            color: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 30px;
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
        
        input[type="text"] {
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
        
        .btn-submit {
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
        }
        
        .btn-submit:hover {
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
        
        .instructions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .instructions h4 {
            color: #4361ee;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 480px) {
            .forgot-container {
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
        
        .user-types {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .user-type {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #666;
            border: 2px solid transparent;
        }
        
        .user-type.active {
            background: white;
            border-color: #4361ee;
            color: #4361ee;
            font-weight: 600;
        }
    </style>
    <script>
        function showExample(type) {
            const input = document.getElementById('username');
            const examples = document.querySelectorAll('.user-type');
            
            examples.forEach(ex => ex.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            if (type === 'parent') {
                document.querySelector('label[for="username"]').innerHTML = 'Student ID <span style="color:#666;font-size:0.9em;">(Parent Username)</span>';
                input.placeholder = 'Enter Student ID (e.g., STU001)';
            } else {
                document.querySelector('label[for="username"]').innerHTML = 'Email Address';
                input.placeholder = 'Enter your email';
            }
        }
    </script>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <h1><i class="fas fa-key"></i> Forgot Password</h1>
            <p>Reset your account password</p>
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            Enter your username/email. You will receive a password reset link valid for 15 minutes.
        </div>
        
        <?php if ($error): ?>
            <div class="info-box error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="info-box success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="user-types">
            <div class="user-type active" onclick="showExample('admin')">
                Admin/Teacher
            </div>
            <div class="user-type" onclick="showExample('parent')">
                Parent
            </div>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Email Address</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your email"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> Send Reset Link
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
        
        <div class="instructions">
            <h4><i class="fas fa-lightbulb"></i> Instructions:</h4>
            <ul style="padding-left: 20px; margin: 10px 0;">
                <li><strong>Teachers/Admins:</strong> Enter your email address</li>
                <li><strong>Parents:</strong> Enter your child's Student ID (e.g., STU001)</li>
                <li>Check your email for reset link (valid for 15 minutes)</li>
                <li>For localhost testing: Check email_log.txt file</li>
            </ul>
        </div>
    </div>
</body>
</html>