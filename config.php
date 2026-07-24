<?php
// ============================================
// CONFIGURATION FILE - SCHOOL MANAGEMENT SYSTEM
// International School Edition v2.0
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0); // Off in production

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ── Database ────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── Site Settings ────────────────────────────
define('SITE_NAME', 'EduTrack International School');
define('SITE_SHORT', 'EduTrack');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
define('SITE_VERSION', '2.0');

// ── Cookie Settings ─────────────────────────
define('REMEMBER_ME_COOKIE', 'remember_me');
define('REMEMBER_ME_EXPIRE', 30 * 24 * 60 * 60);

// ── Result Visibility Modes ─────────────────
// 1=Hidden, 2=Attendance Only, 3=Average+Rank+Attendance, 4=Full Report
define('VISIBILITY_HIDDEN', 1);
define('VISIBILITY_ATTENDANCE', 2);
define('VISIBILITY_SUMMARY', 3);
define('VISIBILITY_FULL', 4);

// ── Database Connection ──────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed. Check config.php']));
}

// ============================================
// AUTH FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php?error=session_expired");
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    $allowedRoles = (array)$allowedRoles;
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        switch ($_SESSION['role']) {
            case 'superadmin': header("Location: admin.php"); break;
            case 'teacher':    header("Location: teacher.php"); break;
            case 'parent':     header("Location: parent.php"); break;
            default:           header("Location: login.php");
        }
        exit();
    }
}

function redirectByRole() {
    if (isLoggedIn()) {
        switch ($_SESSION['role']) {
            case 'superadmin': header("Location: admin.php"); break;
            case 'teacher':    header("Location: teacher.php"); break;
            case 'parent':     header("Location: parent.php"); break;
            default:           header("Location: login.php");
        }
        exit();
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function generateStudentID($pdo) {
    $stmt = $pdo->query("SELECT roll_number FROM students WHERE roll_number REGEXP '^STU[0-9]+$' ORDER BY CAST(SUBSTRING(roll_number,4) AS UNSIGNED) DESC LIMIT 1");
    $last = $stmt->fetch();
    $next = $last ? (int)substr($last['roll_number'], 3) + 1 : 1;
    return 'STU' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

function getGradeLetter($percentage) {
    if ($percentage >= 90) return ['A+', '#10b981'];
    if ($percentage >= 80) return ['A',  '#10b981'];
    if ($percentage >= 70) return ['B+', '#3b82f6'];
    if ($percentage >= 60) return ['B',  '#3b82f6'];
    if ($percentage >= 50) return ['C',  '#f59e0b'];
    if ($percentage >= 40) return ['D',  '#f97316'];
    return ['F', '#ef4444'];
}

function getVisibilityMode($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_value FROM school_settings WHERE setting_key = 'result_visibility' LIMIT 1");
        $row = $stmt->fetch();
        return $row ? (int)$row['setting_value'] : 1;
    } catch (Exception $e) {
        return 1;
    }
}

function getSchoolSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM school_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function isClassLocked($pdo, $class_id, $term, $year) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM semester_locks WHERE class_id = ? AND term = ? AND academic_year = ? AND is_locked = 1");
        $stmt->execute([$class_id, $term, $year]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function uploadFile($file, $subdir = 'uploads') {
    $allowed = ['jpg','jpeg','png','gif','pdf','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false;
    $filename = uniqid('file_', true) . '.' . $ext;
    $path = __DIR__ . '/' . $subdir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $subdir . '/' . $filename;
    }
    return false;
}
?>
