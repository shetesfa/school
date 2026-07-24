<?php
require_once 'config.php';
requireLogin();

// Only teachers and admins
if (!in_array($_SESSION['role'], ['teacher', 'superadmin', 'registration'])) {
    header('HTTP/1.0 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Check if all subjects are locked for a class
if (isset($_GET['check_class_locks'])) {
    $class_id = (int)$_GET['class_id'];
    $term = $_GET['term'] ?? 'Term 1';
    $academic_year = $_GET['academic_year'] ?? date('Y') . '/' . (date('Y')+1);
    
    // Get all subjects for this class
    $stmt = $pdo->prepare("
        SELECT DISTINCT sub.id, sub.name
        FROM subjects sub
        WHERE sub.id IN (
            SELECT DISTINCT subject_id FROM student_marks_custom smc
            JOIN students s ON smc.student_id = s.id
            JOIN classes c ON s.grade = c.grade AND s.class_section = c.section
            WHERE c.id = ? AND smc.term = ?
        )
    ");
    $stmt->execute([$class_id, $term]);
    $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check which subjects are locked
    $locked_subjects = [];
    $unlocked_subjects = [];
    
    foreach ($all_subjects as $subject) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_marks_custom smc
            JOIN students s ON smc.student_id = s.id
            JOIN classes c ON s.grade = c.grade AND s.class_section = c.section
            WHERE c.id = ? AND smc.subject_id = ? AND smc.term = ? AND smc.is_locked = 0
        ");
        $stmt->execute([$class_id, $subject['id'], $term]);
        $unlocked_count = $stmt->fetchColumn();
        
        if ($unlocked_count == 0) {
            $locked_subjects[] = $subject;
        } else {
            $unlocked_subjects[] = $subject;
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_subjects' => count($all_subjects),
        'locked_subjects' => $locked_subjects,
        'unlocked_subjects' => $unlocked_subjects,
        'all_locked' => (count($unlocked_subjects) == 0)
    ]);
    exit;
}

// Lock a subject's marks
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lock_subject'])) {
    $subject_id = (int)$_POST['subject_id'];
    $teacher_id = $_SESSION['teacher_id'] ?? 0;
    $term = $_POST['term'] ?? 'Term 1';
    
    if (!$teacher_id) {
        echo json_encode(['success' => false, 'error' => 'Teacher not found']);
        exit;
    }
    
    // Verify this teacher owns this subject
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE id = ? AND subject_id = ?");
    $stmt->execute([$teacher_id, $subject_id]);
    if (!$stmt->fetch() && $_SESSION['role'] != 'superadmin') {
        echo json_encode(['success' => false, 'error' => 'You do not own this subject']);
        exit;
    }
    
    // Lock all marks for this subject by this teacher
    $stmt = $pdo->prepare("
        UPDATE student_marks_custom 
        SET is_locked = 1, locked_by = ?, locked_at = NOW()
        WHERE subject_id = ? AND teacher_id = ? AND term = ?
    ");
    $success = $stmt->execute([$_SESSION['user_id'], $subject_id, $teacher_id, $term]);
    
    echo json_encode(['success' => $success, 'message' => 'Subject locked successfully']);
    exit;
}

// Calculate class ranks (only when all subjects locked)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calculate_ranks'])) {
    $class_id = (int)$_POST['class_id'];
    $term = $_POST['term'] ?? 'Term 1';
    $academic_year = $_POST['academic_year'] ?? date('Y') . '/' . (date('Y')+1);
    
    // Verify all subjects are locked
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT smc.subject_id) 
        FROM student_marks_custom smc
        JOIN students s ON smc.student_id = s.id
        WHERE s.grade = (SELECT grade FROM classes WHERE id = ?)
        AND s.class_section = (SELECT section FROM classes WHERE id = ?)
        AND smc.term = ? AND smc.is_locked = 0
    ");
    $stmt->execute([$class_id, $class_id, $term]);
    $unlocked_subjects = $stmt->fetchColumn();
    
    if ($unlocked_subjects > 0) {
        echo json_encode(['success' => false, 'error' => 'All subjects must be locked first']);
        exit;
    }
    
    // Get all students in this class
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.roll_number,
               AVG(smc.percentage) as total_percentage
        FROM students s
        LEFT JOIN student_marks_custom smc ON s.id = smc.student_id AND smc.term = ?
        WHERE s.grade = (SELECT grade FROM classes WHERE id = ?)
        AND s.class_section = (SELECT section FROM classes WHERE id = ?)
        GROUP BY s.id
        ORDER BY total_percentage DESC
    ");
    $stmt->execute([$term, $class_id, $class_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delete old rankings
    $pdo->prepare("DELETE FROM class_rankings WHERE class_id = ? AND term = ? AND academic_year = ?")
        ->execute([$class_id, $term, $academic_year]);
    
    // Insert new rankings
    $rank = 1;
    $pdo->beginTransaction();
    try {
        foreach ($students as $student) {
            if ($student['total_percentage'] > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO class_rankings (class_id, student_id, term, academic_year, total_percentage, rank_position)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $class_id,
                    $student['id'],
                    $term,
                    $academic_year,
                    $student['total_percentage'],
                    $rank
                ]);
                $rank++;
            }
        }
        
        // Record that semester is finalized
        $stmt = $pdo->prepare("
            INSERT INTO semester_locks (class_id, term, academic_year, locked_by, is_finalized)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE is_finalized = 1, locked_by = VALUES(locked_by), locked_at = NOW()
        ");
        $stmt->execute([$class_id, $term, $academic_year, $_SESSION['user_id']]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ranks calculated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get class ranks
if (isset($_GET['get_ranks'])) {
    $class_id = (int)$_GET['class_id'];
    $term = $_GET['term'] ?? 'Term 1';
    $academic_year = $_GET['academic_year'] ?? date('Y') . '/' . (date('Y')+1);
    
    $stmt = $pdo->prepare("
        SELECT cr.*, s.name, s.roll_number
        FROM class_rankings cr
        JOIN students s ON cr.student_id = s.id
        WHERE cr.class_id = ? AND cr.term = ? AND cr.academic_year = ?
        ORDER BY cr.rank_position
    ");
    $stmt->execute([$class_id, $term, $academic_year]);
    $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'ranks' => $ranks]);
    exit;
}
?>