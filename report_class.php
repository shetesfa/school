<?php
require_once 'config.php';
requireLogin();
// Redirect to report_card with class_id
$class_id = (int)($_GET['class_id'] ?? 0);
$term = sanitize($_GET['term'] ?? 'Term 1');
$year = sanitize($_GET['year'] ?? '2025/2026');
header("Location: report_card.php?class_id=$class_id&term=".urlencode($term)."&year=".urlencode($year));
exit();
