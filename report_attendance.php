<?php
require_once 'config.php';
requireRole(['superadmin','teacher']);

$pageTitle  = 'Attendance Report';
$activeMenu = 'attend';

$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedClass = sanitize($_GET['class'] ?? '');
[$sYear, $sMonth] = explode('-', $selectedMonth);

$classes = $pdo->query("SELECT *, CONCAT(grade,section) AS class_name FROM classes ORDER BY grade, section")->fetchAll();

$whereClass = '';
$params = [];
if ($selectedClass && preg_match('/^(\d+)([A-Z])$/', $selectedClass, $m)) {
    $whereClass = " AND s.grade=? AND s.class_section=?";
    $params = [$m[1], $m[2]];
}

$students = $pdo->query("
    SELECT s.*, CONCAT(s.grade,s.class_section) AS class_name,
           SUM(a.status='Present') AS present_count,
           SUM(a.status='Absent') AS absent_count,
           SUM(a.status='Late') AS late_count,
           SUM(a.status='Excused') AS excused_count,
           COUNT(a.id) AS total_days,
           ROUND(SUM(a.status='Present')/NULLIF(COUNT(a.id),0)*100,1) AS att_pct
    FROM students s
    LEFT JOIN attendance a ON a.student_id=s.id AND DATE_FORMAT(a.date,'%Y-%m')='$selectedMonth'
    WHERE s.status='active'" . $whereClass . "
    GROUP BY s.id ORDER BY s.grade, s.class_section, s.name
", $params ?: null);

if (!is_object($students)) {
    // Prepared query version
    $stmt = $pdo->prepare("
        SELECT s.*, CONCAT(s.grade,s.class_section) AS class_name,
               SUM(a.status='Present') AS present_count,
               SUM(a.status='Absent') AS absent_count,
               SUM(a.status='Late') AS late_count,
               SUM(a.status='Excused') AS excused_count,
               COUNT(a.id) AS total_days,
               ROUND(SUM(a.status='Present')/NULLIF(COUNT(a.id),0)*100,1) AS att_pct
        FROM students s
        LEFT JOIN attendance a ON a.student_id=s.id AND DATE_FORMAT(a.date,'%Y-%m')=?
        WHERE s.status='active'" . $whereClass . "
        GROUP BY s.id ORDER BY s.grade, s.class_section, s.name
    ");
    $stmt->execute(array_merge([$selectedMonth], $params));
    $stuRows = $stmt->fetchAll();
} else {
    $stuRows = $students->fetchAll();
}

$schoolName = getSchoolSetting($pdo, 'school_name', 'EduTrack International School');

include 'layout.php';
?>
<div class="card mb-4">
  <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <form method="GET" class="flex gap-2 flex-wrap items-center" style="flex:1">
      <input type="hidden" name="tab" value="attend">
      <div class="form-group" style="margin:0">
        <label class="form-label">Month</label>
        <input type="month" name="month" class="form-control" value="<?= $selectedMonth ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Class</label>
        <select name="class" class="form-control" style="width:160px">
          <option value="">All Classes</option>
          <?php foreach($classes as $cl): ?>
          <option value="<?= $cl['class_name'] ?>" <?= $selectedClass===$cl['class_name']?'selected':'' ?>>Grade <?= $cl['class_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;align-self:flex-end">
        <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3><i class="fa fa-calendar-check"></i> Attendance – <?= date('F Y', mktime(0,0,0,(int)$sMonth,1,(int)$sYear)) ?></h3>
    <span class="badge badge-info"><?= count($stuRows) ?> students</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Student</th><th>Class</th>
          <th style="color:#10b981">Present</th>
          <th style="color:#ef4444">Absent</th>
          <th style="color:#f59e0b">Late</th>
          <th style="color:#3b82f6">Excused</th>
          <th>Total</th>
          <th>Rate</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($stuRows as $i=>$s):
        $pct = (float)($s['att_pct'] ?? 0);
        $color = $pct >= 90 ? 'var(--success)' : ($pct >= 75 ? 'var(--warning)' : 'var(--danger)');
      ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><strong><?= sanitize($s['name']) ?></strong><br><span class="text-sm text-muted"><?= $s['roll_number'] ?></span></td>
        <td>Grade <?= $s['class_name'] ?></td>
        <td style="color:#10b981;font-weight:700"><?= $s['present_count']??0 ?></td>
        <td style="color:#ef4444;font-weight:700"><?= $s['absent_count']??0 ?></td>
        <td style="color:#f59e0b;font-weight:700"><?= $s['late_count']??0 ?></td>
        <td style="color:#3b82f6;font-weight:700"><?= $s['excused_count']??0 ?></td>
        <td><?= $s['total_days']??0 ?></td>
        <td>
          <div class="flex items-center gap-2">
            <div class="progress" style="flex:1;min-width:60px">
              <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
            </div>
            <span style="color:<?= $color ?>;font-weight:700;font-size:.85rem"><?= $pct ?>%</span>
          </div>
        </td>
        <td>
          <?php if($pct >= 90): ?><span class="badge badge-success">Excellent</span>
          <?php elseif($pct >= 75): ?><span class="badge badge-warning">Good</span>
          <?php elseif($pct >= 60): ?><span class="badge badge-warning">At Risk</span>
          <?php else: ?><span class="badge badge-danger">Critical</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($stuRows)): ?>
      <tr><td colspan="10" class="text-center text-muted" style="padding:32px">No data for selected period</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'layout_end.php'; ?>
