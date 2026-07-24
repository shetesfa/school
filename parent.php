<?php
require_once 'config.php';
requireRole('parent');

$pageTitle  = 'Parent Portal';
$activeMenu = 'dashboard';

$parent_user_id = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'dashboard';
$activeMenu = $tab;

$currentTerm = getSchoolSetting($pdo, 'current_term', 'Term 1');
$currentYear = getSchoolSetting($pdo, 'current_year', '2025/2026');
$visibility  = getVisibilityMode($pdo);

// Get linked students
$sq = $pdo->prepare("
    SELECT s.* FROM students s
    JOIN parent_access pa ON pa.student_id=s.id
    WHERE pa.user_id=?
    UNION
    SELECT s.* FROM students s WHERE s.parent_email=(SELECT email FROM users WHERE id=?)
    ORDER BY name
");
$sq->execute([$parent_user_id, $parent_user_id]);
$myStudents = $sq->fetchAll();

// Also try matching by email if no PA rows
if (empty($myStudents)) {
    $pemail = $pdo->prepare("SELECT email FROM users WHERE id=?");
    $pemail->execute([$parent_user_id]);
    $pem = $pemail->fetchColumn();
    if ($pem) {
        $sq2 = $pdo->prepare("SELECT * FROM students WHERE parent_email=? AND status='active'");
        $sq2->execute([$pem]);
        $myStudents = $sq2->fetchAll();
    }
}

// Selected student
$selStudentId = (int)($_GET['student'] ?? ($myStudents[0]['id'] ?? 0));
$selStudent = null;
foreach($myStudents as $ms) if($ms['id'] == $selStudentId) { $selStudent = $ms; break; }

include 'layout.php';
?>

<?php if(empty($myStudents)): ?>
<div class="alert alert-warning" style="margin-bottom:0">
  <i class="fa fa-exclamation-triangle"></i>
  No students linked to your account. Please contact the school administration.
</div>
<?php include 'layout_end.php'; exit(); endif; ?>

<!-- Student Selector -->
<?php if(count($myStudents) > 1): ?>
<div class="card mb-4" style="padding:12px 16px">
  <div class="flex items-center gap-3 flex-wrap">
    <span class="text-muted text-sm font-bold">Select Child:</span>
    <?php foreach($myStudents as $ms): ?>
    <a href="parent.php?tab=<?= $tab ?>&student=<?= $ms['id'] ?>"
       class="btn <?= $ms['id']==$selStudentId?'btn-primary':'btn-secondary' ?>">
      <?php if($ms['photo']): ?>
      <img src="<?= sanitize($ms['photo']) ?>" style="width:24px;height:24px;border-radius:50%;object-fit:cover" alt="">
      <?php endif; ?>
      <?= sanitize($ms['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if(!$selStudent): ?>
<div class="alert alert-danger">Student not found.</div>
<?php include 'layout_end.php'; exit(); endif; ?>

<!-- Student Banner -->
<div class="card mb-4" style="background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border:none">
  <div class="card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;padding:24px">
    <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;flex-shrink:0">
      <?php if($selStudent['photo']): ?>
      <img src="<?= sanitize($selStudent['photo']) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover" alt="">
      <?php else: ?>
      <?= strtoupper(substr($selStudent['name'],0,1)) ?>
      <?php endif; ?>
    </div>
    <div style="flex:1">
      <div style="font-size:1.4rem;font-weight:800"><?= sanitize($selStudent['name']) ?></div>
      <div style="opacity:.85;margin-top:4px">
        Grade <?= $selStudent['grade'] ?><?= $selStudent['class_section'] ?> &nbsp;·&nbsp;
        <?= $selStudent['roll_number'] ?> &nbsp;·&nbsp;
        <?= $selStudent['nationality'] ?? 'International' ?>
      </div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap">
      <?php
      // Quick attendance stats
      $att = $pdo->prepare("
          SELECT
            SUM(status='Present') AS present,
            SUM(status='Absent') AS absent,
            SUM(status='Late') AS late,
            SUM(status='Excused') AS excused,
            COUNT(*) AS total
          FROM attendance WHERE student_id=? AND date >= DATE_SUB(CURDATE(),INTERVAL 90 DAY)
      ");
      $att->execute([$selStudentId]);
      $attStats = $att->fetch();
      $attPct = $attStats['total']>0 ? round(($attStats['present']/$attStats['total'])*100,1) : 0;
      ?>
      <div style="text-align:center;background:rgba(255,255,255,.15);padding:12px 20px;border-radius:12px">
        <div style="font-size:1.6rem;font-weight:800"><?= $attPct ?>%</div>
        <div style="font-size:.78rem;opacity:.8">Attendance</div>
      </div>
      <?php if($visibility >= 3):
        $avgMark = $pdo->prepare("
            SELECT AVG(percentage) AS avg_pct FROM student_marks_custom
            WHERE student_id=? AND term=? AND academic_year=?
        ");
        $avgMark->execute([$selStudentId,$currentTerm,$currentYear]);
        $avg = $avgMark->fetchColumn();
        [$gl,$gc] = getGradeLetter($avg??0);
      ?>
      <div style="text-align:center;background:rgba(255,255,255,.15);padding:12px 20px;border-radius:12px">
        <div style="font-size:1.6rem;font-weight:800"><?= $avg?round($avg,1).'%':'—' ?></div>
        <div style="font-size:.78rem;opacity:.8">Average (<?= $currentTerm ?>)</div>
      </div>
      <?php endif; ?>
      <?php if($visibility >= 3):
        $rank = $pdo->prepare("
            SELECT cr.rank_position, (SELECT COUNT(*) FROM class_rankings cr2 WHERE cr2.class_id=cr.class_id AND cr2.term=cr.term AND cr2.academic_year=cr.academic_year) AS total_in_class
            FROM class_rankings cr
            JOIN classes c ON c.id=cr.class_id
            WHERE cr.student_id=? AND cr.term=? AND cr.academic_year=?
            LIMIT 1
        ");
        $rank->execute([$selStudentId,$currentTerm,$currentYear]);
        $rankRow = $rank->fetch();
      ?>
      <div style="text-align:center;background:rgba(255,255,255,.15);padding:12px 20px;border-radius:12px">
        <div style="font-size:1.6rem;font-weight:800">
          <?= $rankRow ? '#'.$rankRow['rank_position'].'/'.$rankRow['total_in_class'] : '—' ?>
        </div>
        <div style="font-size:.78rem;opacity:.8">Class Rank</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <?php
  $pTabs=[
    ['key'=>'dashboard','icon'=>'fa-gauge','label'=>'Overview'],
    ['key'=>'attend',   'icon'=>'fa-calendar-check','label'=>'Attendance'],
    ['key'=>'calendar', 'icon'=>'fa-calendar','label'=>'Calendar'],
  ];
  if($visibility >= 3) $pTabs[]=['key'=>'marks','icon'=>'fa-star','label'=>'Results'];
  $pTabs[]=['key'=>'profile','icon'=>'fa-user','label'=>'Profile'];
  foreach($pTabs as $pt): ?>
  <button class="tab-btn <?= $tab===$pt['key']?'active':'' ?>" onclick="switchTab('<?= $pt['key'] ?>')">
    <i class="fa <?= $pt['icon'] ?>"></i> <?= $pt['label'] ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- ═══ OVERVIEW ═══ -->
<div class="tab-pane <?= $tab==='dashboard'?'active':'' ?>" id="tab-dashboard">

  <!-- Visibility mode info -->
  <?php if($visibility===1): ?>
  <div class="alert alert-info"><i class="fa fa-info-circle"></i>
    Results are not yet published. Please check back later or contact the school.
  </div>
  <?php elseif($visibility===2): ?>
  <div class="alert alert-info"><i class="fa fa-info-circle"></i>
    Currently showing attendance records only. Marks will be published when the school admin enables them.
  </div>
  <?php endif; ?>

  <div class="grid-2">
    <!-- Attendance Summary -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-calendar-check"></i> Attendance Summary</h3></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <?php foreach([
            ['Present','success','fa-check',$attStats['present']],
            ['Absent','danger','fa-xmark',$attStats['absent']],
            ['Late','warning','fa-clock',$attStats['late']],
            ['Excused','info','fa-file',$attStats['excused']],
          ] as [$l,$c,$ic,$v]): ?>
          <div style="text-align:center;padding:16px;background:var(--bg);border-radius:12px">
            <i class="fa <?= $ic ?>" style="font-size:1.4rem;color:var(--<?= $c ?>);margin-bottom:6px"></i>
            <div style="font-size:1.6rem;font-weight:800"><?= $v??0 ?></div>
            <div class="text-sm text-muted"><?= $l ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4">
          <div class="flex justify-between mb-1"><span class="text-sm">Attendance Rate</span><strong><?= $attPct ?>%</strong></div>
          <div class="progress"><div class="progress-bar" style="width:<?= $attPct ?>%;background:<?= $attPct>=90?'var(--success)':($attPct>=75?'var(--warning)':'var(--danger)') ?>"></div></div>
        </div>
      </div>
    </div>

    <!-- Recent Attendance -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-history"></i> Recent Days</h3></div>
      <div class="table-wrap">
        <?php
        $recentAtt = $pdo->prepare("
            SELECT * FROM attendance WHERE student_id=? ORDER BY date DESC LIMIT 15
        ");
        $recentAtt->execute([$selStudentId]);
        $ra = $recentAtt->fetchAll();
        ?>
        <table>
          <thead><tr><th>Date</th><th>Day</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($ra as $a): $bc=['Present'=>'success','Absent'=>'danger','Late'=>'warning','Excused'=>'info']; ?>
          <tr>
            <td><?= date('d M Y',strtotime($a['date'])) ?></td>
            <td class="text-muted"><?= date('l',strtotime($a['date'])) ?></td>
            <td><span class="badge badge-<?= $bc[$a['status']] ?>"><?= $a['status'] ?></span></td>
          </tr>
          <?php endforeach;
          if(empty($ra)): ?><tr><td colspan="3" class="text-center text-muted">No attendance records</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <?php
  $anns = $pdo->query("
      SELECT * FROM announcements WHERE is_active=1 AND (target_role='parent' OR target_role='all')
      ORDER BY created_at DESC LIMIT 5
  ")->fetchAll();
  if($anns): ?>
  <div class="card mt-4">
    <div class="card-header"><h3><i class="fa fa-bullhorn"></i> School Announcements</h3></div>
    <div class="card-body">
      <?php foreach($anns as $an): $pc=['normal'=>'info','high'=>'warning','urgent'=>'danger']; ?>
      <div style="padding:14px 0;border-bottom:1px solid var(--border)">
        <div class="flex items-center gap-2 mb-2">
          <span class="badge badge-<?= $pc[$an['priority']] ?>"><?= ucfirst($an['priority']) ?></span>
          <span class="text-sm text-muted"><?= date('M d, Y', strtotime($an['created_at'])) ?></span>
        </div>
        <div style="font-weight:700;margin-bottom:4px"><?= sanitize($an['title']) ?></div>
        <div class="text-sm text-muted"><?= sanitize($an['message']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ ATTENDANCE ═══ -->
<div class="tab-pane <?= $tab==='attend'?'active':'' ?>" id="tab-attend">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-calendar-check"></i> Full Attendance Record</h3>
    </div>
    <div class="card-body">
      <div class="flex gap-3 mb-4 flex-wrap">
        <select id="att-month" class="form-control" style="width:160px" onchange="filterAttendance()">
          <option value="">All Time</option>
          <?php for($m=1;$m<=12;$m++): ?>
          <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <?php
      $fullAtt = $pdo->prepare("
          SELECT * FROM attendance WHERE student_id=? ORDER BY date DESC
      ");
      $fullAtt->execute([$selStudentId]);
      $fa = $fullAtt->fetchAll();
      ?>
      <div class="table-wrap">
        <table id="full-att-table">
          <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Remarks</th></tr></thead>
          <tbody>
          <?php foreach($fa as $a): $bc=['Present'=>'success','Absent'=>'danger','Late'=>'warning','Excused'=>'info']; ?>
          <tr data-month="<?= date('m',strtotime($a['date'])) ?>">
            <td><?= date('d M Y',strtotime($a['date'])) ?></td>
            <td class="text-muted"><?= date('l',strtotime($a['date'])) ?></td>
            <td><span class="badge badge-<?= $bc[$a['status']] ?>"><?= $a['status'] ?></span></td>
            <td class="text-muted text-sm"><?= sanitize($a['remarks']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ═══ RESULTS ═══ -->
<?php if($visibility >= 3): ?>
<div class="tab-pane <?= $tab==='marks'?'active':'' ?>" id="tab-marks">
  <?php if($visibility === 3): ?>
  <div class="alert alert-info"><i class="fa fa-info-circle"></i>
    Subject-wise marks are not shown. Contact school for details.
  </div>
  <?php endif; ?>

  <?php
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
  $marks->execute([$selStudentId,$currentTerm,$currentYear]);
  $allMarks = $marks->fetchAll();

  $overallAvg = 0;
  if($allMarks) $overallAvg = array_sum(array_column($allMarks,'percentage'))/count($allMarks);
  [$ogl,$ogc] = getGradeLetter($overallAvg);

  // Comments from homeroom teacher
  $comms = $pdo->prepare("
      SELECT c.*, u.name AS teacher_name FROM comments c
      JOIN teachers t ON t.id=c.teacher_id
      JOIN users u ON u.id=t.user_id
      WHERE c.student_id=? AND c.term=? AND c.academic_year=?
  ");
  $comms->execute([$selStudentId,$currentTerm,$currentYear]);
  $comments = $comms->fetchAll();
  ?>

  <!-- Summary Card -->
  <div class="card mb-4" style="border:2px solid var(--primary)">
    <div class="card-body" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;padding:24px">
      <div style="text-align:center;flex-shrink:0">
        <div style="width:80px;height:80px;border-radius:50%;background:<?= $ogc ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;font-weight:800;margin:0 auto 8px"><?= $ogl ?></div>
        <div class="text-sm text-muted">Grade</div>
      </div>
      <div>
        <div style="font-size:2rem;font-weight:800;color:<?= $ogc ?>"><?= round($overallAvg,1) ?>%</div>
        <div class="text-muted">Overall Average – <?= $currentTerm ?></div>
        <?php if($rankRow??false): ?>
        <div class="mt-2"><span class="badge badge-primary">🏆 Rank #<?= $rankRow['rank_position'] ?> of <?= $rankRow['total_in_class'] ?></span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if($visibility >= 4 && $allMarks): ?>
  <!-- Subject Breakdown -->
  <div class="card mb-4">
    <div class="card-header"><h3><i class="fa fa-star"></i> Subject Results</h3></div>
    <div class="card-body">
      <?php foreach($allMarks as $mk): [$gl,$gc]=getGradeLetter($mk['percentage']); ?>
      <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
        <div class="flex justify-between items-center mb-2">
          <div>
            <span class="badge" style="background:<?= $mk['subj_color'] ?>22;color:<?= $mk['subj_color'] ?>;margin-right:8px">
              <?= sanitize($mk['subject_name']) ?>
            </span>
            <span class="text-sm text-muted">by <?= sanitize($mk['teacher_name']) ?></span>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-sm text-muted"><?= $mk['total_mark'] ?>/<?= $mk['max_mark'] ?></span>
            <span style="font-weight:800;font-size:1.1rem;color:<?= $gc ?>"><?= $mk['percentage'] ?>%</span>
            <span style="font-weight:700;color:<?= $gc ?>"><?= $mk['grade'] ?></span>
          </div>
        </div>
        <div class="progress">
          <div class="progress-bar" style="width:<?= min($mk['percentage'],100) ?>%;background:<?= $gc ?>"></div>
        </div>
        <?php
        $cd = json_decode($mk['criteria_data'],true);
        $cm = json_decode($mk['criteria_max'],true);
        if($cd && is_array($cd)): ?>
        <div class="flex gap-2 mt-2 flex-wrap">
          <?php foreach($cd as $cn=>$cv): ?>
          <div style="background:var(--bg);padding:4px 10px;border-radius:20px;font-size:.75rem">
            <strong><?= sanitize($cn) ?>:</strong> <?= $cv ?>/<?= $cm[$cn]??'?' ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if($mk['remarks']): ?>
        <div class="text-sm text-muted mt-2"><i class="fa fa-comment"></i> <?= sanitize($mk['remarks']) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Teacher Comments -->
  <?php if($visibility >= 4 && $comments): ?>
  <div class="card">
    <div class="card-header"><h3><i class="fa fa-comments"></i> Teacher Comments</h3></div>
    <div class="card-body">
      <?php foreach($comments as $cm): ?>
      <div style="padding:12px;background:var(--bg);border-radius:10px;margin-bottom:10px">
        <div class="flex items-center gap-2 mb-2">
          <div class="avatar" style="width:28px;height:28px;font-size:.75rem"><?= strtoupper(substr($cm['teacher_name'],0,1)) ?></div>
          <strong class="text-sm"><?= sanitize($cm['teacher_name']) ?></strong>
          <span class="badge badge-<?= $cm['type']==='academic'?'primary':($cm['type']==='behavioral'?'warning':'info') ?>"><?= ucfirst($cm['type']) ?></span>
        </div>
        <div><?= sanitize($cm['comment_text']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Print Button -->
  <div class="mt-4 no-print">
    <a href="report_card.php?student_id=<?= $selStudentId ?>&term=<?= urlencode($currentTerm) ?>&year=<?= urlencode($currentYear) ?>"
       class="btn btn-primary" target="_blank">
      <i class="fa fa-print"></i> Print Report Card
    </a>
  </div>
</div>
<?php endif; ?>

<!-- ═══ CALENDAR ═══ -->
<div class="tab-pane <?= $tab==='calendar'?'active':'' ?>" id="tab-calendar">
  <?php include 'includes/calendar_view.php'; ?>
</div>

<!-- ═══ PROFILE ═══ -->
<div class="tab-pane <?= $tab==='profile'?'active':'' ?>" id="tab-profile">
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-user"></i> Student Profile</h3></div>
      <div class="card-body">
        <?php
        $fields=[
          ['fa-id-badge','Student ID',$selStudent['roll_number']],
          ['fa-user','Full Name',$selStudent['name']],
          ['fa-venus-mars','Gender',$selStudent['gender']??'—'],
          ['fa-cake-candles','Date of Birth',$selStudent['date_of_birth']?date('d M Y',strtotime($selStudent['date_of_birth'])):'—'],
          ['fa-flag','Nationality',$selStudent['nationality']??'—'],
          ['fa-door-open','Grade & Section','Grade '.$selStudent['grade'].$selStudent['class_section']],
          ['fa-graduation-cap','Academic Year',$selStudent['academic_year']],
          ['fa-circle-info','Status',ucfirst($selStudent['status'])],
        ];
        foreach($fields as [$ic,$l,$v]): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <i class="fa <?= $ic ?>" style="width:20px;text-align:center;color:var(--primary)"></i>
          <span class="text-muted text-sm" style="width:120px;flex-shrink:0"><?= $l ?></span>
          <strong><?= sanitize($v) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-house-user"></i> Parent / Guardian</h3></div>
      <div class="card-body">
        <?php
        $pfields=[
          ['fa-user','Name',$selStudent['parent_name']??'—'],
          ['fa-phone','Phone',$selStudent['parent_phone']??'—'],
          ['fa-envelope','Email',$selStudent['parent_email']??'—'],
          ['fa-location-dot','Address',$selStudent['address']??'—'],
        ];
        foreach($pfields as [$ic,$l,$v]): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">
          <i class="fa <?= $ic ?>" style="width:20px;text-align:center;color:var(--primary)"></i>
          <span class="text-muted text-sm" style="width:120px;flex-shrink:0"><?= $l ?></span>
          <strong><?= sanitize($v) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-body" style="border-top:1px solid var(--border)">
        <a href="chat.php" class="btn btn-primary w-full"><i class="fa fa-comments"></i> Message the Teacher</a>
      </div>
    </div>
  </div>
</div>

<script>

function filterAttendance(){
  const m=document.getElementById('att-month').value;
  document.querySelectorAll('#full-att-table tbody tr').forEach(row=>{
    row.style.display=(!m||row.dataset.month===m)?'':'none';
  });
}
</script>

<?php include 'layout_end.php'; ?>
