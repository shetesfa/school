<?php
require_once 'config.php';
requireRole('superadmin');

$pageTitle  = 'Admin Dashboard';
$activeMenu = $_GET['tab'] ?? 'dashboard';
if ($activeMenu === 'admin.php') $activeMenu = 'dashboard';

$currentTerm = getSchoolSetting($pdo, 'current_term', 'Term 1');
$currentYear = getSchoolSetting($pdo, 'current_year', '2025/2026');

$msg = ''; $err = '';

// ── POST Handlers ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add Student
    if ($action === 'add_student') {
        $name    = sanitize($_POST['name']);
        $grade   = sanitize($_POST['grade']);
        $section = sanitize($_POST['section']);
        $gender  = sanitize($_POST['gender']);
        $dob     = $_POST['dob'] ?? null;
        $pname   = sanitize($_POST['parent_name']);
        $pphone  = sanitize($_POST['parent_phone']);
        $pemail  = sanitize($_POST['parent_email']);
        $nation  = sanitize($_POST['nationality'] ?? '');
        $rollno  = generateStudentID($pdo);

        // Get class teacher
        $cl = $pdo->prepare("SELECT teacher_id FROM classes WHERE grade=? AND section=? LIMIT 1");
        $cl->execute([$grade, $section]);
        $cls = $cl->fetch();
        $tid = $cls['teacher_id'] ?? null;

        $st = $pdo->prepare("INSERT INTO students (name,roll_number,grade,class_section,gender,date_of_birth,nationality,parent_name,parent_phone,parent_email,teacher_id,academic_year) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        if ($st->execute([$name,$rollno,$grade,$section,$gender,$dob,$nation,$pname,$pphone,$pemail,$tid,$currentYear])) {
            $msg = "Student $name added successfully (ID: $rollno)";
        } else { $err = 'Failed to add student.'; }
    }

    // Edit Student
    if ($action === 'edit_student') {
        $id      = (int)$_POST['id'];
        $name    = sanitize($_POST['name']);
        $grade   = sanitize($_POST['grade']);
        $section = sanitize($_POST['section']);
        $gender  = sanitize($_POST['gender']);
        $dob     = $_POST['dob'] ?? null;
        $pname   = sanitize($_POST['parent_name']);
        $pphone  = sanitize($_POST['parent_phone']);
        $pemail  = sanitize($_POST['parent_email']);
        $nation  = sanitize($_POST['nationality'] ?? '');
        $st = $pdo->prepare("UPDATE students SET name=?,grade=?,class_section=?,gender=?,date_of_birth=?,nationality=?,parent_name=?,parent_phone=?,parent_email=? WHERE id=?");
        if ($st->execute([$name,$grade,$section,$gender,$dob,$nation,$pname,$pphone,$pemail,$id])) {
            $msg = 'Student updated.';
        } else { $err = 'Failed to update student.'; }
    }

    // Delete Student
    if ($action === 'delete_student') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        $msg = 'Student deleted.';
    }

    // Add Teacher
    if ($action === 'add_teacher') {
        $name  = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $subid = (int)$_POST['subject_id'];
        $qual  = sanitize($_POST['qualification'] ?? '');
        $rawpw = $_POST['password'] ?? '';
        // Use known teacher123 hash if no password supplied, else hash the given password
        $teacher123hash = '$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu';
        $pass  = $rawpw ? password_hash($rawpw, PASSWORD_DEFAULT) : $teacher123hash;
        $pdo->beginTransaction();
        try {
            $u = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'teacher')");
            $u->execute([$name,$email,$pass]);
            $uid = $pdo->lastInsertId();
            $t = $pdo->prepare("INSERT INTO teachers (user_id,subject_id,phone,qualification) VALUES (?,?,?,?)");
            $t->execute([$uid,$subid,$phone,$qual]);
            $pdo->commit();
            $msg = "Teacher $name added.";
        } catch(Exception $e) { $pdo->rollBack(); $err = 'Failed: '.$e->getMessage(); }
    }

    // Delete Teacher
    if ($action === 'delete_teacher') {
        $id = (int)$_POST['id'];
        $t = $pdo->prepare("SELECT user_id FROM teachers WHERE id=?");
        $t->execute([$id]);
        $row = $t->fetch();
        if ($row) $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$row['user_id']]);
        $msg = 'Teacher deleted.';
    }

    // Add Class
    if ($action === 'add_class') {
        $grade   = sanitize($_POST['grade']);
        $section = strtoupper(sanitize($_POST['section']));
        $tid     = (int)$_POST['teacher_id'];
        $room    = sanitize($_POST['room'] ?? '');
        $st = $pdo->prepare("INSERT INTO classes (grade,section,teacher_id,room_number,created_by) VALUES (?,?,?,?,?)");
        if ($st->execute([$grade,$section,$tid?:null,$room,$_SESSION['user_id']])) {
            $msg = "Class $grade$section created.";
        } else { $err = 'Class may already exist.'; }
    }

    // Assign subject-teacher to class
    if ($action === 'assign_subject') {
        $cid  = (int)$_POST['class_id'];
        $sid  = (int)$_POST['subject_id'];
        $tid  = (int)$_POST['teacher_id'];
        $st = $pdo->prepare("INSERT INTO class_subject_teachers (class_id,subject_id,teacher_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE teacher_id=?");
        if ($st->execute([$cid,$sid,$tid,$tid])) { $msg = 'Assignment saved.'; }
        else { $err = 'Failed.'; }
    }

    // Lock / Unlock marks
    if ($action === 'lock_marks') {
        $class_id = (int)$_POST['class_id'];
        $term     = sanitize($_POST['term']);
        $year     = sanitize($_POST['year']);
        $lock     = (int)$_POST['lock_state'];

        if ($lock) {
            $pdo->prepare("INSERT IGNORE INTO semester_locks (class_id,term,academic_year,locked_by,is_locked) VALUES (?,?,?,?,1)")
                ->execute([$class_id,$term,$year,$_SESSION['user_id']]);
            // Lock all marks for this class+term
            $pdo->prepare("UPDATE student_marks_custom SET is_locked=1 WHERE class_id=? AND term=? AND academic_year=?")
                ->execute([$class_id,$term,$year]);
            $msg = 'Marks locked.';
        } else {
            $pdo->prepare("DELETE FROM semester_locks WHERE class_id=? AND term=? AND academic_year=?")
                ->execute([$class_id,$term,$year]);
            $pdo->prepare("UPDATE student_marks_custom SET is_locked=0 WHERE class_id=? AND term=? AND academic_year=?")
                ->execute([$class_id,$term,$year]);
            $msg = 'Marks unlocked.';
        }
    }

    // Visibility setting
    if ($action === 'set_visibility') {
        $v = (int)$_POST['visibility'];
        $pdo->prepare("UPDATE school_settings SET setting_value=? WHERE setting_key='result_visibility'")->execute([$v]);
        $msg = 'Visibility updated to Mode '.$v;
    }

    // Add Calendar Event
    if ($action === 'add_event') {
        $st = $pdo->prepare("INSERT INTO calendar_events (title,description,event_date,end_date,type,color,created_by) VALUES (?,?,?,?,?,?,?)");
        if ($st->execute([
            sanitize($_POST['title']),
            sanitize($_POST['description']??''),
            $_POST['event_date'],
            $_POST['end_date']??null,
            sanitize($_POST['type']),
            sanitize($_POST['color']??'#667eea'),
            $_SESSION['user_id']
        ])) { $msg = 'Event added.'; }
        else { $err = 'Failed.'; }
    }

    // Delete Event
    if ($action === 'delete_event') {
        $pdo->prepare("DELETE FROM calendar_events WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = 'Event deleted.';
    }

    // Add Announcement
    if ($action === 'add_announcement') {
        $st = $pdo->prepare("INSERT INTO announcements (title,message,created_by,target_role,priority) VALUES (?,?,?,?,?)");
        if ($st->execute([
            sanitize($_POST['title']),
            sanitize($_POST['message']),
            $_SESSION['user_id'],
            sanitize($_POST['target_role']),
            sanitize($_POST['priority'])
        ])) { $msg = 'Announcement published.'; }
    }

    // Add Subject
    if ($action === 'add_subject') {
        $st = $pdo->prepare("INSERT INTO subjects (name,code,color) VALUES (?,?,?)");
        if ($st->execute([sanitize($_POST['name']),sanitize($_POST['code']),sanitize($_POST['color']??'#667eea')])) {
            $msg = 'Subject added.';
        }
    }

    // Update settings
    if ($action === 'save_settings') {
        $keys = ['school_name','school_address','school_phone','current_term','current_year','pass_percentage'];
        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                $pdo->prepare("UPDATE school_settings SET setting_value=? WHERE setting_key=?")->execute([sanitize($_POST[$k]),$k]);
            }
        }
        $msg = 'Settings saved.';
    }

    header('Location: admin.php?tab='.($activeMenu).'&msg='.urlencode($msg).'&err='.urlencode($err));
    exit();
}

if (isset($_GET['msg'])) $msg = sanitize($_GET['msg']);
if (isset($_GET['err'])) $err = sanitize($_GET['err']);
$activeMenu = sanitize($_GET['tab'] ?? 'dashboard');

// ── Fetch Data ───────────────────────────────────────────────────
$total_students = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$total_classes  = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$total_parents  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='parent' AND status='active'")->fetchColumn();

$today = date('Y-m-d');
$att_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date='$today' AND status='Present'")->fetchColumn();

$visibility = getVisibilityMode($pdo);

$classes = $pdo->query("
    SELECT c.*, CONCAT(c.grade,c.section) AS class_name,
           u.name AS homeroom_name, COUNT(DISTINCT s.id) AS student_count
    FROM classes c
    LEFT JOIN teachers t ON t.id=c.teacher_id
    LEFT JOIN users u ON u.id=t.user_id
    LEFT JOIN students s ON s.grade=c.grade AND s.class_section=c.section AND s.status='active'
    GROUP BY c.id ORDER BY c.grade, c.section
")->fetchAll();

$students = $pdo->query("
    SELECT s.*, CONCAT(s.grade,s.class_section) AS class_name FROM students s
    WHERE s.status='active' ORDER BY s.grade, s.class_section, s.name LIMIT 200
")->fetchAll();

$teachers = $pdo->query("
    SELECT t.*, u.name, u.email, u.status, sub.name AS subject_name, sub.color AS subj_color,
           COUNT(DISTINCT cst.class_id) AS classes_count
    FROM teachers t
    JOIN users u ON u.id=t.user_id
    LEFT JOIN subjects sub ON sub.id=t.subject_id
    LEFT JOIN class_subject_teachers cst ON cst.teacher_id=t.id
    GROUP BY t.id ORDER BY u.name
")->fetchAll();

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

$events = $pdo->query("
    SELECT * FROM calendar_events ORDER BY event_date ASC
")->fetchAll();

$announcements = $pdo->query("
    SELECT a.*, u.name AS author FROM announcements a
    JOIN users u ON u.id=a.created_by
    WHERE a.is_active=1 ORDER BY a.created_at DESC LIMIT 20
")->fetchAll();

$settings_rows = $pdo->query("SELECT * FROM school_settings")->fetchAll();
$settings = [];
foreach($settings_rows as $sr) $settings[$sr['setting_key']] = $sr['setting_value'];

// Attendance stats
$att_stats = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM attendance
    WHERE date BETWEEN DATE_SUB(CURDATE(),INTERVAL 30 DAY) AND CURDATE()
    GROUP BY status
")->fetchAll();
$att_map = [];
foreach($att_stats as $a) $att_map[$a['status']] = $a['cnt'];

// Top performers
$top = $pdo->query("
    SELECT s.name, s.roll_number, CONCAT(s.grade,s.class_section) AS class_name,
           AVG(smc.percentage) AS avg_pct
    FROM student_marks_custom smc
    JOIN students s ON s.id=smc.student_id
    WHERE smc.term='$currentTerm' AND smc.academic_year='$currentYear'
    GROUP BY s.id HAVING COUNT(smc.id)>=1
    ORDER BY avg_pct DESC LIMIT 5
")->fetchAll();

include 'layout.php';
?>

<?php if($msg): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i><?= $msg ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i><?= $err ?></div><?php endif; ?>

<div class="tabs">
  <?php
  $tabList = [
    ['key'=>'dashboard','icon'=>'fa-gauge',          'label'=>'Overview'],
    ['key'=>'students', 'icon'=>'fa-users',          'label'=>'Students'],
    ['key'=>'teachers', 'icon'=>'fa-chalkboard',     'label'=>'Teachers'],
    ['key'=>'classes',  'icon'=>'fa-door-open',      'label'=>'Classes'],
    ['key'=>'marks',    'icon'=>'fa-star',           'label'=>'Marks & Lock'],
    ['key'=>'attend',   'icon'=>'fa-calendar-check', 'label'=>'Attendance'],
    ['key'=>'calendar', 'icon'=>'fa-calendar',       'label'=>'Calendar'],
    ['key'=>'reports',  'icon'=>'fa-file-alt',       'label'=>'Reports'],
    ['key'=>'announce', 'icon'=>'fa-bullhorn',       'label'=>'Announce'],
    ['key'=>'settings', 'icon'=>'fa-gear',           'label'=>'Settings'],
  ];
  foreach($tabList as $t): ?>
  <button class="tab-btn <?= $activeMenu===$t['key']?'active':'' ?>"
          onclick="switchTab('<?= $t['key'] ?>')">
    <i class="fa <?= $t['icon'] ?>"></i> <?= $t['label'] ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- ═══════════════ OVERVIEW ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='dashboard'?'active':'' ?>" id="tab-dashboard">
  <div class="stats-row">
    <div class="stat-card" style="border-color:#6366f1">
      <div class="stat-icon" style="background:#ede9fe;color:#6366f1"><i class="fa fa-users"></i></div>
      <div><div class="stat-num"><?= $total_students ?></div><div class="stat-label">Students</div></div>
    </div>
    <div class="stat-card" style="border-color:#10b981">
      <div class="stat-icon" style="background:#dcfce7;color:#10b981"><i class="fa fa-chalkboard"></i></div>
      <div><div class="stat-num"><?= $total_teachers ?></div><div class="stat-label">Teachers</div></div>
    </div>
    <div class="stat-card" style="border-color:#3b82f6">
      <div class="stat-icon" style="background:#dbeafe;color:#3b82f6"><i class="fa fa-door-open"></i></div>
      <div><div class="stat-num"><?= $total_classes ?></div><div class="stat-label">Classes</div></div>
    </div>
    <div class="stat-card" style="border-color:#f59e0b">
      <div class="stat-icon" style="background:#fef9c3;color:#ca8a04"><i class="fa fa-heart"></i></div>
      <div><div class="stat-num"><?= $total_parents ?></div><div class="stat-label">Parents</div></div>
    </div>
    <div class="stat-card" style="border-color:#22c55e">
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a"><i class="fa fa-calendar-check"></i></div>
      <div><div class="stat-num"><?= $att_today ?></div><div class="stat-label">Present Today</div></div>
    </div>
  </div>

  <div class="grid-2">
    <!-- Classes Overview -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-door-open"></i> Classes</h3></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Class</th><th>Homeroom</th><th>Students</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($classes as $cl): ?>
          <tr>
            <td><strong>Grade <?= $cl['grade'] ?><?= $cl['section'] ?></strong></td>
            <td><?= sanitize($cl['homeroom_name'] ?? '—') ?></td>
            <td><span class="badge badge-primary"><?= $cl['student_count'] ?></span></td>
            <td><span class="badge badge-success">Active</span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top Performers -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-trophy"></i> Top Performers – <?= $currentTerm ?></h3></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Student</th><th>Class</th><th>Average</th></tr></thead>
          <tbody>
          <?php foreach($top as $i=>$tp): ?>
          <?php [$gl,$gc] = getGradeLetter($tp['avg_pct']); ?>
          <tr>
            <td><strong><?= $i+1 ?></strong></td>
            <td><?= sanitize($tp['name']) ?></td>
            <td><?= $tp['class_name'] ?></td>
            <td>
              <div class="flex items-center gap-2">
                <div class="progress flex-1" style="min-width:60px">
                  <div class="progress-bar" style="width:<?= min($tp['avg_pct'],100) ?>%;background:<?= $gc ?>"></div>
                </div>
                <span style="color:<?= $gc ?>;font-weight:700"><?= round($tp['avg_pct'],1) ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($top)): ?>
          <tr><td colspan="4" class="text-center text-muted">No marks entered yet</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Attendance summary -->
  <div class="card mt-4">
    <div class="card-header"><h3><i class="fa fa-chart-bar"></i> Attendance (Last 30 Days)</h3></div>
    <div class="card-body">
      <div class="grid-3">
        <?php
        $attItems=[['Present','success','fa-check'],['Absent','danger','fa-xmark'],['Late','warning','fa-clock'],['Excused','info','fa-file']];
        foreach($attItems as [$s,$c,$ic]):
          $v=$att_map[$s]??0;
        ?>
        <div style="text-align:center;padding:16px;border-radius:12px;background:var(--bg)">
          <i class="fa <?= $ic ?>" style="font-size:1.8rem;color:var(--<?= $c ?>);margin-bottom:8px"></i>
          <div style="font-size:1.8rem;font-weight:800;color:var(--text)"><?= $v ?></div>
          <div class="text-muted text-sm"><?= $s ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Visibility Control -->
  <div class="card mt-4">
    <div class="card-header">
      <h3><i class="fa fa-eye"></i> Result Visibility Mode</h3>
      <span class="badge badge-primary">Mode <?= $visibility ?> Active</span>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="set_visibility">
        <div class="grid-2" style="gap:10px;margin-bottom:16px">
          <?php
          $modes=[
            [1,'Hidden','Parents see nothing','fa-eye-slash','danger'],
            [2,'Attendance Only','Parents see attendance only','fa-calendar-check','warning'],
            [3,'Summary','Average + Rank + Attendance','fa-chart-bar','info'],
            [4,'Full Report','All marks + comments visible','fa-file-alt','success'],
          ];
          foreach($modes as [$v,$t,$d,$ic,$c]): ?>
          <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s"
                 class="vis-option <?= $visibility==$v?'vis-active':'' ?>" data-v="<?= $v ?>">
            <input type="radio" name="visibility" value="<?= $v ?>" <?= $visibility==$v?'checked':'' ?> style="accent-color:var(--primary)">
            <i class="fa <?= $ic ?>" style="color:var(--<?= $c ?>);font-size:1.1rem;width:20px;text-align:center"></i>
            <div><div style="font-weight:700"><?= $t ?></div><div class="text-sm text-muted"><?= $d ?></div></div>
          </label>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-primary"><i class="fa fa-save"></i> Apply Visibility</button>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════ STUDENTS ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='students'?'active':'' ?>" id="tab-students">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-users"></i> Students (<?= count($students) ?>)</h3>
      <div class="flex gap-2 flex-wrap">
        <input type="text" id="stu-search" placeholder="🔍 Search..." class="form-control" style="width:200px" oninput="filterTable(this,'stu-table')">
        <select id="stu-grade" class="form-control" style="width:120px" onchange="filterByGrade()">
          <option value="">All Grades</option>
          <?php $grades=array_unique(array_column($students,'grade')); sort($grades); foreach($grades as $g): ?>
          <option value="<?= $g ?>">Grade <?= $g ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" onclick="openModal('modal-add-student')"><i class="fa fa-plus"></i> Add Student</button>
      </div>
    </div>
    <div class="table-wrap">
      <table id="stu-table">
        <thead><tr><th>ID</th><th>Photo</th><th>Name</th><th>Class</th><th>Gender</th><th>Parent</th><th>Phone</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($students as $s): ?>
        <tr data-grade="<?= $s['grade'] ?>">
          <td><span class="badge badge-gray"><?= $s['roll_number'] ?></span></td>
          <td>
            <?php if($s['photo']): ?>
            <img src="<?= sanitize($s['photo']) ?>" class="student-photo" alt="">
            <?php else: ?>
            <div class="student-photo" style="background:<?= '#'.substr(md5($s['name']),0,6) ?>"><?= strtoupper(substr($s['name'],0,1)) ?></div>
            <?php endif; ?>
          </td>
          <td><strong><?= sanitize($s['name']) ?></strong></td>
          <td>Grade <?= $s['class_name'] ?></td>
          <td><?= $s['gender'] ?? '—' ?></td>
          <td><?= sanitize($s['parent_name'] ?? '—') ?></td>
          <td><?= sanitize($s['parent_phone'] ?? '—') ?></td>
          <td>
            <div class="flex gap-2">
              <button class="btn btn-sm btn-secondary" onclick='editStudent(<?= json_encode($s) ?>)'><i class="fa fa-edit"></i></button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this student?')">
                <input type="hidden" name="action" value="delete_student">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ TEACHERS ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='teachers'?'active':'' ?>" id="tab-teachers">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-chalkboard"></i> Teachers (<?= count($teachers) ?>)</h3>
      <button class="btn btn-primary" onclick="openModal('modal-add-teacher')"><i class="fa fa-plus"></i> Add Teacher</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Subject</th><th>Classes</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($teachers as $t): ?>
        <tr>
          <td><strong><?= sanitize($t['name']) ?></strong></td>
          <td><?= sanitize($t['email']) ?></td>
          <td>
            <?php if($t['subject_name']): ?>
            <span class="badge" style="background:<?= $t['subj_color'] ?>22;color:<?= $t['subj_color'] ?>"><?= sanitize($t['subject_name']) ?></span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td><span class="badge badge-info"><?= $t['classes_count'] ?></span></td>
          <td><?= sanitize($t['phone'] ?? '—') ?></td>
          <td><span class="badge <?= $t['status']==='active'?'badge-success':'badge-danger' ?>"><?= ucfirst($t['status']) ?></span></td>
          <td>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete teacher?')">
              <input type="hidden" name="action" value="delete_teacher">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ CLASSES ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='classes'?'active':'' ?>" id="tab-classes">
  <div class="grid-2 mb-4">
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-plus"></i> Add Class</h3></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_class">
          <div class="form-group">
            <label class="form-label">Grade</label>
            <select name="grade" class="form-control" required>
              <?php for($g=1;$g<=12;$g++): ?><option value="<?= $g ?>">Grade <?= $g ?></option><?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Section</label>
            <select name="section" class="form-control" required>
              <?php foreach(['A','B','C','D','E','F'] as $sec): ?><option><?= $sec ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Homeroom Teacher</label>
            <select name="teacher_id" class="form-control">
              <option value="">— Select —</option>
              <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Room Number</label>
            <input type="text" name="room" class="form-control" placeholder="e.g. 201">
          </div>
          <button class="btn btn-primary w-full"><i class="fa fa-save"></i> Create Class</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fa fa-link"></i> Assign Subject to Class</h3></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="assign_subject">
          <div class="form-group">
            <label class="form-label">Class</label>
            <select name="class_id" class="form-control" required>
              <?php foreach($classes as $cl): ?><option value="<?= $cl['id'] ?>">Grade <?= $cl['class_name'] ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Subject</label>
            <select name="subject_id" class="form-control" required>
              <?php foreach($subjects as $sub): ?><option value="<?= $sub['id'] ?>"><?= sanitize($sub['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Teacher</label>
            <select name="teacher_id" class="form-control" required>
              <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-primary w-full"><i class="fa fa-save"></i> Assign</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="fa fa-table"></i> All Classes</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Class</th><th>Homeroom Teacher</th><th>Students</th><th>Room</th><th>Subjects</th></tr></thead>
        <tbody>
        <?php foreach($classes as $cl): ?>
        <?php
        $subs = $pdo->prepare("
            SELECT sub.name, sub.color FROM class_subject_teachers cst
            JOIN subjects sub ON sub.id=cst.subject_id WHERE cst.class_id=?
        ");
        $subs->execute([$cl['id']]);
        $classSubs = $subs->fetchAll();
        ?>
        <tr>
          <td><strong>Grade <?= $cl['class_name'] ?></strong></td>
          <td><?= sanitize($cl['homeroom_name'] ?? '—') ?></td>
          <td><span class="badge badge-primary"><?= $cl['student_count'] ?></span></td>
          <td><?= sanitize($cl['room_number'] ?? '—') ?></td>
          <td>
            <?php foreach($classSubs as $cs): ?>
            <span class="badge" style="background:<?= $cs['color'] ?>22;color:<?= $cs['color'] ?>;margin:2px"><?= sanitize($cs['name']) ?></span>
            <?php endforeach; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ MARKS & LOCK ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='marks'?'active':'' ?>" id="tab-marks">
  <div class="card mb-4">
    <div class="card-header"><h3><i class="fa fa-lock"></i> Mark Lock Control</h3></div>
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Class</th><th>Term</th><th>Year</th><th>Marks Entered</th><th>Lock Status</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach($classes as $cl):
            $lockRow = $pdo->prepare("SELECT * FROM semester_locks WHERE class_id=? AND term=? AND academic_year=?");
            $lockRow->execute([$cl['id'],$currentTerm,$currentYear]);
            $lk = $lockRow->fetch();
            $isLocked = $lk && $lk['is_locked'];
            $marksCnt = $pdo->prepare("SELECT COUNT(*) FROM student_marks_custom WHERE class_id=? AND term=? AND academic_year=?");
            $marksCnt->execute([$cl['id'],$currentTerm,$currentYear]);
            $mc = $marksCnt->fetchColumn();
          ?>
          <tr>
            <td><strong>Grade <?= $cl['class_name'] ?></strong></td>
            <td><?= $currentTerm ?></td>
            <td><?= $currentYear ?></td>
            <td><span class="badge badge-info"><?= $mc ?> records</span></td>
            <td>
              <?php if($isLocked): ?>
              <span class="badge badge-danger"><i class="fa fa-lock"></i> Locked</span>
              <?php else: ?>
              <span class="badge badge-success"><i class="fa fa-unlock"></i> Open</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST">
                <input type="hidden" name="action" value="lock_marks">
                <input type="hidden" name="class_id" value="<?= $cl['id'] ?>">
                <input type="hidden" name="term" value="<?= $currentTerm ?>">
                <input type="hidden" name="year" value="<?= $currentYear ?>">
                <input type="hidden" name="lock_state" value="<?= $isLocked?0:1 ?>">
                <button class="btn btn-sm <?= $isLocked?'btn-warning':'btn-danger' ?>">
                  <i class="fa <?= $isLocked?'fa-unlock':'fa-lock' ?>"></i>
                  <?= $isLocked?'Unlock':'Lock' ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- All marks view -->
  <div class="card">
    <div class="card-header"><h3><i class="fa fa-star"></i> All Marks – <?= $currentTerm ?></h3></div>
    <div class="table-wrap">
      <?php
      $allMarks = $pdo->query("
          SELECT smc.*, s.name AS student_name, s.roll_number, CONCAT(s.grade,s.class_section) AS class_name,
                 sub.name AS subject_name, sub.color AS subj_color
          FROM student_marks_custom smc
          JOIN students s ON s.id=smc.student_id
          JOIN subjects sub ON sub.id=smc.subject_id
          WHERE smc.term='$currentTerm' AND smc.academic_year='$currentYear'
          ORDER BY s.grade, s.class_section, s.name
      ")->fetchAll();
      ?>
      <table>
        <thead><tr><th>Student</th><th>Class</th><th>Subject</th><th>Total</th><th>%</th><th>Grade</th><th>Locked</th></tr></thead>
        <tbody>
        <?php if(empty($allMarks)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:32px">No marks entered yet</td></tr>
        <?php else: foreach($allMarks as $mk): [$gl,$gc]=getGradeLetter($mk['percentage']); ?>
        <tr>
          <td><?= sanitize($mk['student_name']) ?><br><span class="text-sm text-muted"><?= $mk['roll_number'] ?></span></td>
          <td><?= $mk['class_name'] ?></td>
          <td><span class="badge" style="background:<?= $mk['subj_color'] ?>22;color:<?= $mk['subj_color'] ?>"><?= sanitize($mk['subject_name']) ?></span></td>
          <td><?= $mk['total_mark'] ?>/<?= $mk['max_mark'] ?></td>
          <td><span style="color:<?= $gc ?>;font-weight:700"><?= $mk['percentage'] ?>%</span></td>
          <td><span class="grade-<?= str_replace('+','p',$mk['grade']) ?>"><?= $mk['grade'] ?></span></td>
          <td><?= $mk['is_locked'] ? '<span class="badge badge-danger">🔒</span>' : '<span class="badge badge-success">🔓</span>' ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ ATTENDANCE ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='attend'?'active':'' ?>" id="tab-attend">
  <div class="card mb-4">
    <div class="card-header"><h3><i class="fa fa-calendar-check"></i> Mark Today's Attendance</h3>
      <span class="badge badge-info"><?= date('l, d M Y') ?></span>
    </div>
    <div class="card-body">
      <div class="flex gap-2 mb-4 flex-wrap">
        <select id="att-class" class="form-control" style="width:180px" onchange="loadClassStudents()">
          <option value="">Select Class</option>
          <?php foreach($classes as $cl): ?>
          <option value="<?= $cl['id'] ?>" data-grade="<?= $cl['grade'] ?>" data-section="<?= $cl['section'] ?>">Grade <?= $cl['class_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <form method="POST" action="api.php" id="att-form">
        <input type="hidden" name="action" value="mark_attendance_admin">
        <input type="hidden" name="date" value="<?= $today ?>">
        <div id="att-students-list" class="text-muted">← Select a class to load students</div>
        <div id="att-submit" class="hidden mt-4">
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Attendance</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Attendance report -->
  <div class="card">
    <div class="card-header"><h3><i class="fa fa-chart-bar"></i> Recent Attendance Log</h3></div>
    <div class="table-wrap">
      <?php
      $attLog = $pdo->query("
          SELECT a.*, s.name AS sname, s.roll_number, CONCAT(s.grade,s.class_section) AS class_name
          FROM attendance a JOIN students s ON s.id=a.student_id
          ORDER BY a.date DESC, s.grade, s.name LIMIT 100
      ")->fetchAll();
      ?>
      <table>
        <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Status</th><th>Remarks</th></tr></thead>
        <tbody>
        <?php foreach($attLog as $al): ?>
        <tr>
          <td><?= $al['date'] ?></td>
          <td><?= sanitize($al['sname']) ?></td>
          <td><?= $al['class_name'] ?></td>
          <td>
            <?php $bc=['Present'=>'success','Absent'=>'danger','Late'=>'warning','Excused'=>'info']; ?>
            <span class="badge badge-<?= $bc[$al['status']] ?>"><?= $al['status'] ?></span>
          </td>
          <td class="text-muted text-sm"><?= sanitize($al['remarks'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════ CALENDAR ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='calendar'?'active':'' ?>" id="tab-calendar">
  <div class="grid-2">
    <!-- Add Event -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-plus"></i> Add Event</h3></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_event">
          <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="event">School Event</option><option value="holiday">Holiday</option>
              <option value="exam">Exam Period</option><option value="meeting">Parent Meeting</option>
              <option value="graduation">Graduation</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="event_date" class="form-control" required></div>
          <div class="form-group"><label class="form-label">End Date (optional)</label><input type="date" name="end_date" class="form-control"></div>
          <div class="form-group"><label class="form-label">Color</label><input type="color" name="color" class="form-control" value="#6366f1" style="height:42px"></div>
          <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
          <button class="btn btn-primary w-full"><i class="fa fa-save"></i> Add Event</button>
        </form>
      </div>
    </div>

    <!-- Events List -->
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-list"></i> Events</h3></div>
      <div style="max-height:480px;overflow-y:auto">
      <?php
      $typeLabels=['holiday'=>'Holiday','exam'=>'Exam','meeting'=>'Meeting','event'=>'Event','graduation'=>'Graduation','other'=>'Other'];
      foreach($events as $ev): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border)">
        <div style="width:10px;height:10px;border-radius:50%;background:<?= sanitize($ev['color']) ?>;flex-shrink:0"></div>
        <div style="flex:1">
          <div style="font-weight:600"><?= sanitize($ev['title']) ?></div>
          <div class="text-sm text-muted"><?= $ev['event_date'] ?><?= $ev['end_date']?' → '.$ev['end_date']:'' ?> · <?= $typeLabels[$ev['type']]??$ev['type'] ?></div>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="delete_event">
          <input type="hidden" name="id" value="<?= $ev['id'] ?>">
          <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
        </form>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ REPORTS ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='reports'?'active':'' ?>" id="tab-reports">
  <div class="grid-3 mb-4">
    <a href="report_class.php?term=<?= urlencode($currentTerm) ?>&year=<?= urlencode($currentYear) ?>" class="card" style="text-decoration:none;padding:24px;text-align:center;transition:transform .2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
      <i class="fa fa-file-alt" style="font-size:2rem;color:var(--primary);margin-bottom:12px"></i>
      <div style="font-weight:700;color:var(--text)">Class Report Cards</div>
      <div class="text-sm text-muted">Print all students in a class</div>
    </a>
    <a href="report_attendance.php" class="card" style="text-decoration:none;padding:24px;text-align:center;transition:transform .2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
      <i class="fa fa-calendar-check" style="font-size:2rem;color:var(--success);margin-bottom:12px"></i>
      <div style="font-weight:700;color:var(--text)">Attendance Report</div>
      <div class="text-sm text-muted">Monthly & student attendance</div>
    </a>
    <a href="report_ranking.php?term=<?= urlencode($currentTerm) ?>&year=<?= urlencode($currentYear) ?>" class="card" style="text-decoration:none;padding:24px;text-align:center;transition:transform .2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
      <i class="fa fa-trophy" style="font-size:2rem;color:var(--warning);margin-bottom:12px"></i>
      <div style="font-weight:700;color:var(--text)">Ranking Report</div>
      <div class="text-sm text-muted">Top students per class</div>
    </a>
  </div>
  <!-- Quick Print: All Class Report -->
  <div class="card">
    <div class="card-header"><h3><i class="fa fa-print"></i> Quick Report Generator</h3></div>
    <div class="card-body">
      <div class="flex gap-3 flex-wrap items-center">
        <select id="rpt-class" class="form-control" style="width:180px">
          <option value="">All Classes</option>
          <?php foreach($classes as $cl): ?>
          <option value="<?= $cl['id'] ?>">Grade <?= $cl['class_name'] ?></option>
          <?php endforeach; ?>
        </select>
        <select id="rpt-term" class="form-control" style="width:140px">
          <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option><option value="Annual">Annual</option>
        </select>
        <button class="btn btn-primary" onclick="generateReport()"><i class="fa fa-file-pdf"></i> Generate Report</button>
        <button class="btn btn-secondary" onclick="window.print()"><i class="fa fa-print"></i> Print Page</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ ANNOUNCEMENTS ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='announce'?'active':'' ?>" id="tab-announce">
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-bullhorn"></i> New Announcement</h3></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_announcement">
          <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="4" required></textarea></div>
          <div class="form-group"><label class="form-label">Send To</label>
            <select name="target_role" class="form-control">
              <option value="all">Everyone</option><option value="teacher">Teachers Only</option><option value="parent">Parents Only</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Priority</label>
            <select name="priority" class="form-control">
              <option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
            </select>
          </div>
          <button class="btn btn-primary w-full"><i class="fa fa-paper-plane"></i> Publish</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fa fa-list"></i> Active Announcements</h3></div>
      <div style="max-height:480px;overflow-y:auto">
      <?php $pColors=['normal'=>'info','high'=>'warning','urgent'=>'danger'];
      foreach($announcements as $an): ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <div class="flex items-center gap-2 mb-2">
          <span class="badge badge-<?= $pColors[$an['priority']] ?>"><?= ucfirst($an['priority']) ?></span>
          <span class="badge badge-gray"><?= $an['target_role'] === 'all' ? 'Everyone' : ucfirst($an['target_role']) ?></span>
          <span class="text-sm text-muted" style="margin-left:auto"><?= date('M d',strtotime($an['created_at'])) ?></span>
        </div>
        <div style="font-weight:700;margin-bottom:4px"><?= sanitize($an['title']) ?></div>
        <div class="text-sm text-muted"><?= sanitize(substr($an['message'],0,120)) ?>...</div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ SETTINGS ═══════════════ -->
<div class="tab-pane <?= $activeMenu==='settings'?'active':'' ?>" id="tab-settings">
  <div class="grid-2">
    <div class="card">
      <div class="card-header"><h3><i class="fa fa-school"></i> School Settings</h3></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="save_settings">
          <?php
          $sFields=[
            ['school_name','School Name','text'],
            ['school_address','Address','text'],
            ['school_phone','Phone','text'],
            ['current_term','Current Term','text'],
            ['current_year','Academic Year','text'],
            ['pass_percentage','Pass Percentage (%)','number'],
          ];
          foreach($sFields as [$k,$l,$tp]): ?>
          <div class="form-group">
            <label class="form-label"><?= $l ?></label>
            <input type="<?= $tp ?>" name="<?= $k ?>" class="form-control" value="<?= sanitize($settings[$k]??'') ?>">
          </div>
          <?php endforeach; ?>
          <button class="btn btn-primary w-full"><i class="fa fa-save"></i> Save Settings</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fa fa-book"></i> Subjects</h3></div>
      <div class="card-body">
        <form method="POST" class="flex gap-2 mb-4 flex-wrap">
          <input type="hidden" name="action" value="add_subject">
          <input type="text" name="name" placeholder="Subject Name" class="form-control" style="flex:1;min-width:120px" required>
          <input type="text" name="code" placeholder="Code" class="form-control" style="width:80px">
          <input type="color" name="color" class="form-control" value="#6366f1" style="width:50px;padding:2px">
          <button class="btn btn-primary"><i class="fa fa-plus"></i></button>
        </form>
        <?php foreach($subjects as $sub): ?>
        <div class="flex items-center gap-2" style="padding:8px 0;border-bottom:1px solid var(--border)">
          <div style="width:12px;height:12px;border-radius:50%;background:<?= $sub['color'] ?>"></div>
          <span style="flex:1"><?= sanitize($sub['name']) ?></span>
          <span class="badge badge-gray"><?= sanitize($sub['code']??'') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════ MODALS ═══════════════ -->
<!-- Add Student Modal -->
<div class="modal-overlay" id="modal-add-student">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-user-plus"></i> Add New Student</h3>
      <button class="modal-close" onclick="closeModal('modal-add-student')">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="form-add-student">
        <input type="hidden" name="action" value="add_student">
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Gender</label>
            <select name="gender" class="form-control"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
          </div>
          <div class="form-group"><label class="form-label">Grade *</label>
            <select name="grade" class="form-control" required>
              <?php for($g=1;$g<=12;$g++): ?><option value="<?= $g ?>">Grade <?= $g ?></option><?php endfor; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Section *</label>
            <select name="section" class="form-control" required>
              <?php foreach(['A','B','C','D','E','F'] as $s): ?><option><?= $s ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control"></div>
          <div class="form-group"><label class="form-label">Nationality</label><input type="text" name="nationality" class="form-control" placeholder="e.g. Ethiopian"></div>
          <div class="form-group"><label class="form-label">Parent Name</label><input type="text" name="parent_name" class="form-control"></div>
          <div class="form-group"><label class="form-label">Parent Phone</label><input type="tel" name="parent_phone" class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">Parent Email</label><input type="email" name="parent_email" class="form-control"></div>
        <div class="modal-footer" style="margin:-24px -24px -24px;padding:16px 24px;border-top:1px solid var(--border)">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-student')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Student</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="modal-edit-student">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-edit"></i> Edit Student</h3>
      <button class="modal-close" onclick="closeModal('modal-edit-student')">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="form-edit-student">
        <input type="hidden" name="action" value="edit_student">
        <input type="hidden" name="id" id="edit-student-id">
        <div class="grid-2">
          <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="name" id="edit-student-name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Gender</label>
            <select name="gender" id="edit-student-gender" class="form-control">
              <option value="Male">Male</option><option value="Female">Female</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Grade</label>
            <select name="grade" id="edit-student-grade" class="form-control">
              <?php for($g=1;$g<=12;$g++): ?><option value="<?= $g ?>">Grade <?= $g ?></option><?php endfor; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Section</label>
            <select name="section" id="edit-student-section" class="form-control">
              <?php foreach(['A','B','C','D','E','F'] as $s): ?><option><?= $s ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="dob" id="edit-student-dob" class="form-control"></div>
          <div class="form-group"><label class="form-label">Nationality</label><input type="text" name="nationality" id="edit-student-nationality" class="form-control"></div>
          <div class="form-group"><label class="form-label">Parent Name</label><input type="text" name="parent_name" id="edit-student-pname" class="form-control"></div>
          <div class="form-group"><label class="form-label">Parent Phone</label><input type="tel" name="parent_phone" id="edit-student-pphone" class="form-control"></div>
        </div>
        <div class="form-group"><label class="form-label">Parent Email</label><input type="email" name="parent_email" id="edit-student-pemail" class="form-control"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-student')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal-overlay" id="modal-add-teacher">
  <div class="modal">
    <div class="modal-header">
      <h3><i class="fa fa-user-plus"></i> Add Teacher</h3>
      <button class="modal-close" onclick="closeModal('modal-add-teacher')">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="add_teacher">
        <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="Default: teacher123"></div>
        <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
        <div class="form-group"><label class="form-label">Subject</label>
          <select name="subject_id" class="form-control">
            <option value="">— Select —</option>
            <?php foreach($subjects as $sub): ?><option value="<?= $sub['id'] ?>"><?= sanitize($sub['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Qualification</label><input type="text" name="qualification" class="form-control" placeholder="e.g. BSc Mathematics"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-teacher')">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Teacher</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.vis-option.vis-active{border-color:var(--primary);background:#ede9fe;}
.vis-option:hover{border-color:var(--primary);}
</style>

<script>
// Edit student populate
function editStudent(s){
  document.getElementById('edit-student-id').value=s.id;
  document.getElementById('edit-student-name').value=s.name;
  document.getElementById('edit-student-gender').value=s.gender||'Male';
  document.getElementById('edit-student-grade').value=s.grade;
  document.getElementById('edit-student-section').value=s.class_section;
  document.getElementById('edit-student-dob').value=s.date_of_birth||'';
  document.getElementById('edit-student-nationality').value=s.nationality||'';
  document.getElementById('edit-student-pname').value=s.parent_name||'';
  document.getElementById('edit-student-pphone').value=s.parent_phone||'';
  document.getElementById('edit-student-pemail').value=s.parent_email||'';
  openModal('modal-edit-student');
}

// Filter table
function filterTable(inp,tableId){
  const q=inp.value.toLowerCase();
  document.querySelectorAll('#'+tableId+' tbody tr').forEach(row=>{
    row.style.display=row.textContent.toLowerCase().includes(q)?'':'none';
  });
}

// Grade filter
function filterByGrade(){
  const g=document.getElementById('stu-grade').value;
  document.querySelectorAll('#stu-table tbody tr').forEach(row=>{
    row.style.display=(!g||row.dataset.grade===g)?'':'none';
  });
}

// Load class students for attendance
function loadClassStudents(){
  const sel=document.getElementById('att-class');
  const opt=sel.options[sel.selectedIndex];
  if(!opt.value) return;
  const grade=opt.dataset.grade, section=opt.dataset.section;
  fetch(`api.php?action=get_students_for_attendance&grade=${grade}&section=${section}&date=<?= $today ?>`)
    .then(r=>r.json()).then(data=>{
      if(!data.students||!data.students.length){
        document.getElementById('att-students-list').innerHTML='<div class="text-muted">No students found.</div>';
        return;
      }
      let html='<div class="table-wrap"><table><thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th></tr></thead><tbody>';
      data.students.forEach(s=>{
        const cur=s.today_status||'Present';
        ['Present','Absent','Late','Excused'].forEach(st=>{});
        html+=`<tr>
          <td><strong>${s.name}</strong><br><span class="text-sm text-muted">${s.roll_number}</span>
          <input type="hidden" name="student_id[]" value="${s.id}"></td>
          ${['Present','Absent','Late','Excused'].map(st=>`
          <td style="text-align:center">
            <input type="radio" name="status_${s.id}" value="${st}" ${cur===st?'checked':''} style="width:18px;height:18px;accent-color:var(--primary)">
          </td>`).join('')}
        </tr>`;
      });
      html+='</tbody></table></div>';
      document.getElementById('att-students-list').innerHTML=html;
      document.getElementById('att-submit').classList.remove('hidden');
    });
}

// Report generator
function generateReport(){
  const cid=document.getElementById('rpt-class').value;
  const term=document.getElementById('rpt-term').value;
  window.open(`report_class.php?class_id=${cid}&term=${encodeURIComponent(term)}&year=<?= urlencode($currentYear) ?>`, '_blank');
}

// Visibility highlight
document.querySelectorAll('.vis-option').forEach(el=>{
  el.addEventListener('change',function(){
    document.querySelectorAll('.vis-option').forEach(o=>o.classList.remove('vis-active'));
    this.classList.add('vis-active');
  });
  el.querySelector('input')?.addEventListener('change',function(){
    document.querySelectorAll('.vis-option').forEach(o=>o.classList.remove('vis-active'));
    el.classList.add('vis-active');
  });
});
</script>

<?php include 'layout_end.php'; ?>
