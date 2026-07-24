<?php
require_once 'config.php';
requireLogin();

// Only teachers and admins can unlock
if (!in_array($_SESSION['role'], ['teacher', 'superadmin', 'registration'])) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Get teacher's students
if (isset($_GET['get_teacher_students'])) {
    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    
    if (!$teacher_id) {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.roll_number, CONCAT(s.grade, s.class_section) as class,
               (SELECT COUNT(*) FROM parent_access pa WHERE pa.student_id = s.id AND pa.is_active = 1 AND pa.unlocked_until > NOW()) as is_unlocked
        FROM students s
        WHERE s.teacher_id = ?
        ORDER BY s.grade, s.class_section, s.name
    ");
    $stmt->execute([$teacher_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'students' => $students]);
    exit;
}

// Get classes for teacher (whole class unlock)
if (isset($_GET['get_teacher_classes'])) {
    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    
    if (!$teacher_id) {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT CONCAT(s.grade, s.class_section) as class_name, s.grade, s.class_section,
               COUNT(*) as student_count
        FROM students s
        WHERE s.teacher_id = ?
        GROUP BY s.grade, s.class_section
    ");
    $stmt->execute([$teacher_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'classes' => $classes]);
    exit;
}

// Unlock student(s)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $duration = (int)($_POST['duration'] ?? 7); // days
    $unlocked_until = date('Y-m-d H:i:s', strtotime("+$duration days"));
    
    if ($action == 'unlock_single') {
        $student_id = (int)$_POST['student_id'];
        
        // Deactivate any existing unlocks
        $pdo->prepare("UPDATE parent_access SET is_active = 0 WHERE student_id = ?")->execute([$student_id]);
        
        // Create new unlock
        $stmt = $pdo->prepare("
            INSERT INTO parent_access (student_id, unlocked_by, unlocked_until, access_type)
            VALUES (?, ?, ?, 'single')
        ");
        $success = $stmt->execute([$student_id, $_SESSION['user_id'], $unlocked_until]);
        
        echo json_encode(['success' => $success, 'message' => 'Student unlocked for ' . $duration . ' days']);
        
    } elseif ($action == 'unlock_class') {
        $grade = $_POST['grade'];
        $section = $_POST['section'];
        $teacher_id = $_SESSION['teacher_id'] ?? 0;
        
        // Get all students in this class
        $stmt = $pdo->prepare("
            SELECT id FROM students 
            WHERE grade = ? AND class_section = ? AND teacher_id = ?
        ");
        $stmt->execute([$grade, $section, $teacher_id]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $pdo->beginTransaction();
        try {
            foreach ($students as $student_id) {
                // Deactivate existing
                $pdo->prepare("UPDATE parent_access SET is_active = 0 WHERE student_id = ?")->execute([$student_id]);
                
                // Create new
                $pdo->prepare("
                    INSERT INTO parent_access (student_id, unlocked_by, unlocked_until, access_type)
                    VALUES (?, ?, ?, 'whole_class')
                ")->execute([$student_id, $_SESSION['user_id'], $unlocked_until]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Whole class unlocked for ' . $duration . ' days']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        
    } elseif ($action == 'lock_student') {
        $student_id = (int)$_POST['student_id'];
        $stmt = $pdo->prepare("UPDATE parent_access SET is_active = 0 WHERE student_id = ?");
        $success = $stmt->execute([$student_id]);
        echo json_encode(['success' => $success, 'message' => 'Student locked']);
        
    } elseif ($action == 'lock_class') {
        $grade = $_POST['grade'];
        $section = $_POST['section'];
        $teacher_id = $_SESSION['teacher_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            UPDATE parent_access pa 
            JOIN students s ON pa.student_id = s.id
            SET pa.is_active = 0
            WHERE s.grade = ? AND s.class_section = ? AND s.teacher_id = ?
        ");
        $success = $stmt->execute([$grade, $section, $teacher_id]);
        echo json_encode(['success' => $success, 'message' => 'Class locked']);
    }
    exit;
}
?>