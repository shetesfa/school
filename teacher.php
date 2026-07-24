<?php
require_once 'config.php';
requireRole('teacher');

$pageTitle  = 'Teacher Dashboard';
$activeMenu = 'dashboard';

$teacher_user_id = $_SESSION['user_id'];
$teacher_id      = $_SESSION['teacher_id'] ?? 0;

$currentTerm = getSchoolSetting($pdo, 'current_term', 'Term 1');
$currentYear = getSchoolSetting($pdo, 'current_year', '2025/2026');

$msg = ''; $err = '';

// ── Get teacher info ───────────────────────
$t = $pdo->prepare("
    SELECT t.*, u.name, u.email, sub.name AS subject_name, sub.id AS subject_id, sub.color AS subj_color
    FROM teachers t
    JOIN users u ON u.id=t.user_id
    LEFT JOIN subjects sub ON sub.id=t.subject_id
    WHERE t.id=?
");
$t->execute([$teacher_id]);
$teacher = $t->fetch();
if (!$teacher) { header('Location: login.php'); exit(); }

// Is homeroom teacher?
$hr = $pdo->prepare("SELECT c.*, CONCAT(c.grade,c.section) AS class_name FROM classes c WHERE c.teacher_id=?");
$hr->execute([$teacher_id]);
$homroomClass = $hr->fetch();
$isHomeroom = (bool)$homroomClass;

// Teacher's assigned classes
$cls = $pdo->prepare("
    SELECT DISTINCT c.id, c.grade, c.section, CONCAT(c.grade,c.section) AS class_name
    FROM class_subject_teachers cst
    JOIN classes c ON c.id=cst.class_id
    WHERE cst.teacher_id=?
    ORDER BY c.grade, c.section
");
$cls->execute([$teacher_id]);
$myClasses = $cls->fetchAll();

$tab = $_GET['tab'] ?? 'dashboard';
$activeMenu = $tab;

// ── POST handlers ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save marks
    if ($action === 'save_marks') {
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $class_id   = (int)$_POST['class_id'];
        $term       = sanitize($_POST['term']);
        $year       = sanitize($_POST['year']);
        $remarks    = sanitize($_POST['remarks'] ?? '');

        // Check lock
        $lk = $pdo->prepare("SELECT id FROM semester_locks WHERE class_id=? AND term=? AND academic_year=? AND is_locked=1");
        $lk->execute([$class_id, $term, $year]);
        if ($lk->fetch()) {
            $err = 'Marks are locked for this class.';
        } else {
            $criteria_data = $_POST['criteria'] ?? [];
            $criteria_max  = $_POST['criteria_max'] ?? [];
            $total = 0; $max = 0;
            foreach ($criteria_data as $k => $v) { $total += (float)$v; $max += (float)($criteria_max[$k] ?? 0); }
            $pct = $max > 0 ? round(($total / $max) * 100, 2) : 0;
            [$gl] = getGradeLetter($pct);

            $st = $pdo->prepare("
                INSERT INTO student_marks_custom
                  (student_id,subject_id,teacher_id,class_id,term,academic_year,criteria_data,criteria_max,total_mark,max_mark,percentage,grade,remarks,entered_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  criteria_data=VALUES(criteria_data), criteria_max=VALUES(criteria_max),
                  total_mark=VALUES(total_mark), max_mark=VALUES(max_mark),
                  percentage=VALUES(percentage), grade=VALUES(grade), remarks=VALUES(remarks),
                  entered_by=VALUES(entered_by), updated_at=NOW()
            ");
            if ($st->execute([
                $student_id, $subject_id, $teacher_id, $class_id, $term, $year,
                json_encode($criteria_data), json_encode($criteria_max),
                $total, $max, $pct, $gl, $remarks, $teacher_user_id
            ])) { $msg = 'Marks saved successfully.'; }
            else { $err = 'Failed to save marks.'; }
        }
    }

    // Mark attendance
    if ($action === 'mark_attendance') {
        $date = $_POST['date'] ?? date('Y-m-d');
        $grade   = sanitize($_POST['grade']);
        $section = sanitize($_POST['section']);
        $statuses = $_POST['status'] ?? [];
        $stuIds   = $_POST['student_ids'] ?? [];
        $saved = 0;
        foreach ($stuIds as $sid) {
            $status = $statuses[$sid] ?? 'Present';
            $st = $pdo->prepare("
                INSERT INTO attendance (student_id,date,status,marked_by)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), marked_by=VALUES(marked_by)
            ");
            if ($st->execute([$sid, $date, $status, $teacher_user_id])) $saved++;
        }
        $msg = "Attendance saved for $saved students.";
    }

    // Add comment
    if ($action === 'add_comment') {
        $st = $pdo->prepare("
            INSERT INTO comments (student_id,teacher_id,term,academic_year,comment_text,type)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE comment_text=VALUES(comment_text), type=VALUES(type)
        ");
        $st->execute([
            (int)$_POST['student_id'], $teacher_id, $currentTerm, $currentYear,
            sanitize($_POST['comment_text']), sanitize($_POST['type'] ?? 'general')
        ]);
        $msg = 'Comment saved.';
    }

    header("Location: teacher.php?tab=$tab&msg=".urlencode($msg).'&err='.urlencode($err));
    exit();
}

if (isset($_GET['msg'])) $msg = sanitize($_GET['msg']);
if (isset($_GET['err'])) $err = sanitize($_GET['err']);

// Selected class for marks/attendance
$selClass = $_GET['class'] ?? ($myClasses[0]['class_name'] ?? '');
$selGrade = ''; $selSection = '';
if ($selClass && preg_match('/^(\d+)([A-Z])$/', $selClass, $m)) {
    $selGrade = $m[1]; $selSection = $m[2];
}

// Get students for selected class
$students = [];
if ($selGrade && $selSection) {
    $sq = $pdo->prepare("SELECT * FROM students WHERE grade=? AND class_section=? AND status='active' ORDER BY name");
    $sq->execute([$selGrade, $selSection]);
    $students = $sq->fetchAll();
}

// Get class_id for selected class
$classIdRow = null;
if ($selGrade && $selSection) {
    $cq = $pdo->prepare("SELECT id FROM classes WHERE grade=? AND section=?");
    $cq->execute([$selGrade, $selSection]);
    $classIdRow = $cq->fetch();
}
$selectedClassId = $classIdRow['id'] ?? 0;
$classIsLocked = isClassLocked($pdo, $selectedClassId, $currentTerm, $currentYear);

// Stats
$totalStudents = 0;
foreach($myClasses as $cl) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE grade=? AND class_section=? AND status='active'");
    $cnt->execute([$cl['grade'], $cl['section']]);
    $totalStudents += (int)$cnt->fetchColumn();
}
$marksEntered = $pdo->prepare("SELECT COUNT(*) FROM student_marks_custom WHERE teacher_id=? AND term=? AND academic_year=?");
$marksEntered->execute([$teacher_id, $currentTerm, $currentYear]);
$marksCount = (int)$marksEntered->fetchColumn();

$today = date('Y-m-d');
$attToday = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE marked_by=? AND date=?");
$attToday->execute([$teacher_user_id, $today]);
$attTodayCount = (int)$attToday->fetchColumn();

include 'layout.php';
?>

<?php if($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i><?= $err ?></div><?php endif; ?>

<!-- Teacher Profile Header -->
<div class="card mb-4" style="border-left:4px solid <?= $teacher['subj_color']??'var(--primary)' ?>">
  <div class="card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div class="avatar" style="width:56px;height:56px;font-size:1.3rem;background:<?= $teacher['subj_color']??'var(--primary)' ?>">
      <?= strtoupper(substr($teacher['name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-size:1.1rem;font-weight:700"><?= sanitize($teacher['name']) ?></div>
      <div class="text-muted text-sm"><?= sanitize($teacher['subject_name']??'No Subject') ?> Teacher
        <?php if($isHomeroom): ?> · <span class="badge badge-primary">Homeroom: Grade <?= $homroomClass['class_name'] ?></span><?php endif; ?>
      </div>
    </div>
    <div class="stats-row" style="margin:0;flex:2;min-width:300px">
      <div class="stat-card" style="border-color:var(--primary);padding:14px">
        <div class="stat-icon" style="background:#ede9fe;color:var(--primary);width:40px;height:40px"><i class="fa fa-users"></i></div>
        <div><div class="stat-num" style="font-size:1.4rem"><?= $totalStudents ?></div><div class="stat-label">My Students</div></div>
      </div>
      <div class="stat-card" style="border-color:var(--success);padding:14px">
        <div class="stat-icon" style="background:#dcfce7;color:var(--success);width:40px;height:40px"><i class="fa fa-star"></i></div>
        <div><div class="stat-num" style="font-size:1.4rem"><?= $marksCount ?></div><div class="stat-label">Marks Entered</div></div>
      </div>
      <div class="stat-card" style="border-color:var(--accent);padding:14px">
        <div class="stat-icon" style="background:#e0f2fe;color:var(--accent);width:40px;height:40px"><i class="fa fa-calendar-check"></i></div>
        <div><div class="stat-num" style="font-size:1.4rem"><?= $attTodayCount ?></div><div class="stat-label">Today's Attendance</div></div>
      </div>
    </div>
  </div>
</div>

<div class="tabs">
  <?php
  $tTabs=[
    ['key'=>'dashboard','icon'=>'fa-gauge','label'=>'Overview'],
    ['key'=>'marks','icon'=>'fa-star','label'=>'Enter Marks'],
    ['key'=>'attendance','icon'=>'fa-calendar-check','label'=>'Attendance'],
    ['key'=>'students','icon'=>'fa-users','label'=>'My Students'],
  ];
  if($isHomeroom) $tTabs[]=['key'=>'homeroom','icon'=>'fa-house','label'=>'Homeroom'];
  $tTabs[]=['key'=>'calendar','icon'=>'fa-calendar','label'=>'Calendar'];
  foreach($tTabs as $tb): ?>
  <button class="tab-btn <?= $tab===$tb['key']?'active':'' ?>" onclick="switchTab('<?= $tb['key'] ?>')">
    <i class="fa <?= $tb['icon'] ?>"></i> <?= $tb['label'] ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- OVERVIEW -->
<div class="tab-pane <?= $tab==='dashboard'?'active':'' ?>" id="tab-dashboard">
  <div class="grid-2">
    <!-- My Classes -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-door-open"></i> My Classes</h3></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Class</th><th>Students</th><th>Marks</th></tr></thead>
          <tbody>
          <?php foreach($myClasses as $cl):
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE grade=? AND class_section=? AND status='active'");
            $cnt->execute([$cl['grade'], $cl['section']]);
            $c = (int)$cnt->fetchColumn();
            $mc = $pdo->prepare("SELECT COUNT(*) FROM student_marks_custom WHERE teacher_id=? AND class_id=(SELECT id FROM classes WHERE grade=? AND section=?) AND term=?");
            $mc->execute([$teacher_id,$cl['grade'],$cl['section'],$currentTerm]);
            $mcc = (int)$mc->fetchColumn();
          ?>
          <tr>
            <td><strong>Grade <?= $cl['class_name'] ?></strong></td>
            <td><span class="badge badge-primary"><?= $c ?></span></td>
            <td><span class="badge badge-success"><?= $mcc ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Marks -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-history"></i> Recent Marks</h3></div>
      <?php
      $recMarks = $pdo->prepare("
          SELECT smc.*, s.name AS sname, sub.name AS subname
          FROM student_marks_custom smc
          JOIN students s ON s.id=smc.student_id
          JOIN subjects sub ON sub.id=smc.subject_id
          WHERE smc.teacher_id=? ORDER BY smc.updated_at DESC LIMIT 8
      ");
      $recMarks->execute([$teacher_id]);
      $rm = $recMarks->fetchAll();
      ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Subject</th><th>%</th><th>Grade</th></tr></thead>
          <tbody>
          <?php foreach($rm as $r): [$gl,$gc]=getGradeLetter($r['percentage']); ?>
          <tr>
            <td><?= sanitize($r['sname']) ?></td>
            <td><?= sanitize($r['subname']) ?></td>
            <td><?= $r['percentage'] ?>%</td>
            <td><span style="color:<?= $gc ?>;font-weight:700"><?= $r['grade'] ?></span></td>
          </tr>
          <?php endforeach;
          if(empty($rm)): ?><tr><td colspan="4" class="text-center text-muted" style="padding:24px">No marks entered yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <?php
  $anns = $pdo->query("SELECT * FROM announcements WHERE is_active=1 AND (target_role='teacher' OR target_role='all') ORDER BY created_at DESC LIMIT 3")->fetchAll();
  if($anns): ?>
  <div class="card mt-4">
    <div class="card-header"><h3><i class="fa fa-bullhorn"></i> Announcements</h3></div>
    <div class="card-body">
      <?php foreach($anns as $an): ?>
      <div style="padding:12px 0;border-bottom:1px solid var(--border)">
        <div class="flex items-center gap-2 mb-1">
          <span class="badge badge-<?= ['normal'=>'info','high'=>'warning','urgent'=>'danger'][$an['priority']] ?>"><?= ucfirst($an['priority']) ?></span>
          <span class="text-sm text-muted"><?= date('M d, Y',strtotime($an['created_at'])) ?></span>
        </div>
        <div style="font-weight:700"><?= sanitize($an['title']) ?></div>
        <div class="text-sm text-muted"><?= sanitize($an['message']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- MARKS -->
<div class="tab-pane <?= $tab==='marks'?'active':'' ?>" id="tab-marks">
  <!-- Class selector -->
  <div class="card mb-4">
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <label class="form-label" style="margin:0">Select Class:</label>
      <?php foreach($myClasses as $cl): ?>
      <a href="teacher.php?tab=marks&class=<?= $cl['class_name'] ?>"
         class="btn <?= $selClass===$cl['class_name']?'btn-primary':'btn-secondary' ?>">
        Grade <?= $cl['class_name'] ?>
      </a>
      <?php endforeach; ?>
      <?php if($classIsLocked): ?>
      <span class="badge badge-danger"><i class="fa fa-lock"></i> Marks Locked by Admin</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if($selGrade && $selSection && $students): ?>
  <!-- Mark Entry Form -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-star"></i> Enter Marks – Grade <?= $selClass ?> – <?= sanitize($teacher['subject_name']??'') ?> – <?= $currentTerm ?></h3>
    </div>
    <div class="card-body">
      <?php if($classIsLocked): ?>
      <div class="alert alert-warning"><i class="fa fa-lock"></i> Marks are locked. Contact admin to unlock.</div>
      <?php else: ?>

      <!-- Criteria builder -->
      <div class="card mb-4" style="background:var(--bg)">
        <div class="card-body">
          <div class="flex items-center gap-3 flex-wrap mb-3">
            <strong>Mark Criteria:</strong>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addCriteria()"><i class="fa fa-plus"></i> Add Criterion</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="loadTemplate('default')"><i class="fa fa-template"></i> Default Template</button>
          </div>
          <div id="criteria-list" class="flex gap-2 flex-wrap">
            <div class="criteria-item flex items-center gap-2" style="background:var(--card);padding:8px 12px;border-radius:8px;border:1px solid var(--border)">
              <input type="text" class="form-control" style="width:120px" placeholder="Name" value="Assignment" name="cname[]">
              <input type="number" class="form-control" style="width:70px" placeholder="Max" value="20" name="cmax[]" min="1">
              <button type="button" onclick="this.closest('.criteria-item').remove()" style="background:none;border:none;color:var(--danger);cursor:pointer"><i class="fa fa-trash"></i></button>
            </div>
            <div class="criteria-item flex items-center gap-2" style="background:var(--card);padding:8px 12px;border-radius:8px;border:1px solid var(--border)">
              <input type="text" class="form-control" style="width:120px" placeholder="Name" value="Mid Exam" name="cname[]">
              <input type="number" class="form-control" style="width:70px" placeholder="Max" value="25" name="cmax[]" min="1">
              <button type="button" onclick="this.closest('.criteria-item').remove()" style="background:none;border:none;color:var(--danger);cursor:pointer"><i class="fa fa-trash"></i></button>
            </div>
            <div class="criteria-item flex items-center gap-2" style="background:var(--card);padding:8px 12px;border-radius:8px;border:1px solid var(--border)">
              <input type="text" class="form-control" style="width:120px" placeholder="Name" value="Final Exam" name="cname[]">
              <input type="number" class="form-control" style="width:70px" placeholder="Max" value="50" name="cmax[]" min="1">
              <button type="button" onclick="this.closest('.criteria-item').remove()" style="background:none;border:none;color:var(--danger);cursor:pointer"><i class="fa fa-trash"></i></button>
            </div>
            <div class="criteria-item flex items-center gap-2" style="background:var(--card);padding:8px 12px;border-radius:8px;border:1px solid var(--border)">
              <input type="text" class="form-control" style="width:120px" placeholder="Name" value="Attendance" name="cname[]">
              <input type="number" class="form-control" style="width:70px" placeholder="Max" value="10" name="cmax[]" min="1">
              <button type="button" onclick="this.closest('.criteria-item').remove()" style="background:none;border:none;color:var(--danger);cursor:pointer"><i class="fa fa-trash"></i></button>
            </div>
          </div>
          <div class="mt-3">
            <strong>Total Max: <span id="total-max" style="color:var(--primary)">105</span></strong>
          </div>
        </div>
      </div>

      <!-- Students mark grid -->
      <div class="table-wrap">
        <table id="marks-table">
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Student</th>
              <th id="th-criteria">Criteria</th>
              <th>Total</th>
              <th>%</th>
              <th>Grade</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($students as $i => $stu):
            // Existing marks
            $ex = $pdo->prepare("SELECT * FROM student_marks_custom WHERE student_id=? AND subject_id=? AND term=? AND academic_year=?");
            $ex->execute([$stu['id'], $teacher['subject_id'] ?? 0, $currentTerm, $currentYear]);
            $existing = $ex->fetch();
            $existingData = $existing ? json_decode($existing['criteria_data'], true) : [];
          ?>
          <tr id="row-<?= $stu['id'] ?>">
            <td><?= $i+1 ?></td>
            <td>
              <strong><?= sanitize($stu['name']) ?></strong><br>
              <span class="text-sm text-muted"><?= $stu['roll_number'] ?></span>
            </td>
            <td id="criteria-inputs-<?= $stu['id'] ?>">
              <!-- Populated by JS -->
              <span class="text-muted text-sm">Set criteria above first</span>
            </td>
            <td id="total-<?= $stu['id'] ?>">
              <?= $existing ? '<strong>'.$existing['total_mark'].'/'.$existing['max_mark'].'</strong>' : '—' ?>
            </td>
            <td id="pct-<?= $stu['id'] ?>">
              <?php if($existing): [$gl,$gc]=getGradeLetter($existing['percentage']); ?>
              <span style="color:<?= $gc ?>;font-weight:700"><?= $existing['percentage'] ?>%</span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td id="grade-<?= $stu['id'] ?>">
              <?php if($existing): ?><span class="grade-<?= str_replace('+','p',$existing['grade']) ?>"><?= $existing['grade'] ?></span><?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-primary" onclick="saveStudentMark(<?= $stu['id'] ?>,<?= $selectedClassId ?>,<?= $teacher['subject_id']??0 ?>)">
                <i class="fa fa-save"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="mt-4">
        <button class="btn btn-success" onclick="saveAllMarks(<?= $selectedClassId ?>,<?= $teacher['subject_id']??0 ?>)">
          <i class="fa fa-save"></i> Save All Marks
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ATTENDANCE -->
<div class="tab-pane <?= $tab==='attendance'?'active':'' ?>" id="tab-attendance">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-calendar-check"></i> Mark Attendance</h3>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="mark_attendance">
        <div class="flex gap-3 flex-wrap mb-4">
          <div class="form-group" style="margin:0">
            <label class="form-label">Class</label>
            <select name="grade_section" class="form-control" style="width:160px" onchange="selectAttClass(this)">
              <?php foreach($myClasses as $cl): ?>
              <option value="<?= $cl['grade'] ?>|<?= $cl['section'] ?>" <?= $selGrade==$cl['grade']&&$selSection==$cl['section']?'selected':'' ?>>
                Grade <?= $cl['class_name'] ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= $today ?>" id="att-date">
          </div>
          <input type="hidden" name="grade" id="att-grade" value="<?= $selGrade ?>">
          <input type="hidden" name="section" id="att-section" value="<?= $selSection ?>">
        </div>

        <?php if($students): ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th style="text-align:center">Present</th>
                <th style="text-align:center">Absent</th>
                <th style="text-align:center">Late</th>
                <th style="text-align:center">Excused</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($students as $stu):
              $aRow = $pdo->prepare("SELECT status FROM attendance WHERE student_id=? AND date=?");
              $aRow->execute([$stu['id'], $today]);
              $aStatus = $aRow->fetchColumn() ?: 'Present';
            ?>
            <tr>
              <td>
                <strong><?= sanitize($stu['name']) ?></strong>
                <span class="text-sm text-muted"> · <?= $stu['roll_number'] ?></span>
                <input type="hidden" name="student_ids[]" value="<?= $stu['id'] ?>">
              </td>
              <?php foreach(['Present','Absent','Late','Excused'] as $st): ?>
              <td style="text-align:center">
                <input type="radio" name="status[<?= $stu['id'] ?>]" value="<?= $st ?>"
                       <?= $aStatus===$st?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--primary)">
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Attendance</button>
        </div>
        <?php else: ?>
        <div class="text-center text-muted" style="padding:40px">
          <i class="fa fa-users" style="font-size:2rem;margin-bottom:12px"></i>
          <div>Select a class to see students</div>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<!-- STUDENTS -->
<div class="tab-pane <?= $tab==='students'?'active':'' ?>" id="tab-students">
  <div class="card mb-3">
    <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
      <?php foreach($myClasses as $cl): ?>
      <a href="teacher.php?tab=students&class=<?= $cl['class_name'] ?>"
         class="btn <?= $selClass===$cl['class_name']?'btn-primary':'btn-secondary' ?>">Grade <?= $cl['class_name'] ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3><i class="fa fa-users"></i> Students – Grade <?= $selClass ?></h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>ID</th><th>Gender</th><th>DOB</th><th>Parent</th><th>Phone</th><th>Marks</th></tr></thead>
        <tbody>
        <?php foreach($students as $i=>$stu):
          $sm = $pdo->prepare("SELECT percentage, grade FROM student_marks_custom WHERE student_id=? AND teacher_id=? AND term=?");
          $sm->execute([$stu['id'],$teacher_id,$currentTerm]);
          $sm = $sm->fetch();
        ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td>
            <?php if($stu['photo']): ?>
            <img src="<?= sanitize($stu['photo']) ?>" class="student-photo" alt="">
            <?php else: ?>
            <div class="student-photo"><?= strtoupper(substr($stu['name'],0,1)) ?></div>
            <?php endif; ?>
          </td>
          <td><strong><?= sanitize($stu['name']) ?></strong></td>
          <td><span class="badge badge-gray"><?= $stu['roll_number'] ?></span></td>
          <td><?= $stu['gender']??'—' ?></td>
          <td><?= $stu['date_of_birth']??'—' ?></td>
          <td><?= sanitize($stu['parent_name']??'—') ?></td>
          <td><?= sanitize($stu['parent_phone']??'—') ?></td>
          <td>
            <?php if($sm): [$gl,$gc]=getGradeLetter($sm['percentage']); ?>
            <span style="color:<?= $gc ?>;font-weight:700"><?= $sm['percentage'] ?>% (<?= $sm['grade'] ?>)</span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- HOMEROOM (if applicable) -->
<?php if($isHomeroom): ?>
<div class="tab-pane <?= $tab==='homeroom'?'active':'' ?>" id="tab-homeroom">
  <div class="card mb-4">
    <div class="card-header"><h3><i class="fa fa-house"></i> Homeroom – Grade <?= $homroomClass['class_name'] ?></h3></div>
    <div class="table-wrap">
      <?php
      $hrStudents = $pdo->prepare("
          SELECT s.*, AVG(smc.percentage) AS avg_pct,
                 (SELECT rank_position FROM class_rankings cr WHERE cr.student_id=s.id AND cr.class_id=? AND cr.term=? LIMIT 1) AS rank_pos
          FROM students s
          LEFT JOIN student_marks_custom smc ON smc.student_id=s.id AND smc.term=? AND smc.academic_year=?
          WHERE s.grade=? AND s.class_section=? AND s.status='active'
          GROUP BY s.id ORDER BY avg_pct DESC
      ");
      $hrStudents->execute([$homroomClass['id'],$currentTerm,$currentTerm,$currentYear,$homroomClass['grade'],$homroomClass['section']]);
      $hrStu = $hrStudents->fetchAll();
      ?>
      <table>
        <thead><tr><th>Rank</th><th>Student</th><th>ID</th><?php
          // Get all subjects
          $hrSubs = $pdo->prepare("
              SELECT DISTINCT sub.id, sub.name FROM class_subject_teachers cst
              JOIN subjects sub ON sub.id=cst.subject_id WHERE cst.class_id=?
          ");
          $hrSubs->execute([$homroomClass['id']]);
          $hrSubList = $hrSubs->fetchAll();
          foreach($hrSubList as $hs): ?><th><?= sanitize($hs['name']) ?></th><?php endforeach;
        ?><th>Average</th><th>Comment</th></tr></thead>
        <tbody>
        <?php foreach($hrStu as $i=>$hs):
          [$gl,$gc]=getGradeLetter($hs['avg_pct']??0);
          $comm = $pdo->prepare("SELECT comment_text FROM comments WHERE student_id=? AND teacher_id=? AND term=? LIMIT 1");
          $comm->execute([$hs['id'],$teacher_id,$currentTerm]);
          $existingComment = $comm->fetchColumn();
        ?>
        <tr>
          <td><strong><?= $hs['rank_pos']??($i+1) ?></strong></td>
          <td><?= sanitize($hs['name']) ?></td>
          <td><?= $hs['roll_number'] ?></td>
          <?php foreach($hrSubList as $sub):
            $mk = $pdo->prepare("SELECT percentage, grade FROM student_marks_custom WHERE student_id=? AND subject_id=? AND term=?");
            $mk->execute([$hs['id'],$sub['id'],$currentTerm]);
            $mkr = $mk->fetch();
            [$mgl,$mgc]=getGradeLetter($mkr['percentage']??0);
          ?>
          <td>
            <?php if($mkr): ?>
            <span style="color:<?= $mgc ?>;font-weight:700"><?= $mkr['percentage'] ?>%</span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td><span style="color:<?= $gc ?>;font-weight:700"><?= round($hs['avg_pct']??0,1) ?>%</span></td>
          <td>
            <form method="POST" class="flex gap-1">
              <input type="hidden" name="action" value="add_comment">
              <input type="hidden" name="student_id" value="<?= $hs['id'] ?>">
              <input type="text" name="comment_text" class="form-control" style="width:160px;font-size:.78rem"
                     placeholder="Add comment..." value="<?= sanitize($existingComment??'') ?>">
              <select name="type" class="form-control" style="width:90px;font-size:.78rem">
                <option value="general">General</option>
                <option value="academic">Academic</option>
                <option value="behavioral">Behavior</option>
              </select>
              <button class="btn btn-sm btn-primary"><i class="fa fa-save"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="flex gap-2">
    <a href="report_class.php?class_id=<?= $homroomClass['id'] ?>&term=<?= urlencode($currentTerm) ?>&year=<?= urlencode($currentYear) ?>" class="btn btn-primary" target="_blank">
      <i class="fa fa-print"></i> Print Report Cards
    </a>
    <a href="report_ranking.php?class_id=<?= $homroomClass['id'] ?>&term=<?= urlencode($currentTerm) ?>&year=<?= urlencode($currentYear) ?>" class="btn btn-secondary" target="_blank">
      <i class="fa fa-trophy"></i> Ranking Report
    </a>
  </div>
</div>
<?php endif; ?>

<!-- CALENDAR -->
<div class="tab-pane <?= $tab==='calendar'?'active':'' ?>" id="tab-calendar">
  <?php include 'includes/calendar_view.php'; ?>
</div>

<script>


function addCriteria(){
  const div=document.createElement('div');
  div.className='criteria-item flex items-center gap-2';
  div.style.cssText='background:var(--card);padding:8px 12px;border-radius:8px;border:1px solid var(--border)';
  div.innerHTML=`
    <input type="text" class="form-control" style="width:120px" placeholder="Name" name="cname[]">
    <input type="number" class="form-control" style="width:70px" placeholder="Max" value="10" name="cmax[]" min="1" oninput="updateMax()">
    <button type="button" onclick="this.closest('.criteria-item').remove();updateMax();" style="background:none;border:none;color:var(--danger);cursor:pointer"><i class="fa fa-trash"></i></button>
  `;
  document.getElementById('criteria-list').appendChild(div);
  updateMax();
}

function updateMax(){
  let t=0;
  document.querySelectorAll('[name="cmax[]"]').forEach(i=>t+=parseFloat(i.value||0));
  document.getElementById('total-max').textContent=t;
  renderCriteriaInputs();
}

function renderCriteriaInputs(){
  const names=[...document.querySelectorAll('[name="cname[]"]')].map(i=>i.value.trim());
  const maxes=[...document.querySelectorAll('[name="cmax[]"]')].map(i=>parseFloat(i.value||0));
  document.querySelectorAll('[id^="criteria-inputs-"]').forEach(cell=>{
    const sid=cell.id.replace('criteria-inputs-','');
    let html='<div class="flex gap-1 flex-wrap">';
    names.forEach((n,i)=>{
      if(!n) return;
      html+=`<div style="text-align:center">
        <div style="font-size:.7rem;color:var(--muted);margin-bottom:2px">${n}<br><span style="font-size:.65rem">(/${maxes[i]})</span></div>
        <input type="number" id="c_${sid}_${i}" step="0.5" min="0" max="${maxes[i]}"
               style="width:60px;padding:4px;border:1px solid var(--border);border-radius:6px;text-align:center;background:var(--input-bg);color:var(--text)"
               oninput="calcRow(${sid})">
      </div>`;
    });
    html+='</div>';
    cell.innerHTML=html;
  });
}

function calcRow(sid){
  const names=[...document.querySelectorAll('[name="cname[]"]')].map(i=>i.value.trim());
  const maxes=[...document.querySelectorAll('[name="cmax[]"]')].map(i=>parseFloat(i.value||0));
  let total=0, maxT=0;
  names.forEach((n,i)=>{
    const v=parseFloat(document.getElementById(`c_${sid}_${i}`)?.value||0);
    total+=v; maxT+=maxes[i];
  });
  const pct=maxT>0?Math.round(total/maxT*10000)/100:0;
  const grades=[['A+',90],['A',80],['B+',70],['B',60],['C',50],['D',40]];
  let gl='F'; for(const[g,t] of grades){if(pct>=t){gl=g;break;}}
  const colors={'A+':'#10b981','A':'#10b981','B+':'#3b82f6','B':'#3b82f6','C':'#f59e0b','D':'#f97316','F':'#ef4444'};
  document.getElementById(`total-${sid}`).innerHTML=`<strong>${total}/${maxT}</strong>`;
  document.getElementById(`pct-${sid}`).innerHTML=`<span style="color:${colors[gl]||'#666'};font-weight:700">${pct}%</span>`;
  document.getElementById(`grade-${sid}`).innerHTML=`<span style="color:${colors[gl]||'#666'};font-weight:700">${gl}</span>`;
}

function saveStudentMark(sid, cid, subid){
  const names=[...document.querySelectorAll('[name="cname[]"]')].map(i=>i.value.trim());
  const maxes=[...document.querySelectorAll('[name="cmax[]"]')].map(i=>parseFloat(i.value||0));
  const data={}, maxData={};
  let total=0, maxT=0, valid=true;
  names.forEach((n,i)=>{
    if(!n) return;
    const v=parseFloat(document.getElementById(`c_${sid}_${i}`)?.value??'');
    if(isNaN(v)){valid=false;return;}
    data[n]=v; maxData[n]=maxes[i]; total+=v; maxT+=maxes[i];
  });
  if(!valid){alert('Please fill all mark fields.');return;}
  const pct=maxT>0?Math.round(total/maxT*10000)/100:0;
  const body=new URLSearchParams({
    action:'save_mark_ajax',student_id:sid,subject_id:subid,class_id:cid,
    term:'<?= $currentTerm ?>',year:'<?= $currentYear ?>',
    criteria_data:JSON.stringify(data),criteria_max:JSON.stringify(maxData),
    total_mark:total,max_mark:maxT,percentage:pct
  });
  fetch('api.php',{method:'POST',body}).then(r=>r.json()).then(d=>{
    if(d.success){
      const btn=document.querySelector(`#row-${sid} .btn-primary`);
      if(btn){btn.innerHTML='<i class="fa fa-check"></i>';btn.style.background='var(--success)';
        setTimeout(()=>{btn.innerHTML='<i class="fa fa-save"></i>';btn.style.background='';},2000);}
    } else {alert('Error: '+(d.error||'Failed'));}
  });
}

function saveAllMarks(cid, subid){
  document.querySelectorAll('[id^="row-"]').forEach(row=>{
    const sid=row.id.replace('row-','');
    saveStudentMark(parseInt(sid),cid,subid);
  });
}

function selectAttClass(sel){
  const [g,s]=sel.value.split('|');
  document.getElementById('att-grade').value=g;
  document.getElementById('att-section').value=s;
}

// Init criteria inputs
document.addEventListener('DOMContentLoaded',function(){
  updateMax();
  document.querySelectorAll('[name="cname[]"],[name="cmax[]"]').forEach(el=>el.addEventListener('input',function(){updateMax();}));
});
</script>

<?php include 'layout_end.php'; ?>
