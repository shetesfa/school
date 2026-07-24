<?php
session_start();
require_once 'config.php';

requireLogin();

// Only teachers with homeroom class can access
if ($_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit;
}

$teacher_user_id = $_SESSION['user_id'];
$teacher_id = $_SESSION['teacher_id'] ?? 0;

// Get teacher's homeroom class
$stmt = $pdo->prepare("
    SELECT c.*, CONCAT(c.grade, c.section) as class_name,
           COUNT(s.id) as student_count
    FROM classes c
    LEFT JOIN students s ON s.grade = c.grade AND s.class_section = c.section
    WHERE c.teacher_id = ?
    GROUP BY c.id
");
$stmt->execute([$teacher_id]);
$homeroom_class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$homeroom_class) {
    // No homeroom class assigned
    $error = "You don't have a homeroom class assigned yet.";
}

$term = $_GET['term'] ?? 'Term 1';
$academic_year = $_GET['year'] ?? date('Y') . '/' . (date('Y')+1);

// Get all students in homeroom class with their marks across all subjects
if ($homeroom_class) {
    $stmt = $pdo->prepare("
        SELECT 
            s.id, s.name, s.roll_number,
            (SELECT COUNT(DISTINCT subject_id) FROM student_marks_custom WHERE student_id = s.id AND term = ?) as subjects_count,
            (SELECT COUNT(DISTINCT subject_id) FROM student_marks_custom WHERE student_id = s.id AND term = ? AND is_locked = 1) as locked_count,
            AVG(smc.percentage) as overall_percentage,
            MAX(CASE WHEN smc.is_locked = 0 THEN 1 ELSE 0 END) as has_unlocked
        FROM students s
        LEFT JOIN student_marks_custom smc ON s.id = smc.student_id AND smc.term = ?
        WHERE s.grade = ? AND s.class_section = ?
        GROUP BY s.id
        ORDER BY s.roll_number
    ");
    $stmt->execute([$term, $term, $term, $homeroom_class['grade'], $homeroom_class['section']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if all subjects are locked for the class
    $all_locked = true;
    foreach ($students as $student) {
        if ($student['has_unlocked'] == 1) {
            $all_locked = false;
            break;
        }
    }
    
    // Get ranks if already calculated
    if ($all_locked) {
        $stmt = $pdo->prepare("
            SELECT cr.*, s.name, s.roll_number
            FROM class_rankings cr
            JOIN students s ON cr.student_id = s.id
            WHERE cr.class_id = ? AND cr.term = ? AND cr.academic_year = ?
            ORDER BY cr.rank_position
        ");
        $stmt->execute([$homeroom_class['id'], $term, $academic_year]);
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ranks_by_student = [];
        foreach ($ranks as $r) {
            $ranks_by_student[$r['student_id']] = $r;
        }
    }
    
    // Get all subjects for this class
    $stmt = $pdo->prepare("
        SELECT DISTINCT sub.id, sub.name, sub.code,
               t.user_id as teacher_user_id,
               u.name as teacher_name
        FROM student_marks_custom smc
        JOIN subjects sub ON smc.subject_id = sub.id
        JOIN teachers t ON smc.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN students s ON smc.student_id = s.id
        WHERE s.grade = ? AND s.class_section = ? AND smc.term = ?
        GROUP BY sub.id
        ORDER BY sub.name
    ");
    $stmt->execute([$homeroom_class['grade'], $homeroom_class['section'], $term]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get marks for each student per subject
    $marks_matrix = [];
    foreach ($students as $student) {
        $stmt = $pdo->prepare("
            SELECT subject_id, percentage, grade, is_locked
            FROM student_marks_custom
            WHERE student_id = ? AND term = ?
        ");
        $stmt->execute([$student['id'], $term]);
        $student_marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($student_marks as $sm) {
            $marks_matrix[$student['id']][$sm['subject_id']] = $sm;
        }
    }
}

// Handle AJAX request to calculate ranks
if (isset($_POST['calculate_ranks'])) {
    require_once 'semester_lock.php';
    // This will be handled by semester_lock.php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homeroom Dashboard - <?php echo $homeroom_class['class_name'] ?? ''; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f8fafc; }
        
        .header {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .class-badge {
            background: #ffd700;
            color: #1e3c72;
            padding: 6px 15px;
            border-radius: 30px;
            font-weight: 600;
            margin-left: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border-left: 4px solid #1e3c72;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e3c72;
        }
        
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        th {
            background: #f1f5f9;
            padding: 15px;
            text-align: center;
            font-weight: 600;
            color: #1e3c72;
            border-bottom: 2px solid #cbd5e1;
            position: sticky;
            top: 0;
        }
        
        td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .student-name {
            text-align: left;
            font-weight: 600;
        }
        
        .locked-badge {
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .unlocked-badge {
            background: #f59e0b;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .rank-1 { background: #ffd700; color: #000; font-weight: 700; }
        .rank-2 { background: #c0c0c0; color: #000; font-weight: 700; }
        .rank-3 { background: #cd7f32; color: #fff; font-weight: 700; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1e3c72;
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #2a5298;
            transform: translateY(-2px);
        }
        
        .btn-primary:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .grade-A { color: #10b981; font-weight: 700; }
        .grade-B { color: #3b82f6; font-weight: 700; }
        .grade-C { color: #f59e0b; font-weight: 700; }
        .grade-D { color: #ef4444; font-weight: 700; }
        .grade-F { color: #7f1d1d; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-chalkboard"></i> 
            Homeroom Dashboard - Class <?php echo $homeroom_class['class_name'] ?? ''; ?>
            <span class="class-badge">Your Class</span>
        </h1>
        <div>
            <span style="margin-right: 20px;"><i class="fas fa-user"></i> <?php echo $_SESSION['name']; ?></span>
            <a href="teacher_dashboard.php" style="color: white; margin-right: 15px;">Subject Dashboard</a>
            <a href="logout.php" style="color: white;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($error)): ?>
            <div style="background: #ffebee; color: #c62828; padding: 20px; border-radius: 12px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                <h3><?php echo $error; ?></h3>
            </div>
        <?php else: ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($students); ?></div>
                <div>Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($subjects); ?></div>
                <div>Subjects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: <?php echo $all_locked ? '#10b981' : '#f59e0b'; ?>">
                    <?php echo $all_locked ? 'Locked' : 'Pending'; ?>
                </div>
                <div>Semester Status</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo isset($ranks) ? count($ranks) : 0; ?></div>
                <div>Ranked Students</div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div>
                <label style="margin-right: 10px;">Term:</label>
                <select id="termSelect" style="padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1;">
                    <option value="Term 1" <?php echo $term == 'Term 1' ? 'selected' : ''; ?>>Term 1</option>
                    <option value="Term 2" <?php echo $term == 'Term 2' ? 'selected' : ''; ?>>Term 2</option>
                    <option value="Term 3" <?php echo $term == 'Term 3' ? 'selected' : ''; ?>>Term 3</option>
                    <option value="Final" <?php echo $term == 'Final' ? 'selected' : ''; ?>>Final</option>
                </select>
            </div>
            
            <div style="margin-left: auto;">
                <?php if ($all_locked && !isset($ranks)): ?>
                    <button class="btn btn-success" onclick="calculateRanks()">
                        <i class="fas fa-chart-line"></i> Calculate Class Ranks
                    </button>
                <?php elseif (isset($ranks)): ?>
                    <button class="btn btn-primary" onclick="window.location.href='homeroom_dashboard.php?term=<?php echo $term; ?>&year=<?php echo $academic_year; ?>&export=1'">
                        <i class="fas fa-file-pdf"></i> Export Report
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <?php foreach ($subjects as $subject): ?>
                            <th>
                                <?php echo $subject['name']; ?><br>
                                <small style="font-weight: normal;"><?php echo $subject['teacher_name']; ?></small>
                            </th>
                        <?php endforeach; ?>
                        <th>Total %</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $display_students = isset($ranks) ? $ranks : $students;
                    foreach ($display_students as $index => $item): 
                        $student_id = $item['student_id'] ?? $item['id'];
                        $student_name = $item['name'];
                        $roll_number = $item['roll_number'];
                        $total_percentage = $item['total_percentage'] ?? $item['overall_percentage'] ?? 0;
                        $rank_position = $item['rank_position'] ?? ($index + 1);
                        $rank_class = $rank_position == 1 ? 'rank-1' : ($rank_position == 2 ? 'rank-2' : ($rank_position == 3 ? 'rank-3' : ''));
                    ?>
                    <tr>
                        <td>
                            <span class="<?php echo $rank_class; ?>" style="display: inline-block; width: 30px; height: 30px; line-height: 30px; border-radius: 50%;">
                                <?php echo $rank_position; ?>
                            </span>
                        </td>
                        <td><strong><?php echo $roll_number; ?></strong></td>
                        <td class="student-name"><?php echo $student_name; ?></td>
                        
                        <?php foreach ($subjects as $subject): 
                            $mark = $marks_matrix[$student_id][$subject['id']] ?? null;
                            $percentage = $mark ? $mark['percentage'] : 0;
                            $grade = $mark ? $mark['grade'] : 'N/A';
                            $grade_class = '';
                            if ($grade == 'A+' || $grade == 'A') $grade_class = 'grade-A';
                            elseif ($grade == 'B+' || $grade == 'B') $grade_class = 'grade-B';
                            elseif ($grade == 'C') $grade_class = 'grade-C';
                            elseif ($grade == 'D') $grade_class = 'grade-D';
                            elseif ($grade == 'F') $grade_class = 'grade-F';
                        ?>
                            <td>
                                <?php if ($mark): ?>
                                    <span class="<?php echo $grade_class; ?>">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </span>
                                    <br>
                                    <small style="font-size: 0.7rem;">(<?php echo $grade; ?>)</small>
                                    <?php if ($mark['is_locked']): ?>
                                        <br><span class="locked-badge"><i class="fas fa-lock"></i></span>
                                    <?php else: ?>
                                        <br><span class="unlocked-badge"><i class="fas fa-lock-open"></i></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        
                        <td>
                            <strong style="font-size: 1.1rem; color: <?php echo $total_percentage >= 80 ? '#10b981' : ($total_percentage >= 60 ? '#f59e0b' : '#ef4444'); ?>">
                                <?php echo number_format($total_percentage, 1); ?>%
                            </strong>
                        </td>
                        <td>
                            <?php if (isset($item['has_unlocked']) && $item['has_unlocked']): ?>
                                <span class="unlocked-badge"><i class="fas fa-hourglass-half"></i> In Progress</span>
                            <?php elseif ($all_locked): ?>
                                <span class="locked-badge"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Subject Teachers List -->
        <div style="margin-top: 30px; background: white; border-radius: 16px; padding: 20px;">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-chalkboard-teacher"></i> Subject Teachers</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <?php foreach ($subjects as $subject): ?>
                    <div style="background: #f8fafc; padding: 15px; border-radius: 12px;">
                        <strong><?php echo $subject['name']; ?></strong>
                        <div style="color: #64748b; margin-top: 5px;">
                            <i class="fas fa-user"></i> <?php echo $subject['teacher_name']; ?>
                        </div>
                        <?php 
                        // Check if subject is locked
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM student_marks_custom smc
                            JOIN students s ON smc.student_id = s.id
                            WHERE s.grade = ? AND s.class_section = ? 
                            AND smc.subject_id = ? AND smc.term = ? AND smc.is_locked = 0
                        ");
                        $stmt->execute([$homeroom_class['grade'], $homeroom_class['section'], $subject['id'], $term]);
                        $unlocked = $stmt->fetchColumn();
                        ?>
                        <div style="margin-top: 10px;">
                            <?php if ($unlocked == 0): ?>
                                <span style="color: #10b981;"><i class="fas fa-lock"></i> Locked</span>
                            <?php else: ?>
                                <span style="color: #f59e0b;"><i class="fas fa-lock-open"></i> In Progress</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
        function calculateRanks() {
            if (!confirm('Calculate final class ranks? This action cannot be undone.')) {
                return;
            }
            
            fetch('semester_lock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'calculate_ranks=1&class_id=<?php echo $homeroom_class['id']; ?>&term=<?php echo $term; ?>&academic_year=<?php echo $academic_year; ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ ' + data.error);
                }
            });
        }
        
        document.getElementById('termSelect').addEventListener('change', function() {
            window.location.href = 'homeroom_dashboard.php?term=' + this.value + '&year=<?php echo $academic_year; ?>';
        });
    </script>
</body>
</html>