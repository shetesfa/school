<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
    exit;
}

// Get last 120 days of attendance
$start_date = date('Y-m-d', strtotime('-120 days'));
$end_date = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT date, status 
    FROM attendance 
    WHERE student_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$stmt->execute([$student_id, $start_date, $end_date]);

$attendance = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attendance[$row['date']] = $row['status'];
}

echo json_encode([
    'success' => true,
    'start' => $start_date,
    'end' => $end_date,
    'attendance' => $attendance
]);
?>