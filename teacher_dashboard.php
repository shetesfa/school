<?php
session_start();
require_once 'config.php';

// Only teachers can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$message = '';
$error = '';

// Get teacher info with photo
$stmt = $pdo->prepare("
    SELECT t.id, t.subject_id, t.phone, t.photo as teacher_photo, u.name as teacher_name, sub.name as subject_name
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    LEFT JOIN subjects sub ON t.subject_id = sub.id
    WHERE t.user_id = ?
");
$stmt->execute([$teacher_user_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die("Teacher record not found");
}

$teacher_id = $teacher['id'];
$teacher_name = $teacher['teacher_name'];
$teacher_subject_id = $teacher['subject_id'];
$teacher_subject_name = $teacher['subject_name'] ?? 'Not Assigned';
$teacher_phone = $teacher['phone'] ?? 'Not provided';
$teacher_photo = $teacher['teacher_photo'] ?? null;

// ============================================
// CHECK IF TEACHER IS HOMEROOM TEACHER
// ============================================
$stmt = $pdo->prepare("
    SELECT c.*, CONCAT(c.grade, c.section) as class_name
    FROM classes c
    WHERE c.teacher_id = ?
");
$stmt->execute([$teacher_id]);
$homeroom_class = $stmt->fetch();

$is_homeroom = ($homeroom_class) ? true : false;

// ============================================
// GET TEACHER'S CLASSES
// ============================================
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.grade, c.section, CONCAT(c.grade, c.section) AS class_name
    FROM class_subject_teachers cst
    JOIN classes c ON cst.class_id = c.id
    WHERE cst.teacher_id = ?
    ORDER BY c.grade, c.section
");
$stmt->execute([$teacher_id]);
$assigned_classes = $stmt->fetchAll();

// If no classes in subject_teachers, try to get from students table
if (empty($assigned_classes)) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT grade, class_section, CONCAT(grade, class_section) AS class_name
        FROM students 
        WHERE teacher_id = ?
        ORDER BY grade, class_section
    ");
    $stmt->execute([$teacher_id]);
    $assigned_classes = $stmt->fetchAll();
}

// Default selected class
$selected_class = $_GET['class'] ?? ($assigned_classes[0]['class_name'] ?? '');
$grade = '';
$section = '';

if ($selected_class) {
    if (preg_match('/(\d+)([A-H])/', $selected_class, $matches)) {
        $grade = $matches[1];
        $section = $matches[2];
    }
}

// ============================================
// GET STUDENTS FOR SELECTED CLASS WITH PHOTOS
// ============================================
$students = [];
if ($grade && $section) {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               s.photo as student_photo
        FROM students s
        WHERE s.grade = ? AND s.class_section = ?
        ORDER BY s.roll_number
    ");
    $stmt->execute([$grade, $section]);
    $students = $stmt->fetchAll();
}

// ============================================
// HANDLE ANSWER QUESTION
// ============================================
if (isset($_POST['answer_question'])) {
    $question_id = (int)$_POST['question_id'];
    $answer_text = trim($_POST['answer_text']);
    
    if ($question_id && $answer_text) {
        $stmt = $pdo->prepare("
            UPDATE questions 
            SET answer_text = ?, status = 'answered', answered_at = NOW(), answered_by = ?
            WHERE id = ? AND teacher_id = ?
        ");
        
        if ($stmt->execute([$answer_text, $teacher_user_id, $question_id, $teacher_id])) {
            // Get student_id and parent_id for notification
            $stmt = $pdo->prepare("SELECT student_id, parent_id FROM questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $q = $stmt->fetch();
            
            if ($q) {
                // Get parent's user_id
                $parent_id = $q['parent_id'];
                
                // Create notification for parent
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, reference_id, message)
                    VALUES (?, 'new_answer', ?, ?)
                ");
                $notif_message = "Your question has been answered by " . $teacher_name;
                $notif_stmt->execute([$parent_id, $question_id, $notif_message]);
            }
            
            $message = "✅ Answer posted successfully!";
        } else {
            $error = "❌ Failed to post answer.";
        }
    }
}

// ============================================
// GET UNANSWERED QUESTIONS COUNT (for notification)
// ============================================
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM questions 
    WHERE teacher_id = ? AND status = 'pending'
");
$stmt->execute([$teacher_id]);
$unanswered_count = $stmt->fetchColumn();

// ============================================
// GET ALL QUESTIONS FOR THIS TEACHER
// ============================================
$stmt = $pdo->prepare("
    SELECT q.*, 
           s.name as student_name,
           s.photo as student_photo,
           sub.name as subject_name,
           u.name as parent_name
    FROM questions q
    JOIN students s ON q.student_id = s.id
    JOIN subjects sub ON q.subject_id = sub.id
    JOIN users u ON q.parent_id = u.id
    WHERE q.teacher_id = ?
    ORDER BY q.status, q.created_at DESC
");
$stmt->execute([$teacher_id]);
$questions = $stmt->fetchAll();

// ============================================
// GET MARK CRITERIA
// ============================================
$criteria_list = [];
$total_weight = 0;

if ($teacher_id && $teacher_subject_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM mark_criteria 
        WHERE teacher_id = ? AND subject_id = ? AND is_active = 1
        ORDER BY criteria_order
    ");
    $stmt->execute([$teacher_id, $teacher_subject_id]);
    $criteria_list = $stmt->fetchAll();
    
    foreach ($criteria_list as $c) {
        $total_weight += $c['criteria_weight'];
    }
}

// ============================================
// GET STUDENT MARKS
// ============================================
$student_marks = [];
if ($students && $teacher_id && $teacher_subject_id) {
    $ids = array_column($students, 'id');
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT student_id, criteria_data, total_mark, percentage, grade, is_locked
            FROM student_marks_custom
            WHERE student_id IN ($placeholders) AND subject_id = ? AND teacher_id = ? AND term = 'Term 1'
        ");
        $params = array_merge($ids, [$teacher_subject_id, $teacher_id]);
        $stmt->execute($params);
        
        foreach ($stmt->fetchAll() as $row) {
            $student_marks[$row['student_id']] = [
                'criteria_data' => json_decode($row['criteria_data'], true),
                'total' => $row['total_mark'],
                'percentage' => $row['percentage'],
                'grade' => $row['grade'],
                'is_locked' => $row['is_locked']
            ];
        }
    }
}

// ============================================
// GET ATTENDANCE DATA
// ============================================
$week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
$start_date = new DateTime($week_start);
$week_dates = [];

for ($i = 0; $i < 5; $i++) {
    $date = clone $start_date;
    $date->modify("+$i days");
    $date_str = $date->format('Y-m-d');
    $week_dates[] = [
        'date' => $date_str,
        'day_name' => $date->format('D'),
        'day_num' => $date->format('j'),
        'month' => $date->format('M'),
        'is_today' => ($date_str == $today)
    ];
}

// Get attendance data
$attendance_data = [];
if ($students && !empty($students)) {
    $student_ids = array_column($students, 'id');
    $date_strings = array_column($week_dates, 'date');
    
    if (!empty($student_ids) && !empty($date_strings)) {
        $ids_placeholder = implode(',', array_fill(0, count($student_ids), '?'));
        $dates_placeholder = implode(',', array_fill(0, count($date_strings), '?'));
        
        $stmt = $pdo->prepare("
            SELECT student_id, date, status 
            FROM attendance 
            WHERE student_id IN ($ids_placeholder) AND date IN ($dates_placeholder)
        ");
        $stmt->execute(array_merge($student_ids, $date_strings));
        
        foreach ($stmt->fetchAll() as $row) {
            $attendance_data[$row['student_id']][$row['date']] = $row['status'];
        }
    }
}

// ============================================
// CHECK IF SUBJECT IS LOCKED
// ============================================
$subject_locked = false;
if ($teacher_subject_id && !empty($students)) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM student_marks_custom
        WHERE subject_id = ? AND teacher_id = ? AND term = 'Term 1' AND is_locked = 1
    ");
    $stmt->execute([$teacher_subject_id, $teacher_id]);
    $locked_count = $stmt->fetchColumn();
    
    $total_students = count($students);
    if ($total_students > 0) {
        $subject_locked = ($locked_count == $total_students);
    }
}

// ============================================
// HOMEROOM DATA - Get all subjects and marks for the class
// ============================================
$homeroom_subjects = [];
$homeroom_marks = [];
$class_rankings = [];

if ($is_homeroom && $homeroom_class) {
    $class_id = $homeroom_class['id'];
    $homeroom_grade = $homeroom_class['grade'];
    $homeroom_section = $homeroom_class['section'];
    
    // Get all students in homeroom class
    $stmt = $pdo->prepare("
        SELECT s.*, s.photo as student_photo
        FROM students s
        WHERE s.grade = ? AND s.class_section = ?
        ORDER BY s.roll_number
    ");
    $stmt->execute([$homeroom_grade, $homeroom_section]);
    $homeroom_students = $stmt->fetchAll();
    
    // Get all subjects taught in this class with teacher info
    $stmt = $pdo->prepare("
        SELECT 
            sub.id,
            sub.name as subject_name,
            u.name as teacher_name,
            t.id as teacher_id,
            t.photo as teacher_photo
        FROM class_subject_teachers cst
        JOIN subjects sub ON cst.subject_id = sub.id
        JOIN teachers t ON cst.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE cst.class_id = ?
        ORDER BY sub.name
    ");
    $stmt->execute([$class_id]);
    $homeroom_subjects = $stmt->fetchAll();
    
    // Get marks for all students in all subjects
    if (!empty($homeroom_students) && !empty($homeroom_subjects)) {
        $student_ids = array_column($homeroom_students, 'id');
        $subject_ids = array_column($homeroom_subjects, 'id');
        
        $student_list = implode(',', $student_ids);
        $subject_list = implode(',', $subject_ids);
        
        $stmt = $pdo->query("
            SELECT 
                smc.student_id,
                smc.subject_id,
                smc.percentage,
                smc.grade,
                smc.is_locked
            FROM student_marks_custom smc
            WHERE smc.student_id IN ($student_list)
            AND smc.subject_id IN ($subject_list)
            AND smc.term = 'Term 1'
        ");
        
        while ($row = $stmt->fetch()) {
            $homeroom_marks[$row['student_id']][$row['subject_id']] = $row;
        }
        
        // Calculate averages and rankings
        $rank_data = [];
        foreach ($homeroom_students as $student) {
            $total = 0;
            $count = 0;
            foreach ($homeroom_subjects as $subject) {
                if (isset($homeroom_marks[$student['id']][$subject['id']])) {
                    $total += $homeroom_marks[$student['id']][$subject['id']]['percentage'];
                    $count++;
                }
            }
            $average = $count > 0 ? $total / $count : 0;
            $rank_data[$student['id']] = [
                'name' => $student['name'],
                'roll' => $student['roll_number'],
                'photo' => $student['student_photo'],
                'average' => $average,
                'count' => $count
            ];
        }
        
        // Sort by average descending for ranking
        uasort($rank_data, function($a, $b) {
            return $b['average'] <=> $a['average'];
        });
        
        $rank = 1;
        foreach ($rank_data as $id => $data) {
            $class_rankings[$id] = [
                'rank' => $rank,
                'average' => $data['average'],
                'name' => $data['name'],
                'roll' => $data['roll'],
                'photo' => $data['photo'],
                'count' => $data['count']
            ];
            $rank++;
        }
    }
    
    // Check if all subjects are locked for this class
    $all_locked = true;
    if (!empty($homeroom_students) && !empty($homeroom_subjects)) {
        foreach ($homeroom_students as $student) {
            foreach ($homeroom_subjects as $subject) {
                if (!isset($homeroom_marks[$student['id']][$subject['id']]) || 
                    $homeroom_marks[$student['id']][$subject['id']]['is_locked'] == 0) {
                    $all_locked = false;
                    break 2;
                }
            }
        }
    }
}

// ============================================
// AJAX HANDLERS
// ============================================

// Save attendance
if (isset($_GET['ajax_attendance'])) {
    $student_id = (int)$_POST['student_id'];
    $date = $_POST['date'] ?? $today;
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("
        INSERT INTO attendance (student_id, date, status, marked_by) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE status = ?, marked_by = ?
    ");
    $success = $stmt->execute([$student_id, $date, $status, $teacher_user_id, $status, $teacher_user_id]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit;
}

// Save marks
if (isset($_GET['ajax_save_mark'])) {
    $student_id = (int)$_POST['student_id'];
    $criteria_name = $_POST['criteria_name'];
    $value = (float)$_POST['value'];
    $term = $_POST['term'] ?? 'Term 1';
    
    $stmt = $pdo->prepare("
        SELECT criteria_data FROM student_marks_custom 
        WHERE student_id = ? AND subject_id = ? AND teacher_id = ? AND term = ?
    ");
    $stmt->execute([$student_id, $teacher_subject_id, $teacher_id, $term]);
    $existing = $stmt->fetch();
    
    $criteria_data = [];
    if ($existing) {
        $criteria_data = json_decode($existing['criteria_data'], true);
    }
    
    $weight = 0;
    foreach ($criteria_list as $c) {
        if ($c['criteria_name'] == $criteria_name) {
            $weight = $c['criteria_weight'];
            break;
        }
    }
    
    $criteria_data[$criteria_name] = min(max($value, 0), $weight);
    
    $total = 0;
    foreach ($criteria_data as $val) {
        $total += $val;
    }
    
    if ($total >= 90) $grade = 'A+';
    elseif ($total >= 80) $grade = 'A';
    elseif ($total >= 70) $grade = 'B+';
    elseif ($total >= 60) $grade = 'B';
    elseif ($total >= 50) $grade = 'C';
    elseif ($total >= 40) $grade = 'D';
    else $grade = 'F';
    
    $stmt = $pdo->prepare("
        INSERT INTO student_marks_custom 
        (student_id, subject_id, teacher_id, term, criteria_data, total_mark, percentage, grade, entered_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            criteria_data = VALUES(criteria_data),
            total_mark = VALUES(total_mark),
            percentage = VALUES(percentage),
            grade = VALUES(grade),
            entered_by = VALUES(entered_by),
            updated_at = NOW()
    ");
    
    $success = $stmt->execute([
        $student_id, 
        $teacher_subject_id, 
        $teacher_id, 
        $term, 
        json_encode($criteria_data), 
        $total,
        $total,
        $grade,
        $teacher_user_id
    ]);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'total' => number_format($total, 1),
        'grade' => $grade
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - Bori Secondary School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f8fafc;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1600px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .teacher-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            border-left: 5px solid #667eea;
        }
        
        .teacher-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }
        
        .teacher-avatar-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .notification-badge {
            background: #f44336;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            position: absolute;
            top: -5px;
            right: -5px;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            position: relative;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            position: relative;
        }
        
        .tab-btn.active {
            background: #667eea;
            color: white;
        }
        
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .class-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 5px;
        }
        
        .class-tab {
            padding: 8px 15px;
            background: white;
            border-radius: 20px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
        }
        
        .class-tab.active {
            background: #667eea;
            color: white;
        }
        
        .week-nav {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 5px;
        }
        
        .btn {
            padding: 8px 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.9rem;
        }
        
        .btn-success {
            background: #4CAF50;
        }
        
        .btn-warning {
            background: #ff9800;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f1f5f9;
            padding: 12px;
            text-align: center;
            border-bottom: 2px solid #ddd;
        }
        
        td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        
        .student-cell {
            text-align: left;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }
        
        .student-avatar-placeholder {
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
        
        .today-highlight {
            background: #fff3e0;
            font-weight: bold;
        }
        
        .attendance-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin: 0 2px;
            border: none;
        }
        
        .present {
            background: #4CAF50;
            color: white;
        }
        
        .permission {
            background: #FF9800;
            color: white;
        }
        
        .absent {
            background: #f44336;
            color: white;
        }
        
        .selected {
            border: 3px solid #333;
            transform: scale(1.1);
        }
        
        .mark-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background: #667eea;
            color: white;
        }
        
        .badge-warning {
            background: #ff9800;
            color: white;
        }
        
        .badge-success {
            background: #4CAF50;
            color: white;
        }
        
        .badge-danger {
            background: #f44336;
            color: white;
        }
        
        .homeroom-badge {
            background: #ff6b6b;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        /* Question Cards */
        .questions-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .question-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }
        
        .question-card.pending {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .student-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .question-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .question-text {
            margin: 15px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        
        .answer-form {
            margin-top: 15px;
        }
        
        .answer-form textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .answer-box {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            border-left: 3px solid #4CAF50;
        }
        
        /* Homeroom specific styles */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .rank-1 {
            background: #ffd700;
            color: #333;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .rank-2 {
            background: #c0c0c0;
            color: #333;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .rank-3 {
            background: #cd7f32;
            color: white;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .locked-badge {
            background: #94a3b8;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        
        .subject-tag {
            background: #e8eaf6;
            color: #667eea;
            padding: 8px 15px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin: 5px;
        }
        
        .subject-tag img {
            width: 25px;
            height: 25px;
            border-radius: 50%;
        }
        
        /* Modal */
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
            border-radius: 10px;
            max-width: 600px;
            width: 100%;
            padding: 30px;
            max-height: 80vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-chalkboard-teacher"></i> 
            <?php echo htmlspecialchars($teacher_name); ?>
            <span style="font-size: 0.8rem; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 20px; margin-left: 10px;">
                <?php echo htmlspecialchars($teacher_subject_name); ?>
            </span>
            <?php if ($is_homeroom): ?>
                <span class="homeroom-badge"><i class="fas fa-house-user"></i> Homeroom - Class <?php echo $homeroom_class['class_name']; ?></span>
            <?php endif; ?>
        </h1>
        <div>
            <?php echo date('l, F j, Y'); ?> | 
            <a href="logout.php" style="color: white; text-decoration: none; margin-left: 15px;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <!-- Teacher Info with Photo -->
        <div class="teacher-info">
            <?php if ($teacher_photo): ?>
                <img src="<?php echo $teacher_photo; ?>" class="teacher-avatar" alt="<?php echo htmlspecialchars($teacher_name); ?>">
            <?php else: ?>
                <div class="teacher-avatar-placeholder">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
            <?php endif; ?>
            <div>
                <h2><?php echo htmlspecialchars($teacher_name); ?></h2>
                <p>
                    <strong>Subject:</strong> <?php echo htmlspecialchars($teacher_subject_name); ?> | 
                    <strong>Phone:</strong> <?php echo htmlspecialchars($teacher_phone); ?>
                </p>
            </div>
        </div>
        
        <?php if($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('attendance')"><i class="fas fa-calendar-check"></i> Attendance</button>
            <button class="tab-btn" onclick="showTab('marks')"><i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($teacher_subject_name); ?> Marks</button>
            <button class="tab-btn" onclick="showTab('questions')">
                <i class="fas fa-question-circle"></i> Questions 
                <?php if ($unanswered_count > 0): ?>
                    <span style="background: #f44336; color: white; border-radius: 50%; padding: 2px 8px; margin-left: 5px; font-size: 0.8rem;">
                        <?php echo $unanswered_count; ?>
                    </span>
                <?php endif; ?>
            </button>
            <?php if ($is_homeroom): ?>
                <button class="tab-btn" onclick="showTab('homeroom')"><i class="fas fa-house-user"></i> Homeroom Dashboard</button>
            <?php endif; ?>
        </div>
        
        <!-- ATTENDANCE TAB -->
        <div id="attendance-tab" class="tab-content active">
            <?php if (!empty($assigned_classes)): ?>
            <div class="class-tabs">
                <?php foreach ($assigned_classes as $c): 
                    $class_display = is_array($c) ? ($c['class_name'] ?? ($c['grade'] . $c['class_section'])) : $c;
                ?>
                <a href="?class=<?php echo urlencode($class_display); ?>&week_start=<?php echo $week_start; ?>" 
                   class="class-tab <?php echo $selected_class === $class_display ? 'active' : ''; ?>">
                    Class <?php echo htmlspecialchars($class_display); ?>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div class="week-nav">
                <a href="?class=<?php echo urlencode($selected_class); ?>&week_start=<?php echo date('Y-m-d', strtotime($week_start . ' -7 days')); ?>" class="btn">
                    ← Previous Week
                </a>
                <span style="font-weight: bold;">
                    <?php echo date('M d', strtotime($week_dates[0]['date'])); ?> - <?php echo date('M d, Y', strtotime($week_dates[4]['date'])); ?>
                </span>
                <a href="?class=<?php echo urlencode($selected_class); ?>&week_start=<?php echo date('Y-m-d', strtotime('monday this week')); ?>" class="btn">
                    This Week →
                </a>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Roll</th>
                        <?php foreach ($week_dates as $day): ?>
                            <th class="<?php echo $day['is_today'] ? 'today-highlight' : ''; ?>">
                                <?php echo $day['day_name']; ?><br>
                                <span style="font-size: 1.2rem;"><?php echo $day['day_num']; ?></span>
                                <?php if($day['is_today']): ?>
                                    <br><small style="color: #ff6b6b;">TODAY</small>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 50px; color: #999;">
                                <i class="fas fa-info-circle"></i> No students in this class
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): 
                            $roll_display = preg_match('/^BS\d+$/', $student['roll_number']) ? $student['roll_number'] : 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td class="student-cell">
                                <div class="student-info">
                                    <?php if ($student['student_photo']): ?>
                                        <img src="<?php echo $student['student_photo']; ?>" class="student-avatar">
                                    <?php else: ?>
                                        <div class="student-avatar-placeholder">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                </div>
                            </td>
                            <td><strong><?php echo $roll_display; ?></strong></td>
                            
                            <?php foreach ($week_dates as $day): 
                                $status = $attendance_data[$student['id']][$day['date']] ?? '';
                            ?>
                                <td class="<?php echo $day['is_today'] ? 'today-highlight' : ''; ?>">
                                    <div style="display: flex; gap: 5px; justify-content: center;">
                                        <div onclick="markAttendance(<?php echo $student['id']; ?>, '<?php echo $day['date']; ?>', 'Present', this)"
                                             class="attendance-btn present <?php echo $status == 'Present' ? 'selected' : ''; ?>">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div onclick="markAttendance(<?php echo $student['id']; ?>, '<?php echo $day['date']; ?>', 'Permission', this)"
                                             class="attendance-btn permission <?php echo $status == 'Permission' ? 'selected' : ''; ?>">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div onclick="markAttendance(<?php echo $student['id']; ?>, '<?php echo $day['date']; ?>', 'Absent', this)"
                                             class="attendance-btn absent <?php echo $status == 'Absent' ? 'selected' : ''; ?>">
                                            <i class="fas fa-times"></i>
                                        </div>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="display: flex; gap: 20px; margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #4CAF50; border-radius: 50%;"></span> Present</div>
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #FF9800; border-radius: 50%;"></span> Permission</div>
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #f44336; border-radius: 50%;"></span> Absent</div>
                <div><span style="display: inline-block; width: 20px; height: 20px; background: #fff3e0; border: 1px solid #ff9800;"></span> Today</div>
            </div>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 10px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                    <h3>No Classes Assigned</h3>
                    <p>You haven't been assigned to any classes yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- MARKS TAB -->
        <div id="marks-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-chart-line"></i> <?php echo htmlspecialchars($teacher_subject_name); ?> Marks - Class <?php echo $selected_class; ?>
                <?php if ($subject_locked): ?>
                    <span class="badge badge-primary"><i class="fas fa-lock"></i> Locked</span>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($criteria_list)): ?>
                <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 10px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                    <h3>No Marking Criteria Set</h3>
                    <p>Please contact admin to set up marking criteria.</p>
                </div>
            <?php elseif (empty($students)): ?>
                <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 10px;">
                    <i class="fas fa-users" style="font-size: 3rem; color: #999; margin-bottom: 15px;"></i>
                    <h3>No Students in Class</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Roll</th>
                            <?php foreach ($criteria_list as $c): ?>
                                <th><?php echo $c['criteria_name']; ?><br><small>(<?php echo $c['criteria_weight']; ?>%)</small></th>
                            <?php endforeach; ?>
                            <th>Total</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $marks = $student_marks[$student['id']] ?? ['criteria_data' => [], 'total' => 0, 'grade' => 'N/A', 'is_locked' => false];
                            $criteria_data = $marks['criteria_data'];
                            $total = $marks['total'] ?? 0;
                            $grade = $marks['grade'] ?? 'N/A';
                            $is_locked = $marks['is_locked'] ?? false;
                            $roll_display = preg_match('/^BS\d+$/', $student['roll_number']) ? $student['roll_number'] : 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
                        ?>
                        <tr>
                            <td class="student-cell">
                                <div class="student-info">
                                    <?php if ($student['student_photo']): ?>
                                        <img src="<?php echo $student['student_photo']; ?>" class="student-avatar">
                                    <?php else: ?>
                                        <div class="student-avatar-placeholder">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                </div>
                            </td>
                            <td><strong><?php echo $roll_display; ?></strong></td>
                            
                            <?php foreach ($criteria_list as $c): 
                                $criteria_name = $c['criteria_name'];
                                $weight = $c['criteria_weight'];
                                $value = $criteria_data[$criteria_name] ?? '';
                            ?>
                            <td>
                                <input type="number" step="0.1" min="0" max="<?php echo $weight; ?>" 
                                       class="mark-input" 
                                       data-student="<?php echo $student['id']; ?>"
                                       data-criteria="<?php echo htmlspecialchars($criteria_name); ?>"
                                       value="<?php echo htmlspecialchars($value); ?>"
                                       <?php echo $is_locked ? 'disabled' : ''; ?>>
                            </td>
                            <?php endforeach; ?>
                            
                            <td><strong><?php echo number_format($total, 1); ?>%</strong></td>
                            <td><span class="badge" style="background: #e8f5e9;"><?php echo $grade; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- QUESTIONS TAB -->
        <div id="questions-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-question-circle"></i> Questions from Parents
                <?php if ($unanswered_count > 0): ?>
                    <span class="badge badge-warning" style="font-size: 1rem;"><?php echo $unanswered_count; ?> pending</span>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($questions)): ?>
                <div style="text-align: center; padding: 60px; background: #f8f9fa; border-radius: 10px;">
                    <i class="fas fa-comments" style="font-size: 4rem; color: #667eea; margin-bottom: 20px;"></i>
                    <h3>No Questions Yet</h3>
                    <p>When parents ask questions, they will appear here.</p>
                </div>
            <?php else: ?>
                <div class="questions-container">
                    <?php foreach ($questions as $q): ?>
                        <div class="question-card <?php echo $q['status'] == 'pending' ? 'pending' : ''; ?>">
                            <div class="question-header">
                                <div class="student-meta">
                                    <?php if (!empty($q['student_photo'])): ?>
                                        <img src="<?php echo $q['student_photo']; ?>" class="question-avatar">
                                    <?php else: ?>
                                        <div style="width:40px; height:40px; border-radius:50%; background:#667eea; display:flex; align-items:center; justify-content:center; color:white;">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($q['student_name']); ?></strong>
                                        <br>
                                        <small>Parent: <?php echo htmlspecialchars($q['parent_name']); ?></small>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($q['status'] == 'pending'): ?>
                                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i> Answered</span>
                                    <?php endif; ?>
                                    <br>
                                    <small><?php echo date('d M Y', strtotime($q['created_at'])); ?></small>
                                </div>
                            </div>
                            
                            <div class="question-text">
                                <strong>Question:</strong> <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                            </div>
                            
                            <?php if (!empty($q['answer_text'])): ?>
                                <div class="answer-box">
                                    <strong>Your Answer:</strong>
                                    <p style="margin-top: 5px;"><?php echo nl2br(htmlspecialchars($q['answer_text'])); ?></p>
                                    <small>Answered on <?php echo date('d M Y', strtotime($q['answered_at'])); ?></small>
                                </div>
                            <?php else: ?>
                                <form method="POST" class="answer-form">
                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                    <textarea name="answer_text" rows="3" placeholder="Type your answer here..." required></textarea>
                                    <button type="submit" name="answer_question" class="btn btn-success">
                                        <i class="fas fa-reply"></i> Submit Answer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- HOMEROOM DASHBOARD TAB - FULLY FUNCTIONAL -->
        <?php if ($is_homeroom): ?>
        <div id="homeroom-tab" class="tab-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #ff6b6b;">
                    <i class="fas fa-house-user"></i> Homeroom Dashboard - Class <?php echo $homeroom_class['class_name']; ?>
                </h2>
                <div>
                    <?php if (isset($all_locked) && $all_locked): ?>
                        <span class="badge badge-success"><i class="fas fa-lock"></i> All Subjects Locked</span>
                    <?php else: ?>
                        <span class="badge badge-warning"><i class="fas fa-lock-open"></i> Some Subjects Unlocked</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($homeroom_students)): ?>
                <div style="text-align: center; padding: 60px; background: #f8f9fa; border-radius: 10px;">
                    <i class="fas fa-users" style="font-size: 4rem; color: #999; margin-bottom: 20px;"></i>
                    <h3>No Students in Class</h3>
                    <p>This class has no students yet.</p>
                </div>
            <?php elseif (empty($homeroom_subjects)): ?>
                <div style="text-align: center; padding: 60px; background: #f8f9fa; border-radius: 10px;">
                    <i class="fas fa-book" style="font-size: 4rem; color: #999; margin-bottom: 20px;"></i>
                    <h3>No Subjects Assigned</h3>
                    <p>Please assign subject teachers to this class first.</p>
                </div>
            <?php else: ?>
                
                <!-- Subject Teachers List -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px; color: #667eea;">
                        <i class="fas fa-chalkboard-teacher"></i> Subject Teachers
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($homeroom_subjects as $subject): ?>
                            <div class="subject-tag">
                                <?php if (!empty($subject['teacher_photo'])): ?>
                                    <img src="<?php echo $subject['teacher_photo']; ?>" style="width:25px; height:25px; border-radius:50%;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                                <span>
                                    <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($subject['teacher_name']); ?></small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($homeroom_students); ?></div>
                        <div>Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($homeroom_subjects); ?></div>
                        <div>Subjects</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                            $completed = 0;
                            foreach ($homeroom_students as $student) {
                                $has_all = true;
                                foreach ($homeroom_subjects as $subject) {
                                    if (!isset($homeroom_marks[$student['id']][$subject['id']])) {
                                        $has_all = false;
                                        break;
                                    }
                                }
                                if ($has_all) $completed++;
                            }
                            echo $completed . '/' . count($homeroom_students);
                            ?>
                        </div>
                        <div>Fully Assessed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php 
                            $class_avg = 0;
                            $count = 0;
                            foreach ($class_rankings as $data) {
                                if ($data['average'] > 0) {
                                    $class_avg += $data['average'];
                                    $count++;
                                }
                            }
                            echo $count > 0 ? number_format($class_avg / $count, 1) . '%' : '0%';
                            ?>
                        </div>
                        <div>Class Average</div>
                    </div>
                </div>
                
                <!-- Student Performance Table with Rankings -->
                <h3 style="margin: 30px 0 15px; color: #667eea;">
                    <i class="fas fa-chart-line"></i> Student Performance & Rankings
                </h3>
                
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Roll</th>
                            <?php foreach ($homeroom_subjects as $subject): ?>
                                <th>
                                    <?php echo htmlspecialchars($subject['subject_name']); ?><br>
                                    <small><?php echo htmlspecialchars($subject['teacher_name']); ?></small>
                                </th>
                            <?php endforeach; ?>
                            <th>Average</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sorted_students = $homeroom_students;
                        usort($sorted_students, function($a, $b) use ($class_rankings) {
                            $rankA = $class_rankings[$a['id']]['rank'] ?? 999;
                            $rankB = $class_rankings[$b['id']]['rank'] ?? 999;
                            return $rankA <=> $rankB;
                        });
                        
                        foreach ($sorted_students as $student): 
                            $student_id = $student['id'];
                            $rank_data = $class_rankings[$student_id] ?? ['rank' => '-', 'average' => 0];
                            $roll_display = preg_match('/^BS\d+$/', $student['roll_number']) ? $student['roll_number'] : 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
                            
                            // Determine rank class
                            $rank_class = '';
                            if ($rank_data['rank'] == 1) $rank_class = 'rank-1';
                            elseif ($rank_data['rank'] == 2) $rank_class = 'rank-2';
                            elseif ($rank_data['rank'] == 3) $rank_class = 'rank-3';
                        ?>
                        <tr>
                            <td>
                                <?php if ($rank_data['rank'] != '-'): ?>
                                    <span class="<?php echo $rank_class; ?>">
                                        #<?php echo $rank_data['rank']; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="student-cell">
                                <div class="student-info">
                                    <?php if ($student['student_photo']): ?>
                                        <img src="<?php echo $student['student_photo']; ?>" class="student-avatar">
                                    <?php else: ?>
                                        <div class="student-avatar-placeholder">
                                            <i class="fas fa-user-graduate"></i>
                                        </div>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                </div>
                            </td>
                            <td><strong><?php echo $roll_display; ?></strong></td>
                            
                            <?php foreach ($homeroom_subjects as $subject): 
                                $mark = $homeroom_marks[$student_id][$subject['id']] ?? null;
                            ?>
                                <td>
                                    <?php if ($mark): ?>
                                        <div>
                                            <strong style="color: <?php 
                                                echo $mark['percentage'] >= 80 ? '#4CAF50' : 
                                                    ($mark['percentage'] >= 60 ? '#FF9800' : '#f44336'); 
                                            ?>;">
                                                <?php echo number_format($mark['percentage'], 1); ?>%
                                            </strong>
                                            <br>
                                            <small><?php echo $mark['grade']; ?></small>
                                            <?php if ($mark['is_locked']): ?>
                                                <br><span class="locked-badge"><i class="fas fa-lock"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            
                            <td>
                                <?php if ($rank_data['average'] > 0): ?>
                                    <strong style="color: <?php 
                                        echo $rank_data['average'] >= 80 ? '#4CAF50' : 
                                            ($rank_data['average'] >= 60 ? '#FF9800' : '#f44336'); 
                                    ?>;">
                                        <?php echo number_format($rank_data['average'], 1); ?>%
                                    </strong>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $all_locked_for_student = true;
                                foreach ($homeroom_subjects as $subject) {
                                    if (!isset($homeroom_marks[$student_id][$subject['id']]) || 
                                        $homeroom_marks[$student_id][$subject['id']]['is_locked'] == 0) {
                                        $all_locked_for_student = false;
                                        break;
                                    }
                                }
                                ?>
                                <?php if ($all_locked_for_student): ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Complete</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> In Progress</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Lock Status Summary -->
                <div style="margin-top: 30px; background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <h4 style="margin-bottom: 15px; color: #667eea;">
                        <i class="fas fa-lock"></i> Subject Lock Status
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($homeroom_subjects as $subject): 
                            $locked_count = 0;
                            $total_count = count($homeroom_students);
                            foreach ($homeroom_students as $student) {
                                if (isset($homeroom_marks[$student['id']][$subject['id']]) && 
                                    $homeroom_marks[$student['id']][$subject['id']]['is_locked'] == 1) {
                                    $locked_count++;
                                }
                            }
                            $percentage = $total_count > 0 ? round(($locked_count / $total_count) * 100) : 0;
                        ?>
                            <div style="background: white; padding: 15px; border-radius: 8px;">
                                <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                <div style="margin-top: 10px;">
                                    <div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo $percentage; ?>%; height: 8px; background: <?php echo $percentage == 100 ? '#4CAF50' : '#FF9800'; ?>;"></div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 0.8rem;">
                                        <span>Locked: <?php echo $locked_count; ?>/<?php echo $total_count; ?></span>
                                        <span><?php echo $percentage; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function markAttendance(studentId, date, status, element) {
            const parent = element.parentNode;
            const buttons = parent.children;
            
            for(let i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('selected');
            }
            
            element.classList.add('selected');
            
            const formData = new FormData();
            formData.append('student_id', studentId);
            formData.append('date', date);
            formData.append('status', status);
            
            fetch('?ajax_attendance=1', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    console.log('Attendance saved');
                }
            });
        }
        
        // Auto-save marks
        document.querySelectorAll('.mark-input').forEach(input => {
            input.addEventListener('change', function() {
                const studentId = this.dataset.student;
                const criteriaName = this.dataset.criteria;
                const value = this.value;
                
                const formData = new FormData();
                formData.append('student_id', studentId);
                formData.append('criteria_name', criteriaName);
                formData.append('value', value);
                
                fetch('?ajax_save_mark=1', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        this.style.borderColor = '#4CAF50';
                        setTimeout(() => this.style.borderColor = '#ddd', 1000);
                        
                        const row = this.closest('tr');
                        if(row) {
                            const totalCell = row.querySelector('td:nth-last-child(2)');
                            const gradeCell = row.querySelector('td:last-child');
                            if(totalCell) totalCell.innerHTML = `<strong>${data.total}%</strong>`;
                            if(gradeCell) gradeCell.innerHTML = `<span class="badge" style="background: #e8f5e9;">${data.grade}</span>`;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>