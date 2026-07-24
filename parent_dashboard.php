<?php
session_start();
require_once 'config.php';

// Only parents can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit;
}

$parent_id = $_SESSION['user_id'];
$parent_name = $_SESSION['name'] ?? 'Parent';

// Find linked student
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'parent'");
$stmt->execute([$parent_id]);
$parent = $stmt->fetch();

if (!$parent) {
    die("Parent record not found");
}

$student_roll = $parent['email'];

// Get student info
$stmt = $pdo->prepare("SELECT * FROM students WHERE roll_number = ?");
$stmt->execute([$student_roll]);
$student = $stmt->fetch();

$message = '';
$error = '';

// Handle Ask Question
if (isset($_POST['ask_question'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $subject_id = (int)$_POST['subject_id'];
    $question_text = trim($_POST['question_text']);
    $student_id = $student['id'];
    
    if ($teacher_id && $subject_id && $question_text && $student_id) {
        // Insert question
        $stmt = $pdo->prepare("
            INSERT INTO questions (student_id, teacher_id, subject_id, parent_id, question_text, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        
        if ($stmt->execute([$student_id, $teacher_id, $subject_id, $parent_id, $question_text])) {
            $question_id = $pdo->lastInsertId();
            
            // Get teacher's user_id for notification
            $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $teacher_user_id = $stmt->fetchColumn();
            
            // Create notification for teacher
            if ($teacher_user_id) {
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, reference_id, message)
                    VALUES (?, 'new_question', ?, ?)
                ");
                $notif_message = "New question from parent of " . $student['name'];
                $notif_stmt->execute([$teacher_user_id, $question_id, $notif_message]);
            }
            
            $message = "✅ Your question has been sent to the teacher!";
        } else {
            $error = "❌ Failed to send question.";
        }
    } else {
        $error = "❌ Please fill all fields.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = trim($_POST['current_password']);
    $new = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);
    
    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "❌ All fields are required!";
    } elseif ($new !== $confirm) {
        $error = "❌ New passwords do not match!";
    } elseif (strlen($new) < 6) {
        $error = "❌ Password must be at least 6 characters!";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$parent_id]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current, $user['password'])) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $parent_id])) {
                $message = "✅ Password updated successfully!";
                $_SESSION['password_changed'] = true;
            } else {
                $error = "❌ Failed to update password.";
            }
        } else {
            $error = "❌ Current password is incorrect!";
        }
    }
}

// Check if using default password
$force_change = false;
if (!isset($_SESSION['password_changed'])) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$parent_id]);
    $user = $stmt->fetch();
    
    if ($user && (password_verify('parent123', $user['password']) || $user['password'] === 'parent123')) {
        $force_change = true;
    }
}

// If no student found
if (!$student) {
    $error = "No student found with roll number: " . htmlspecialchars($student_roll);
} else {
    $student_id = $student['id'];
    
    // Format roll number
    $roll_display = preg_match('/^BS\d+$/', $student['roll_number']) 
        ? $student['roll_number'] 
        : 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
    
    // Get student photo
    $stmt = $pdo->prepare("SELECT photo FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student['photo'] = $stmt->fetchColumn();
    
    // Get attendance
    $stmt = $pdo->prepare("
        SELECT date, status 
        FROM attendance 
        WHERE student_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ORDER BY date DESC
    ");
    $stmt->execute([$student_id]);
    $attendance_records = $stmt->fetchAll();
    
    $attendance = [];
    $present_days = 0;
    $total_days = count($attendance_records);
    $absent_streak = 0;
    $chronic_absent = false;
    
    foreach ($attendance_records as $record) {
        $attendance[$record['date']] = $record['status'];
        if ($record['status'] === 'Present') {
            $present_days++;
            $absent_streak = 0;
        } elseif ($record['status'] === 'Absent') {
            $absent_streak++;
            if ($absent_streak >= 30) {
                $chronic_absent = true;
            }
        } else {
            $absent_streak = 0;
        }
    }
    
    $attendance_rate = $total_days > 0 ? round(($present_days / $total_days) * 100, 1) : 0;
    
    // Get subjects with marks and their teachers
    $stmt = $pdo->prepare("
        SELECT 
            smc.*,
            sub.name as subject_name,
            u.name as teacher_name,
            t.id as teacher_id,
            t.photo as teacher_photo,
            t.phone as teacher_phone
        FROM student_marks_custom smc
        JOIN subjects sub ON smc.subject_id = sub.id
        LEFT JOIN teachers t ON smc.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE smc.student_id = ? AND smc.term = 'Term 1' AND smc.total_mark > 0
        ORDER BY sub.name
    ");
    $stmt->execute([$student_id]);
    $subject_marks = $stmt->fetchAll();
    
    $avg_percentage = 0;
    $subjects_with_marks = count($subject_marks);
    
    foreach ($subject_marks as $mark) {
        $avg_percentage += $mark['percentage'];
    }
    
    $avg_percentage = $subjects_with_marks > 0 ? $avg_percentage / $subjects_with_marks : 0;
    
    // Get ALL questions and answers for this student
    $stmt = $pdo->prepare("
        SELECT q.*, 
               sub.name as subject_name,
               t.photo as teacher_photo,
               u.name as teacher_name,
               u2.name as parent_name
        FROM questions q
        JOIN subjects sub ON q.subject_id = sub.id
        JOIN teachers t ON q.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN users u2 ON q.parent_id = u2.id
        WHERE q.student_id = ?
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$student_id]);
    $questions = $stmt->fetchAll();
    
    // Get class teacher info
    $stmt = $pdo->prepare("
        SELECT u.name as teacher_name, t.phone as teacher_phone, t.photo as teacher_photo
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$student['teacher_id'] ?? 0]);
    $class_teacher = $stmt->fetch();
}

// Helper function for grade color
function getGradeColor($percentage) {
    if ($percentage >= 80) return '#4CAF50';
    if ($percentage >= 60) return '#FF9800';
    if ($percentage >= 40) return '#f44336';
    return '#9C27B0';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Bori Secondary School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4CAF50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        
        /* Student Profile Card */
        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid #667eea;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .student-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .student-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            border: 4px solid #fff;
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-size: 2.2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .student-badge {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            display: inline-block;
            margin-right: 10px;
        }
        
        .info-badges {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .info-badge {
            background: #f1f8e9;
            padding: 8px 20px;
            border-radius: 30px;
            color: #667eea;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .tab-btn.active {
            background: #667eea;
            color: white;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        /* Chronic Absent Alert */
        .chronic-alert {
            background: #ffebee;
            border-left: 6px solid #f44336;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { background-color: #ffebee; }
            50% { background-color: #ffcdd2; }
            100% { background-color: #ffebee; }
        }
        
        .chronic-icon {
            width: 50px;
            height: 50px;
            background: #f44336;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        /* Subjects Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .subject-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.06);
            border-top: 4px solid #667eea;
        }
        
        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .subject-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #667eea;
        }
        
        .teacher-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .teacher-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }
        
        .teacher-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }
        
        /* Questions Section */
        .questions-section {
            margin-top: 30px;
        }
        
        .question-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .question-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .question-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .question-text {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .answer-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid #4CAF50;
        }
        
        .answer-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .pending-badge {
            background: #ff9800;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .answered-badge {
            background: #4CAF50;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        /* Ask Question Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 100%;
            padding: 30px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        select:focus, textarea:focus {
            border-color: #667eea;
            outline: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-user-friends"></i> Parent Portal - Bori Secondary School</h1>
        <div>
            <span style="margin-right:20px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($parent_name); ?></span>
            <a href="logout.php" style="color:white; text-decoration:none;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <?php if($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($student): ?>
        
        <!-- Chronic Absent Alert -->
        <?php if ($chronic_absent): ?>
        <div class="chronic-alert">
            <div class="chronic-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h3 style="color: #b71c1c;">⚠️ Attendance Warning</h3>
                <p style="color: #b71c1c;">Your child has been absent for 30+ consecutive days. Please contact the school immediately.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Student Profile -->
        <div class="profile-card">
            <div class="profile-header">
                <?php if ($student['photo']): ?>
                    <img src="<?php echo $student['photo']; ?>" class="student-avatar" alt="<?php echo htmlspecialchars($student['name']); ?>">
                <?php else: ?>
                    <div class="student-avatar-placeholder">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                <?php endif; ?>
                
                <div class="student-info">
                    <h1 class="student-name"><?php echo htmlspecialchars($student['name']); ?></h1>
                    <div>
                        <span class="student-badge"><?php echo $roll_display; ?></span>
                        <span class="student-badge">Class <?php echo $student['grade'] . $student['class_section']; ?></span>
                    </div>
                    
                    <div class="info-badges">
                        <?php if($class_teacher && !empty($class_teacher['teacher_name'])): ?>
                        <span class="info-badge">
                            <i class="fas fa-chalkboard-teacher"></i> 
                            Homeroom: <?php echo htmlspecialchars($class_teacher['teacher_name']); ?>
                        </span>
                        <?php endif; ?>
                        <span class="info-badge">
                            <i class="fas fa-calendar-check"></i> 
                            Attendance: <?php echo $attendance_rate; ?>%
                        </span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-primary" onclick="showAskQuestionModal()">
                        <i class="fas fa-question-circle"></i> Ask Teacher
                    </button>
                    <button class="btn btn-primary" onclick="showPasswordModal()">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('academics')"><i class="fas fa-book-open"></i> Academics</button>
            <button class="tab-btn" onclick="showTab('questions')"><i class="fas fa-question-circle"></i> Questions & Answers</button>
        </div>
        
        <!-- ACADEMICS TAB -->
        <div id="academics-tab" class="tab-content active">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $subjects_with_marks; ?></div>
                    <div>Subjects with Marks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($avg_percentage, 1); ?>%</div>
                    <div>Overall Average</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $present_days; ?>/<?php echo $total_days; ?></div>
                    <div>Days Present</div>
                </div>
            </div>
            
            <!-- Subjects Performance -->
            <h2 style="margin: 30px 0 15px; color: #667eea;">
                <i class="fas fa-book-open"></i> Academic Performance
            </h2>
            
            <?php if (empty($subject_marks)): ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 16px;">
                    <i class="fas fa-info-circle" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                    <h3>No Marks Available Yet</h3>
                    <p>Your child's academic records will appear here once teachers enter marks.</p>
                </div>
            <?php else: ?>
                <div class="subjects-grid">
                    <?php foreach ($subject_marks as $subject): ?>
                    <div class="subject-card">
                        <div class="subject-header">
                            <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                        </div>
                        
                        <div style="text-align: center; margin: 15px 0;">
                            <strong style="font-size: 2rem; color: <?php echo getGradeColor($subject['percentage']); ?>">
                                <?php echo number_format($subject['percentage'], 1); ?>%
                            </strong>
                            <br>
                            <span style="background: #e8f5e9; padding: 5px 15px; border-radius: 20px; display: inline-block; margin-top: 5px;">
                                Grade: <?php echo $subject['grade']; ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($subject['teacher_name'])): ?>
                            <div class="teacher-info">
                                <?php if (!empty($subject['teacher_photo'])): ?>
                                    <img src="<?php echo $subject['teacher_photo']; ?>" class="teacher-avatar">
                                <?php else: ?>
                                    <div class="teacher-avatar-placeholder">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div><strong><?php echo htmlspecialchars($subject['teacher_name']); ?></strong></div>
                                    <?php if (!empty($subject['teacher_phone'])): ?>
                                        <div style="font-size: 0.8rem; color: #666;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($subject['teacher_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-primary btn-sm" style="margin-left: auto;" 
                                        onclick="quickAsk(<?php echo $subject['teacher_id']; ?>, <?php echo $subject['subject_id']; ?>)">
                                    <i class="fas fa-question"></i> Ask
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Attendance Calendar -->
            <div class="calendar-container" style="background: white; padding: 25px; border-radius: 16px; margin-top: 30px;">
                <h3><i class="fas fa-calendar-alt"></i> Attendance (Last 90 Days)</h3>
                <div style="display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap;">
                    <span><span style="background: #e8f5e9; padding: 5px 10px; border-radius: 5px;">✅ Present</span></span>
                    <span><span style="background: #fff3e0; padding: 5px 10px; border-radius: 5px;">⏰ Permission</span></span>
                    <span><span style="background: #ffebee; padding: 5px 10px; border-radius: 5px;">❌ Absent</span></span>
                    <span><span style="background: #ffcdd2; padding: 5px 10px; border-radius: 5px; animation: blink 1s infinite;">⚠️ 30+ Days</span></span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px;">
                    <?php
                    $start = new DateTime(date('Y-m-d', strtotime('-90 days')));
                    $end = new DateTime(date('Y-m-d'));
                    $interval = new DateInterval('P1D');
                    $period = new DatePeriod($start, $interval, $end);
                    
                    $absent_streak = 0;
                    
                    foreach ($period as $date) {
                        $d = $date->format('Y-m-d');
                        $status = $attendance[$d] ?? 'not-marked';
                        
                        if ($status == 'Absent') {
                            $absent_streak++;
                        } else {
                            $absent_streak = 0;
                        }
                        
                        $class = '';
                        if ($status == 'Present') $class = 'background: #e8f5e9;';
                        elseif ($status == 'Permission') $class = 'background: #fff3e0;';
                        elseif ($status == 'Absent') {
                            $class = ($absent_streak >= 30) ? 'background: #ffcdd2; font-weight: bold;' : 'background: #ffebee;';
                        }
                        
                        echo "<div style='padding:8px; border:1px solid #eee; border-radius:5px; text-align:center; $class'>"
                            .$date->format('d')."</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- QUESTIONS & ANSWERS TAB -->
        <div id="questions-tab" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #667eea;"><i class="fas fa-question-circle"></i> Questions & Answers</h2>
                <button class="btn btn-primary" onclick="showAskQuestionModal()">
                    <i class="fas fa-plus"></i> Ask New Question
                </button>
            </div>
            
            <?php if (empty($questions)): ?>
                <div style="text-align: center; padding: 60px; background: #f8f9fa; border-radius: 16px;">
                    <i class="fas fa-comments" style="font-size: 4rem; color: #667eea; margin-bottom: 20px;"></i>
                    <h3>No Questions Yet</h3>
                    <p>Click "Ask New Question" to contact your child's teachers.</p>
                </div>
            <?php else: ?>
                <div class="questions-section">
                    <?php foreach ($questions as $q): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <div class="question-meta">
                                    <?php if (!empty($q['teacher_photo'])): ?>
                                        <img src="<?php echo $q['teacher_photo']; ?>" class="question-avatar">
                                    <?php else: ?>
                                        <div style="width:35px; height:35px; border-radius:50%; background:#667eea; display:flex; align-items:center; justify-content:center; color:white;">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($q['teacher_name']); ?></strong>
                                        <br>
                                        <small><?php echo htmlspecialchars($q['subject_name']); ?></small>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($q['status'] == 'pending'): ?>
                                        <span class="pending-badge"><i class="fas fa-clock"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="answered-badge"><i class="fas fa-check"></i> Answered</span>
                                    <?php endif; ?>
                                    <small style="display:block; margin-top:5px;"><?php echo date('d M Y', strtotime($q['created_at'])); ?></small>
                                </div>
                            </div>
                            
                            <div class="question-text">
                                <strong>Question:</strong> <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                            </div>
                            
                            <?php if (!empty($q['answer_text'])): ?>
                                <div class="answer-box">
                                    <div class="answer-meta">
                                        <i class="fas fa-reply"></i>
                                        <strong>Answer from <?php echo htmlspecialchars($q['teacher_name']); ?></strong>
                                        <small>(<?php echo date('d M Y', strtotime($q['answered_at'])); ?>)</small>
                                    </div>
                                    <p><?php echo nl2br(htmlspecialchars($q['answer_text'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
            <div style="text-align: center; padding: 100px 20px; background: white; border-radius: 16px;">
                <div style="font-size: 5rem; color: #ddd; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 style="color: #f44336;">Student Not Found</h2>
                <p style="color: #666;">No student record linked to your account. Please contact the school.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Ask Question Modal -->
    <div id="askQuestionModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #667eea;"><i class="fas fa-question-circle"></i> Ask a Teacher</h2>
                <button onclick="closeAskQuestionModal()" style="background:none; border:none; font-size:2rem; cursor:pointer;">&times;</button>
            </div>
            
            <form method="POST">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Select Subject Teacher</label>
                    <select name="teacher_id" id="teacherSelect" required>
                        <option value="">-- Choose Teacher --</option>
                        <?php foreach ($subject_marks as $subject): ?>
                            <option value="<?php echo $subject['teacher_id']; ?>" data-subject="<?php echo $subject['subject_id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?> - <?php echo htmlspecialchars($subject['teacher_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="subject_id" id="subjectId">
                
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:600;">Your Question</label>
                    <textarea name="question_text" rows="5" required placeholder="Type your question here..."></textarea>
                </div>
                
                <button type="submit" name="ask_question" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-paper-plane"></i> Send Question
                </button>
            </form>
        </div>
    </div>
    
    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <h2 style="color: #667eea; margin-bottom: 20px;">
                <i class="fas fa-key"></i> Change Password
            </h2>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Current Password</label>
                    <input type="password" name="current_password" required 
                           style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">New Password</label>
                    <input type="password" name="new_password" required 
                           style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Confirm Password</label>
                    <input type="password" name="confirm_password" required 
                           style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="change_password" class="btn btn-primary" style="flex:1;">
                        Update Password
                    </button>
                    <button type="button" class="btn" onclick="closePasswordModal()" 
                            style="flex:1; background:#f5f5f5; color:#666;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function showAskQuestionModal() {
            document.getElementById('askQuestionModal').style.display = 'flex';
        }
        
        function closeAskQuestionModal() {
            document.getElementById('askQuestionModal').style.display = 'none';
        }
        
        function quickAsk(teacherId, subjectId) {
            document.getElementById('teacherSelect').value = teacherId;
            document.getElementById('subjectId').value = subjectId;
            showAskQuestionModal();
        }
        
        document.getElementById('teacherSelect')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected) {
                const subjectId = selected.getAttribute('data-subject');
                document.getElementById('subjectId').value = subjectId;
            }
        });
        
        function showPasswordModal() {
            document.getElementById('passwordModal').style.display = 'flex';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const askModal = document.getElementById('askQuestionModal');
            const passModal = document.getElementById('passwordModal');
            if (event.target === askModal) closeAskQuestionModal();
            if (event.target === passModal) closePasswordModal();
        }
    </script>
</body>
</html>