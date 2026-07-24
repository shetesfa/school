<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

if (!$student_id) {
    echo json_encode(['comments' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, u.name as author_name, sub.name as subject_name
    FROM comments c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN subjects sub ON c.subject_id = sub.id
    WHERE c.student_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$student_id]);
$comments = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode(['comments' => $comments]);