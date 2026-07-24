<?php
require_once 'config.php';

if (isset($_GET['grade'])) {
    $grade = $_GET['grade'];
    
    $stmt = $pdo->prepare("SELECT DISTINCT section FROM classes WHERE grade = ? ORDER BY section");
    $stmt->execute([$grade]);
    $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode($sections);
}
?>