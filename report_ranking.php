<?php
require_once 'config.php';
requireLogin();

$class_id = (int)($_GET['class_id'] ?? 0);
$term = sanitize($_GET['term'] ?? 'Term 1');
$year = sanitize($_GET['year'] ?? '2025/2026');

$schoolName = getSchoolSetting($pdo, 'school_name', 'EduTrack International School');

// Get classes
if ($class_id) {
    $clq = $pdo->prepare("SELECT * FROM classes WHERE id=?");
    $clq->execute([$class_id]);
    $classes = [$clq->fetch()];
} else {
    $classes = $pdo->query("SELECT * FROM classes ORDER BY grade, section")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ranking Report</title>
<style>
body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;padding:20px;color:#111}
.no-print{margin-bottom:20px;display:flex;gap:10px}
.btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:14px}
.btn-print{background:#6366f1;color:#fff}
.card{background:#fff;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
table{width:100%;border-collapse:collapse}
th{background:#1e1b4b;color:#fff;padding:10px;font-size:11px;text-align:left}
td{padding:10px;border-bottom:1px solid #e5e7eb;font-size:13px}
tr:nth-child(even) td{background:#f9fafb}
.rank-1{background:#fef9c3!important;font-weight:700}
.rank-2{background:#f1f5f9!important}
.rank-3{background:#fef2f2!important}
.medal{font-size:1.2rem}
@media print{.no-print{display:none}.card{box-shadow:none;break-inside:avoid}}
</style>
</head>
<body>
<div class="no-print">
  <button class="btn btn-print" onclick="window.print()">🖨️ Print Rankings</button>
  <button class="btn" onclick="window.close()" style="background:#f1f5f9;border:1px solid #e2e8f0">✕ Close</button>
</div>

<?php foreach($classes as $cl): if(!$cl) continue;
$students = $pdo->prepare("
    SELECT s.id, s.name, s.roll_number,
           COALESCE(AVG(smc.percentage),0) AS avg_pct,
           COUNT(smc.id) AS subjects_count,
           SUM(smc.total_mark) AS total_marks
    FROM students s
    LEFT JOIN student_marks_custom smc ON smc.student_id=s.id AND smc.term=? AND smc.academic_year=?
    WHERE s.grade=? AND s.class_section=? AND s.status='active'
    GROUP BY s.id ORDER BY avg_pct DESC
");
$students->execute([$term, $year, $cl['grade'], $cl['section']]);
$stuRows = $students->fetchAll();
?>
<div class="card">
  <h2 style="margin-bottom:4px"><?= sanitize($schoolName) ?></h2>
  <p style="color:#888;margin-bottom:16px">Ranking Report · Grade <?= $cl['grade'].$cl['section'] ?> · <?= sanitize($term) ?> <?= sanitize($year) ?></p>
  <table>
    <thead><tr><th>Rank</th><th>Student Name</th><th>ID</th><th>Subjects</th><th>Average</th><th>Grade</th></tr></thead>
    <tbody>
    <?php foreach($stuRows as $i=>$s):
      [$gl,$gc] = getGradeLetter($s['avg_pct']);
      $medals = ['🥇','🥈','🥉'];
    ?>
    <tr class="<?= $i<3?'rank-'.($i+1):'' ?>">
      <td><strong><?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':'#'.($i+1))) ?></strong></td>
      <td><?= sanitize($s['name']) ?></td>
      <td><?= $s['roll_number'] ?></td>
      <td><?= $s['subjects_count'] ?></td>
      <td><strong style="color:<?= $gc ?>"><?= round($s['avg_pct'],2) ?>%</strong></td>
      <td><strong style="color:<?= $gc ?>"><?= $gl ?></strong></td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($stuRows)): ?><tr><td colspan="6" style="text-align:center;padding:20px;color:#888">No data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>
</body>
</html>
