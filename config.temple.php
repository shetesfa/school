<?php
// ============================================
// SCHOOL MANAGEMENT SYSTEM - CONFIGURATION TEMPLATE
// ============================================
session_start();

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site configuration
define('SITE_NAME', 'EduManage Pro');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));

// Remember me cookie (30 days)
define('REMEMBER_ME_COOKIE', 'edu_remember');
define('REMEMBER_ME_EXPIRE', 30 * 24 * 60 * 60); // 30 days

// Color theme
define('PRIMARY_COLOR', '#4361ee');
define('SECONDARY_COLOR', '#3a0ca3');
define('SUCCESS_COLOR', '#4cc9f0');
define('WARNING_COLOR', '#f72585');
define('DANGER_COLOR', '#7209b7');
define('LIGHT_COLOR', '#f8f9fa');
define('DARK_COLOR', '#212529');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("<div style='background:linear-gradient(135deg, #f72585, #7209b7);color:white;padding:30px;font-family:Arial;border-radius:10px;max-width:600px;margin:50px auto;'>
            <h1 style='margin:0 0 20px 0;'>🚨 Database Connection Failed</h1>
            <p><strong>Error:</strong> " . $e->getMessage() . "</p>
            <p>Please check:</p>
            <ul>
                <li>XAMPP MySQL is running</li>
                <li>Database 'school_management' exists</li>
                <li>Username: root, Password: (empty)</li>
            </ul>
            <p>Run setup.php first to create database.</p>
        </div>");
}

// Handle remember me login
function checkRememberMe($pdo) {
    if (isset($_COOKIE[REMEMBER_ME_COOKIE]) && !isset($_SESSION['user_id'])) {
        $token = $_COOKIE[REMEMBER_ME_COOKIE];
        list($user_id, $token_hash) = explode(':', $token);
        
        if (is_numeric($user_id)) {
            // Check token in database
            $stmt = $pdo->prepare("SELECT rt.*, u.* FROM remember_tokens rt 
                                  JOIN users u ON rt.user_id = u.id 
                                  WHERE rt.token_hash = ? AND rt.expires_at > NOW() AND u.status = 'active'");
            $stmt->execute([$token_hash]);
            $token_data = $stmt->fetch();
            
            if ($token_data) {
                $_SESSION['user_id'] = $token_data['user_id'];
                $_SESSION['name'] = $token_data['name'];
                $_SESSION['email'] = $token_data['email'];
                $_SESSION['role'] = $token_data['role'];
                
                // Set teacher_id for teachers
                if ($token_data['role'] == 'teacher') {
                    $_SESSION['teacher_id'] = getTeacherId($pdo, $token_data['user_id']);
                }
                
                // Set student_id for parents
                if ($token_data['role'] == 'parent') {
                    $student = getStudentForParent($pdo, $token_data['user_id']);
                    if ($student) {
                        $_SESSION['student_id'] = $student['id'];
                        $_SESSION['student_name'] = $student['name'];
                    }
                }
                
                return true;
            }
        }
    }
    return false;
}

checkRememberMe($pdo);

// Email simulation for localhost
function sendEmail($to, $subject, $message) {
    $headers = "From: system@edumanage.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $logFile = __DIR__ . '/email_log.txt';
    $logMessage = "=================================\n";
    $logMessage .= "To: $to\n";
    $logMessage .= "Subject: $subject\n";
    $logMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $logMessage .= "Message:\n$message\n\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    return true;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_message'] = 'Please login to access this page';
        header("Location: login.php");
        exit();
    }
}

// Check specific role access
function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['role'], (array)$allowedRoles)) {
        $_SESSION['error'] = 'Access denied! You do not have permission.';
        header("Location: " . ($_SESSION['role'] == 'parent' ? 'parent_dashboard.php' : 'dashboard.php'));
        exit();
    }
}

// Redirect based on role
function redirectBasedOnRole() {
    if (isLoggedIn()) {
        switch($_SESSION['role']) {
            case 'superadmin':
                header("Location: superadmin_dashboard.php");
                break;
            case 'teacher':
                header("Location: teacher_dashboard.php");
                break;
            case 'parent':
                header("Location: parent_dashboard.php");
                break;
            default:
                header("Location: login.php");
        }
        exit();
    }
}

// Generate secure token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Get teacher ID from user ID
function getTeacherId($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $teacher = $stmt->fetch();
    return $teacher ? $teacher['id'] : null;
}

// Get student for parent (parent login = student ID)
function getStudentForParent($pdo, $parentId) {
    // Get parent's email (which is student ID)
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'parent'");
    $stmt->execute([$parentId]);
    $parent = $stmt->fetch();
    
    if ($parent) {
        $studentId = $parent['email']; // Email field stores student ID for parents
        $stmt = $pdo->prepare("SELECT * FROM students WHERE roll_number = ?");
        $stmt->execute([$studentId]);
        return $stmt->fetch();
    }
    return null;
}

// Create parent account for student
function createParentAccount($pdo, $studentId, $studentName) {
    // Check if parent account already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'parent'");
    $stmt->execute([$studentId]);
    
    if ($stmt->rowCount() == 0) {
        $parentName = "Parent of " . $studentName;
        $initialPassword = password_hash('parent123', PASSWORD_DEFAULT); // Default password
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'parent')");
        return $stmt->execute([$parentName, $studentId, $initialPassword]);
    }
    return false;
}

// Format date
function formatDate($date, $format = 'd M, Y') {
    return date($format, strtotime($date));
}

// Get performance color
function getPerformanceColor($percentage) {
    if ($percentage >= 80) return '#4CAF50';
    if ($percentage >= 60) return '#FF9800';
    if ($percentage >= 40) return '#FF5722';
    return '#F44336';
}

// Get performance text
function getPerformanceText($percentage) {
    if ($percentage >= 90) return 'Excellent 🎯';
    if ($percentage >= 80) return 'Very Good 👍';
    if ($percentage >= 70) return 'Good ✅';
    if ($percentage >= 60) return 'Satisfactory ⚡';
    if ($percentage >= 50) return 'Needs Improvement 📈';
    if ($percentage >= 40) return 'Poor 📉';
    return 'Very Poor ❌';
}

// Add CSS for alerts
function displayAlert($type, $message) {
    $colors = [
        'success' => '#4CAF50',
        'error' => '#F44336',
        'warning' => '#FF9800',
        'info' => '#2196F3'
    ];
    
    $color = $colors[$type] ?? '#2196F3';
    $icon = $type == 'success' ? '✅' : ($type == 'error' ? '❌' : ($type == 'warning' ? '⚠️' : 'ℹ️'));
    
    return "<div style='
        background: linear-gradient(135deg, {$color}20, {$color}10);
        border-left: 4px solid $color;
        color: #333;
        padding: 15px;
        margin: 15px 0;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
    '>
        <span style='font-size: 1.2em;'>$icon</span>
        <span>$message</span>
    </div>";
}
?>