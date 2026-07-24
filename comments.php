<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// Get comments for a student
if (isset($_GET['get_comments']) && isset($_GET['student_id'])) {
    $student_id = (int)$_GET['student_id'];
    $role = $_SESSION['role'];
    
    // Different visibility based on role
    if ($role == 'parent') {
        // Parents only see non-private comments when unlocked
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   u.name as author_name,
                   sub.name as subject_name
            FROM comments c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN subjects sub ON c.subject_id = sub.id
            WHERE c.student_id = ? AND c.is_private = 0
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$student_id]);
        
    } elseif ($role == 'teacher') {
        // Teachers see all comments
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   u.name as author_name,
                   sub.name as subject_name
            FROM comments c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN subjects sub ON c.subject_id = sub.id
            WHERE c.student_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$student_id]);
        
    } elseif ($role == 'superadmin' || $role == 'registration') {
        // Admins see all
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   u.name as author_name,
                   sub.name as subject_name
            FROM comments c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN subjects sub ON c.subject_id = sub.id
            WHERE c.student_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$student_id]);
    }
    
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}

// Add comment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_comment'])) {
    $student_id = (int)$_POST['student_id'];
    $comment_text = trim($_POST['comment_text']);
    $comment_type = $_POST['comment_type']; // subject_teacher, homeroom, director_warning, director_praise
    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] ? (int)$_POST['subject_id'] : null;
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    
    if (!$student_id || !$comment_text) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    // Get teacher_id if user is teacher
    $teacher_id = null;
    if ($_SESSION['role'] == 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $teacher_id = $stmt->fetchColumn();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO comments (student_id, teacher_id, subject_id, comment_type, comment_text, is_private, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $student_id,
        $teacher_id,
        $subject_id,
        $comment_type,
        $comment_text,
        $is_private,
        $_SESSION['user_id']
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add comment']);
    }
    exit;
}

// Delete comment (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_comment'])) {
    if (!in_array($_SESSION['role'], ['superadmin', 'registration'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $comment_id = (int)$_POST['comment_id'];
    
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    $success = $stmt->execute([$comment_id]);
    
    echo json_encode(['success' => $success]);
    exit;
}
?>