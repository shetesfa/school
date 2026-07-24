<?php
require_once 'config.php';
requireLogin();

$student_id = (int)($_GET['student_id'] ?? 0);
$class_id   = (int)($_GET['class_id'] ?? 0);
$term       = sanitize($_GET['term'] ?? 'Term 1');
$year       = sanitize($_GET['year'] ?? '2025/2026');

// Fetch all students for class (batch print) or single student
$students = [];
if ($class_id && !$student_id) {
    $cl = $pdo->prepare("SELECT grade, section FROM classes WHERE id=?");
    $cl->execute([$class_id]);
    $clRow = $cl->fetch();
    if ($clRow) {
        $sq = $pdo->prepare("SELECT * FROM students WHERE grade=? AND class_section=? AND status='active' ORDER BY name");
        $sq->execute([$clRow['grade'], $clRow['section']]);
        $students = $sq->fetchAll();
    }
} elseif ($student_id) {
    $sq = $pdo->prepare("SELECT * FROM students WHERE id=?");
    $sq->execute([$student_id]);
    $stu = $sq->fetch();
    if ($stu) $students = [$stu];
}

if (empty($students)) { echo '<p>No students found.</p>'; exit(); }

$schoolName = getSchoolSetting($pdo, 'school_name', 'EduTrack International School');
$schoolAddress = getSchoolSetting($pdo, 'school_address', '');
$passPercent = (float)getSchoolSetting($pdo, 'pass_percentage', 50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report Card – <?= sanitize($term) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',Arial,sans-serif; background:#fff; color:#111; font-size:12px; }

  .page-break { page-break-after: always; }
  .no-print { position:fixed; top:16px; right:16px; z-index:1000; display:flex; gap:8px; }
  @media print { .no-print { display:none!important; } }

  .report-card { width:210mm; min-height:297mm; margin:0 auto; padding:15mm 15mm 10mm; border:2px solid #1e1b4b; }

  /* Header */
  .rc-header { display:flex; align-items:center; gap:16px; border-bottom:3px double #1e1b4b; padding-bottom:12px; margin-bottom:12px; }
  .rc-logo { width:60px; height:60px; border-radius:50%; background:#1e1b4b; display:flex; align-items:center; justify-content:center; color:#fff; font-size:1.5rem; font-weight:800; flex-shrink:0; }
  .rc-logo img { width:60px; height:60px; border-radius:50%; object-fit:cover; }
  .rc-school-info { flex:1; text-align:center; }
  .rc-school-name { font-size:18px; font-weight:800; color:#1e1b4b; letter-spacing:1px; text-transform:uppercase; }
  .rc-school-sub { font-size:11px; color:#555; margin-top:2px; }
  .rc-title { font-size:14px; font-weight:700; color:#6366f1; margin-top:6px; text-transform:uppercase; letter-spacing:2px; }

  /* Student info */
  .rc-student-info { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:14px; border:1px solid #ddd; padding:10px; border-radius:6px; background:#f9fafb; }
  .info-item { }
  .info-label { font-size:9px; text-transform:uppercase; color:#888; letter-spacing:.5px; }
  .info-value { font-size:12px; font-weight:700; color:#111; margin-top:1px; }

  /* Marks table */
  .marks-table { width:100%; border-collapse:collapse; margin-bottom:14px; }
  .marks-table th { background:#1e1b4b; color:#fff; padding:7px 10px; font-size:10px; text-align:left; text-transform:uppercase; letter-spacing:.5px; }
  .marks-table td { padding:7px 10px; border-bottom:1px solid #e5e7eb; font-size:11px; vertical-align:middle; }
  .marks-table tr:nth-child(even) td { background:#f9fafb; }
  .marks-table tr:last-child td { font-weight:700; background:#f0f4ff; }
  .grade-pill { display:inline-block; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; }
  .pass { color:#15803d; } .fail { color:#dc2626; }

  /* Progress bar */
  .mini-bar { background:#e5e7eb; border-radius:3px; height:6px; overflow:hidden; margin-top:3px; }
  .mini-bar-fill { height:100%; border-radius:3px; }

  /* Summary */
  .rc-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:14px; }
  .summary-box { border:1.5px solid #e5e7eb; border-radius:8px; padding:10px; text-align:center; }
  .summary-num { font-size:20px; font-weight:800; color:#1e1b4b; }
  .summary-lbl { font-size:9px; color:#888; text-transform:uppercase; margin-top:2px; }

  /* Comments */
  .rc-comments { border:1px solid #ddd; border-radius:6px; padding:10px; margin-bottom:14px; background:#f9fafb; }
  .rc-comments h4 { font-size:10px; text-transform:uppercase; color:#888; margin-bottom:6px; }
  .comment-item { padding:6px 0; border-bottom:1px dashed #e5e7eb; font-size:11px; }

  /* Attendance */
  .rc-attendance { display:flex; gap:16px; margin-bottom:14px; flex-wrap:wrap; }
  .att-item { text-align:center; }
  .att-num { font-size:18px; font-weight:800; }
  .att-lbl { font-size:9px; color:#888; text-transform:uppercase; }

  /* Footer */
  .rc-footer { margin-top:auto; border-top:2px solid #1e1b4b; padding-top:12px; display:flex; gap:20px; justify-content:space-around; }
  .sig-line { text-align:center; width:140px; }
  .sig-line .line { border-top:1px solid #333; margin-bottom:4px; }
  .sig-line .label { font-size:9px; color:#666; text-transform:uppercase; }

  .watermark-pass { position:absolute; right:20px; top:50%; transform:rotate(-30deg); font-size:48px; font-weight:900; color:rgba(16,185,129,.08); pointer-events:none; }

  @page { size:A4; margin:0; }
  @media print {
    body { background:#fff; }
    .report-card { width:100%; min-height:100vh; border:none; padding:10mm; }
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="padding:10px 20px;background:#6366f1;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">
    🖨️ Print All
  </button>
  <button onclick="window.close()" style="padding:10px 20px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px">
    ✕ Close
  </button>
</div>

<?php foreach($students as $stuIdx => $stu): ?>
<?php
// Fetch marks
$marks = $pdo->prepare("
    SELECT smc.*, sub.name AS subject_name, sub.color AS subj_color,
           u.name AS teacher_name
    FROM student_marks_custom smc
    JOIN subjects sub ON sub.id=smc.subject_id
    JOIN teachers t ON t.id=smc.teacher_id
    JOIN users u ON u.id=t.user_id
    WHERE smc.student_id=? AND smc.term=? AND smc.academic_year=?
    ORDER BY sub.name
");
$marks->execute([$stu['id'], $term, $year]);
$allMarks = $marks->fetchAll();

$totalPct = 0; $subjectCount = count($allMarks);
$failCount = 0;
foreach ($allMarks as $mk) {
    $totalPct += $mk['percentage'];
    if ($mk['percentage'] < $passPercent) $failCount++;
}
$avgPct = $subjectCount > 0 ? round($totalPct / $subjectCount, 2) : 0;
[$ogl, $ogc] = getGradeLetter($avgPct);
$isPassing = $avgPct >= $passPercent && $failCount == 0;

// Rank
$rankRow = $pdo->prepare("
    SELECT rank_position,
           (SELECT COUNT(*) FROM class_rankings WHERE class_id=cr.class_id AND term=cr.term AND academic_year=cr.academic_year) AS total
    FROM class_rankings cr
    JOIN classes c ON c.id=cr.class_id
    WHERE cr.student_id=? AND cr.term=? AND cr.academic_year=?
    LIMIT 1
");
$rankRow->execute([$stu['id'], $term, $year]);
$rank = $rankRow->fetch();

// Attendance
$att = $pdo->prepare("
    SELECT
        SUM(status='Present') AS present,
        SUM(status='Absent') AS absent,
        SUM(status='Late') AS late,
        SUM(status='Excused') AS excused,
        COUNT(*) AS total
    FROM attendance WHERE student_id=?
    AND date BETWEEN ? AND ?
");
// Get term dates approximately
$attFrom = '2025-09-01'; $attTo = date('Y-m-d');
$att->execute([$stu['id'], $attFrom, $attTo]);
$attData = $att->fetch();
$attPct = $attData['total'] > 0 ? round(($attData['present'] / $attData['total']) * 100, 1) : 0;

// Comments
$comms = $pdo->prepare("
    SELECT c.comment_text, c.type, u.name AS tname FROM comments c
    JOIN teachers t ON t.id=c.teacher_id JOIN users u ON u.id=t.user_id
    WHERE c.student_id=? AND c.term=? AND c.academic_year=?
");
$comms->execute([$stu['id'], $term, $year]);
$comments = $comms->fetchAll();

// Homeroom
$hr = $pdo->prepare("
    SELECT u.name FROM classes c
    JOIN teachers t ON t.id=c.teacher_id JOIN users u ON u.id=t.user_id
    WHERE c.grade=? AND c.section=? LIMIT 1
");
$hr->execute([$stu['grade'], $stu['class_section']]);
$hrtName = $hr->fetchColumn();
?>

<div class="report-card" style="position:relative">
  <!-- Watermark -->
  <div class="watermark-pass"><?= $isPassing ? 'PASS' : 'REVIEW' ?></div>

  <!-- Header -->
  <div class="rc-header">
    <div class="rc-logo">
      <img src="images/logo.png" onerror="this.style.display='none';this.parentElement.textContent='E'" alt="Logo">
    </div>
    <div class="rc-school-info">
      <div class="rc-school-name"><?= sanitize($schoolName) ?></div>
      <div class="rc-school-sub"><?= sanitize($schoolAddress) ?></div>
      <div class="rc-title">Student Report Card</div>
    </div>
    <div style="text-align:right;font-size:10px;color:#555;line-height:1.8">
      <div><strong>Term:</strong> <?= sanitize($term) ?></div>
      <div><strong>Year:</strong> <?= sanitize($year) ?></div>
      <div><strong>Printed:</strong> <?= date('d M Y') ?></div>
    </div>
  </div>

  <!-- Student Info -->
  <div class="rc-student-info">
    <div class="info-item">
      <div class="info-label">Full Name</div>
      <div class="info-value"><?= sanitize($stu['name']) ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Student ID</div>
      <div class="info-value"><?= sanitize($stu['roll_number']) ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Grade & Section</div>
      <div class="info-value">Grade <?= $stu['grade'] ?><?= $stu['class_section'] ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Gender</div>
      <div class="info-value"><?= sanitize($stu['gender'] ?? '—') ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Date of Birth</div>
      <div class="info-value"><?= $stu['date_of_birth'] ? date('d M Y', strtotime($stu['date_of_birth'])) : '—' ?></div>
    </div>
    <div class="info-item">
      <div class="info-label">Homeroom Teacher</div>
      <div class="info-value"><?= sanitize($hrtName ?? '—') ?></div>
    </div>
  </div>

  <!-- Summary -->
  <div class="rc-summary">
    <div class="summary-box" style="border-color:<?= $ogc ?>;border-width:2px">
      <div class="summary-num" style="color:<?= $ogc ?>"><?= $ogl ?></div>
      <div class="summary-lbl">Overall Grade</div>
    </div>
    <div class="summary-box">
      <div class="summary-num"><?= $avgPct ?>%</div>
      <div class="summary-lbl">Average</div>
    </div>
    <div class="summary-box">
      <div class="summary-num"><?= $rank ? '#'.$rank['rank_position'].'/'.$rank['total'] : '—' ?></div>
      <div class="summary-lbl">Class Rank</div>
    </div>
    <div class="summary-box" style="border-color:<?= $isPassing?'#10b981':'#ef4444' ?>;border-width:2px">
      <div class="summary-num" style="color:<?= $isPassing?'#10b981':'#ef4444' ?>"><?= $isPassing?'PASS':'REVIEW' ?></div>
      <div class="summary-lbl">Status</div>
    </div>
  </div>

  <!-- Marks Table -->
  <table class="marks-table">
    <thead>
      <tr>
        <th>Subject</th>
        <th>Teacher</th>
        <th>Details</th>
        <th style="text-align:right">Total</th>
        <th style="text-align:right">%</th>
        <th style="text-align:right">Grade</th>
        <th>Bar</th>
        <th style="text-align:center">Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($allMarks as $mk): [$gl,$gc]=getGradeLetter($mk['percentage']); ?>
    <tr>
      <td style="font-weight:600"><?= sanitize($mk['subject_name']) ?></td>
      <td style="color:#555"><?= sanitize($mk['teacher_name']) ?></td>
      <td>
        <?php
        $cd=json_decode($mk['criteria_data'],true);
        $cm=json_decode($mk['criteria_max'],true);
        if($cd && is_array($cd)):
          $parts=[];
          foreach($cd as $cn=>$cv) $parts[]="{$cn}: {$cv}/".($cm[$cn]??'?');
          echo '<span style="font-size:9px;color:#777">'.implode(' · ',$parts).'</span>';
        endif;
        ?>
      </td>
      <td style="text-align:right"><?= $mk['total_mark'] ?>/<?= $mk['max_mark'] ?></td>
      <td style="text-align:right;font-weight:700;color:<?= $gc ?>"><?= $mk['percentage'] ?>%</td>
      <td style="text-align:right">
        <span class="grade-pill" style="background:<?= $gc ?>22;color:<?= $gc ?>"><?= $gl ?></span>
      </td>
      <td style="width:80px">
        <div class="mini-bar"><div class="mini-bar-fill" style="width:<?= min($mk['percentage'],100) ?>%;background:<?= $gc ?>"></div></div>
      </td>
      <td style="text-align:center">
        <span class="<?= $mk['percentage']>=$passPercent?'pass':'fail' ?>" style="font-size:10px;font-weight:700">
          <?= $mk['percentage']>=$passPercent?'✓ Pass':'✗ Fail' ?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($allMarks)): ?>
    <tr><td colspan="8" style="text-align:center;padding:20px;color:#888">No marks recorded for this term</td></tr>
    <?php else: ?>
    <tr>
      <td colspan="3" style="font-weight:700">OVERALL AVERAGE</td>
      <td style="text-align:right;font-weight:700"><?= count($allMarks) ?> subjects</td>
      <td style="text-align:right;font-weight:800;color:<?= $ogc ?>"><?= $avgPct ?>%</td>
      <td style="text-align:right"><span class="grade-pill" style="background:<?= $ogc ?>;color:#fff"><?= $ogl ?></span></td>
      <td colspan="2" style="font-weight:700;color:<?= $isPassing?'#15803d':'#dc2626' ?>"><?= $isPassing?'✓ PASS':'✗ REVIEW' ?></td>
    </tr>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- Attendance -->
  <div style="display:flex;align-items:center;gap:24px;margin-bottom:14px;padding:10px;border:1px solid #ddd;border-radius:6px;background:#f9fafb">
    <strong style="font-size:10px;text-transform:uppercase;color:#888;letter-spacing:.5px;width:80px">Attendance</strong>
    <div class="rc-attendance" style="margin:0;flex:1">
      <?php foreach([
        ['Present','#10b981',$attData['present']??0],
        ['Absent','#ef4444',$attData['absent']??0],
        ['Late','#f59e0b',$attData['late']??0],
        ['Excused','#3b82f6',$attData['excused']??0],
      ] as [$l,$c,$v]): ?>
      <div class="att-item">
        <div class="att-num" style="color:<?= $c ?>"><?= $v ?></div>
        <div class="att-lbl"><?= $l ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:20px;font-weight:800;color:<?= $attPct>=90?'#10b981':($attPct>=75?'#f59e0b':'#ef4444') ?>"><?= $attPct ?>%</div>
      <div style="font-size:9px;color:#888;text-transform:uppercase">Attendance Rate</div>
    </div>
  </div>

  <!-- Comments -->
  <?php if($comments): ?>
  <div class="rc-comments">
    <h4>Teacher Comments</h4>
    <?php foreach($comments as $cm): ?>
    <div class="comment-item">
      <strong><?= sanitize($cm['tname']) ?></strong> (<?= ucfirst($cm['type']) ?>): <?= sanitize($cm['comment_text']) ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Signatures -->
  <div class="rc-footer">
    <div class="sig-line">
      <div class="line"></div>
      <div class="label">Class Teacher Signature</div>
    </div>
    <div class="sig-line">
      <div class="line"></div>
      <div class="label">Principal Signature</div>
    </div>
    <div class="sig-line">
      <div class="line"></div>
      <div class="label">Parent / Guardian Signature</div>
    </div>
    <div style="text-align:center;font-size:9px;color:#888">
      <div><?= sanitize($schoolName) ?></div>
      <div>Academic Year <?= sanitize($year) ?></div>
      <div>Generated: <?= date('d M Y H:i') ?></div>
    </div>
  </div>
</div>

<?php if ($stuIdx < count($students) - 1): ?>
<div class="page-break"></div>
<?php endif; ?>

<?php endforeach; ?>
</body>
</html>
