<?php
header('Content-Type: application/json');
require_once 'config.php';

$student_id = $_GET['student_id'] ?? 0;
if (!$student_id || !is_numeric($student_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid student']);
    exit;
}

$days = 120;
$start = date('Y-m-d', strtotime("-$days days"));
$end   = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT date, status 
    FROM attendance 
    WHERE student_id = ? 
      AND date BETWEEN ? AND ?
    ORDER BY date
");
$stmt->execute([$student_id, $start, $end]);

$data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[$row['date']] = $row['status'];
}

echo json_encode([
    'success' => true,
    'attendance' => $data,
    'start' => $start,
    'end' => $end
]);