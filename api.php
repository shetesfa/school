<?php
require_once 'config.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$myId   = $_SESSION['user_id'];
$myRole = $_SESSION['role'];

switch ($action) {

    // ── Toggle dark mode ─────────────────────────────
    case 'toggle_dark':
        $mode = (int)($_GET['mode'] ?? 0);
        $_SESSION['dark'] = $mode;
        $pdo->prepare("UPDATE users SET dark_mode=? WHERE id=?")->execute([$mode, $myId]);
        jsonResponse(['success' => true]);

    // ── Get students for attendance (admin) ──────────
    case 'get_students_for_attendance':
        $grade   = sanitize($_GET['grade'] ?? '');
        $section = sanitize($_GET['section'] ?? '');
        $date    = $_GET['date'] ?? date('Y-m-d');

        $sq = $pdo->prepare("SELECT * FROM students WHERE grade=? AND class_section=? AND status='active' ORDER BY name");
        $sq->execute([$grade, $section]);
        $stus = $sq->fetchAll();

        foreach ($stus as &$s) {
            $a = $pdo->prepare("SELECT status FROM attendance WHERE student_id=? AND date=?");
            $a->execute([$s['id'], $date]);
            $s['today_status'] = $a->fetchColumn() ?: 'Present';
        }
        jsonResponse(['students' => $stus]);

    // ── Mark attendance (admin bulk) ─────────────────
    case 'mark_attendance_admin':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error'=>'Method'], 405);
        $date    = $_POST['date'] ?? date('Y-m-d');
        $stuIds  = $_POST['student_id'] ?? [];
        if (!is_array($stuIds)) $stuIds = [$stuIds];
        $saved = 0;
        foreach ($stuIds as $sid) {
            $sid = (int)$sid;
            $status = sanitize($_POST["status_{$sid}"] ?? 'Present');
            if (!in_array($status, ['Present','Absent','Late','Excused'])) $status = 'Present';
            $pdo->prepare("INSERT INTO attendance (student_id,date,status,marked_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),marked_by=VALUES(marked_by)")
                ->execute([$sid, $date, $status, $myId]);
            $saved++;
        }
        jsonResponse(['success' => true, 'saved' => $saved]);

    // ── Save mark via AJAX ───────────────────────────
    case 'save_mark_ajax':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error'=>'Method'], 405);
        if ($myRole !== 'teacher' && $myRole !== 'superadmin') jsonResponse(['error'=>'Forbidden'], 403);

        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $class_id   = (int)$_POST['class_id'];
        $term       = sanitize($_POST['term'] ?? 'Term 1');
        $year       = sanitize($_POST['year'] ?? '2025/2026');
        $total      = (float)$_POST['total_mark'];
        $max        = (float)$_POST['max_mark'];
        $pct        = (float)$_POST['percentage'];
        $cdata      = $_POST['criteria_data'] ?? '{}';
        $cmax       = $_POST['criteria_max'] ?? '{}';

        // Decode and re-encode to sanitize
        $cdataArr = json_decode($cdata, true) ?: [];
        $cmaxArr  = json_decode($cmax,  true) ?: [];

        // Check lock
        $lk = $pdo->prepare("SELECT id FROM semester_locks WHERE class_id=? AND term=? AND academic_year=? AND is_locked=1");
        $lk->execute([$class_id, $term, $year]);
        if ($lk->fetch()) jsonResponse(['error' => 'Marks locked'], 403);

        // Determine teacher_id
        $tid = $_SESSION['teacher_id'] ?? 0;
        if (!$tid && $myRole === 'superadmin') {
            $tq = $pdo->prepare("SELECT id FROM teachers WHERE user_id=?");
            $tq->execute([$myId]);
            $tid = (int)$tq->fetchColumn();
        }

        [$gl] = getGradeLetter($pct);

        $st = $pdo->prepare("
            INSERT INTO student_marks_custom
              (student_id,subject_id,teacher_id,class_id,term,academic_year,criteria_data,criteria_max,total_mark,max_mark,percentage,grade,entered_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              criteria_data=VALUES(criteria_data), criteria_max=VALUES(criteria_max),
              total_mark=VALUES(total_mark), max_mark=VALUES(max_mark),
              percentage=VALUES(percentage), grade=VALUES(grade),
              entered_by=VALUES(entered_by), updated_at=NOW()
        ");
        if ($st->execute([$student_id,$subject_id,$tid,$class_id,$term,$year,
                          json_encode($cdataArr),json_encode($cmaxArr),$total,$max,$pct,$gl,$myId])) {
            // Recalculate rankings
            recalcRankings($pdo, $class_id, $term, $year);
            jsonResponse(['success' => true]);
        }
        jsonResponse(['error' => 'Failed to save'], 500);

    // ── Get notifications ────────────────────────────
    case 'get_notifications':
        $nq = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
        $nq->execute([$myId]);
        $ns = $nq->fetchAll();
        $unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $unread->execute([$myId]);
        jsonResponse(['notifications' => $ns, 'unread' => (int)$unread->fetchColumn()]);

    // ── Mark notifications read ──────────────────────
    case 'mark_notifications_read':
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$myId]);
        jsonResponse(['success' => true]);

    // ── Get student data ─────────────────────────────
    case 'get_student':
        $sid = (int)($_GET['id'] ?? 0);
        $sq = $pdo->prepare("SELECT * FROM students WHERE id=?");
        $sq->execute([$sid]);
        $s = $sq->fetch();
        if (!$s) jsonResponse(['error' => 'Not found'], 404);
        jsonResponse(['student' => $s]);

    // ── Search students ──────────────────────────────
    case 'search_students':
        $q = '%' . sanitize($_GET['q'] ?? '') . '%';
        $sq = $pdo->prepare("SELECT id, name, roll_number, grade, class_section FROM students WHERE name LIKE ? OR roll_number LIKE ? LIMIT 20");
        $sq->execute([$q, $q]);
        jsonResponse(['results' => $sq->fetchAll()]);

    // ── Get calendar events ──────────────────────────
    case 'get_events':
        $month = $_GET['month'] ?? date('Y-m');
        $eq = $pdo->prepare("
            SELECT * FROM calendar_events
            WHERE DATE_FORMAT(event_date,'%Y-%m')=? OR DATE_FORMAT(end_date,'%Y-%m')=?
            ORDER BY event_date
        ");
        $eq->execute([$month, $month]);
        jsonResponse(['events' => $eq->fetchAll()]);

    // ── Unread message count ─────────────────────────
    case 'unread_count':
        $uc = $pdo->query("
            SELECT COUNT(*) FROM chat_messages cm
            JOIN chat_conversations cc ON cc.id=cm.conversation_id
            WHERE cm.is_read=0 AND cm.sender_id!={$myId}
              AND (cc.user1_id={$myId} OR cc.user2_id={$myId})
        ")->fetchColumn();
        jsonResponse(['count' => (int)$uc]);

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}

// ── Helper: Recalculate Rankings ─────────────────────
function recalcRankings(PDO $pdo, int $class_id, string $term, string $year): void {
    // Get class grade/section
    $cl = $pdo->prepare("SELECT grade, section FROM classes WHERE id=?");
    $cl->execute([$class_id]);
    $clRow = $cl->fetch();
    if (!$clRow) return;

    $sq = $pdo->prepare("
        SELECT s.id AS student_id, COALESCE(AVG(smc.percentage),0) AS avg_pct
        FROM students s
        LEFT JOIN student_marks_custom smc ON smc.student_id=s.id AND smc.term=? AND smc.academic_year=?
        WHERE s.grade=? AND s.class_section=? AND s.status='active'
        GROUP BY s.id
        ORDER BY avg_pct DESC
    ");
    $sq->execute([$term, $year, $clRow['grade'], $clRow['section']]);
    $rows = $sq->fetchAll();

    foreach ($rows as $i => $row) {
        $pdo->prepare("
            INSERT INTO class_rankings (class_id,student_id,term,academic_year,total_percentage,rank_position)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE total_percentage=VALUES(total_percentage), rank_position=VALUES(rank_position), calculated_at=NOW()
        ")->execute([$class_id, $row['student_id'], $term, $year, $row['avg_pct'], $i + 1]);
    }
}
?>
