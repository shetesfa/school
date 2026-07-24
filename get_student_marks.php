<?php
require_once 'config.php';

if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $subject_id = $_GET['subject_id'] ?? null;
    
    $query = "SELECT * FROM student_marks_detail WHERE student_id = ?";
    $params = [$student_id];
    
    if ($subject_id) {
        $query .= " AND subject_id = ?";
        $params[] = $subject_id;
    }
    
    $query .= " ORDER BY term DESC LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $marks = $stmt->fetch();
    
    header('Content-Type: application/json');
    echo json_encode($marks ?: []);
}
?>