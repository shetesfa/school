<?php
session_start();
require_once 'config.php';

// Only superadmin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

$current_user_name = $_SESSION['name'] ?? 'Super Admin';

// ============================================
// FETCH DATA FOR DASHBOARD
// ============================================

// Basic stats only
$total_students = $pdo->query("SELECT COUNT(*) as count FROM students")->fetch()['count'] ?? 0;
$total_teachers = $pdo->query("SELECT COUNT(*) as count FROM teachers")->fetch()['count'] ?? 0;
$total_classes = $pdo->query("SELECT COUNT(*) as count FROM classes")->fetch()['count'] ?? 0;
$total_parents = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'parent'")->fetch()['count'] ?? 0;

// 1. CLASSES - Only show class name, homeroom teacher, total students
$classes = $pdo->query("
    SELECT 
        c.*,
        CONCAT(c.grade, c.section) AS class_name,
        u.name AS homeroom_teacher_name,
        COUNT(DISTINCT s.id) AS student_count
    FROM classes c
    LEFT JOIN teachers t ON t.id = c.teacher_id
    LEFT JOIN users u ON u.id = t.user_id
    LEFT JOIN students s ON s.grade = c.grade AND s.class_section = c.section
    GROUP BY c.id
    ORDER BY c.grade, c.section
")->fetchAll();

// 2. TEACHERS - Show phone instead of email, and classes assigned count
$teachers = $pdo->query("
    SELECT 
        t.*,
        u.name,
        t.phone,  -- Phone instead of email
        sub.name AS subject_name,
        COUNT(DISTINCT c.id) AS classes_assigned  -- How many classes they teach
    FROM teachers t
    JOIN users u ON u.id = t.user_id
    LEFT JOIN subjects sub ON sub.id = t.subject_id
    LEFT JOIN class_subject_teachers cst ON cst.teacher_id = t.id
    LEFT JOIN classes c ON c.id = cst.class_id
    WHERE u.role = 'teacher'
    GROUP BY t.id
    ORDER BY u.name
")->fetchAll();

// 3. STUDENTS - With BS format roll numbers
$students = $pdo->query("
    SELECT 
        s.*,
        CONCAT(s.grade, s.class_section) AS class_name,
        u.name AS teacher_name
    FROM students s
    LEFT JOIN teachers t ON t.id = s.teacher_id
    LEFT JOIN users u ON u.id = t.user_id
    ORDER BY s.grade, s.class_section, s.roll_number
")->fetchAll();

// 4. SUBJECTS - Only name and teacher, no performance data
$subjects = $pdo->query("
    SELECT 
        sub.*,
        GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') AS teachers
    FROM subjects sub
    LEFT JOIN teachers t ON t.subject_id = sub.id
    LEFT JOIN users u ON u.id = t.user_id
    GROUP BY sub.id
    ORDER BY sub.name
")->fetchAll();

// 5. ACADEMIC RECORDS - First semester only, show ranks 1-3
$academic_year = date('Y') . '/' . (date('Y')+1);
$term = 'Term 1';

// Get class rankings (only top 3 per class)
$rankings = $pdo->prepare("
    SELECT 
        cr.*,
        s.name AS student_name,
        s.roll_number,
        CONCAT(c.grade, c.section) AS class_name,
        u.name AS homeroom_teacher
    FROM class_rankings cr
    JOIN students s ON cr.student_id = s.id
    JOIN classes c ON cr.class_id = c.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE cr.term = ? AND cr.academic_year = ? AND cr.rank_position <= 3
    ORDER BY c.grade, c.section, cr.rank_position
");
$rankings->execute([$term, $academic_year]);
$top_students = $rankings->fetchAll();

// 6. ATTENDANCE - Students absent 30+ days (shown in RED)
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
$absent_students = $pdo->prepare("
    SELECT 
        s.id,
        s.name,
        s.roll_number,
        CONCAT(s.grade, s.class_section) AS class_name,
        COUNT(*) AS absent_days,
        s.photo
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.date >= ? AND a.status = 'Absent'
    GROUP BY s.id
    HAVING COUNT(*) >= 30
    ORDER BY absent_days DESC
");
$absent_students->execute([$thirty_days_ago]);
$chronic_absent = $absent_students->fetchAll();

// Helper function for grade colors (kept for potential use)
function getGradeColor($percentage) {
    if ($percentage >= 80) return '#4CAF50';
    if ($percentage >= 60) return '#FF9800';
    return '#f44336';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Bori Secondary School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f0f2f5;
        }
        
        /* Header - Brand Color: #667eea */
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header h1 {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.8rem;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-badge a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }
        
        .user-badge a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1600px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        /* Stats Cards - Simple */
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-left: 5px solid #667eea;
        }
        
        .stat-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
            margin-top: 30px;
        }
        
        .tab {
            padding: 18px 28px;
            cursor: pointer;
            background: #f8f9fa;
            border-right: 1px solid #eee;
            font-weight: 600;
            color: #555;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .tab:hover {
            background: white;
            color: #667eea;
        }
        
        .tab.active {
            background: white;
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }
        
        .tab-content {
            background: white;
            padding: 30px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: #f8f9fa;
            color: #667eea;
            font-weight: 600;
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            color: #444;
        }
        
        tr:hover {
            background: #fafafa;
        }
        
        /* Student ID Badge */
        .student-id {
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        /* Red for chronic absent */
        .absent-danger {
            background: #ffebee;
            color: #c62828;
            font-weight: 700;
            border-left: 4px solid #f44336;
        }
        
        .absent-danger:hover {
            background: #ffcdd2;
        }
        
        /* Rank badges */
        .rank-1 {
            background: #ffd700;
            color: #333;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 700;
        }
        
        .rank-2 {
            background: #c0c0c0;
            color: #333;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 700;
        }
        
        .rank-3 {
            background: #cd7f32;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 700;
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background: #e8eaf6;
            color: #667eea;
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-header h2 {
            color: #667eea;
            font-size: 1.5rem;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .filter-bar input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .filter-bar input:focus {
            border-color: #667eea;
            outline: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .tab {
                padding: 12px 20px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-crown"></i>
            Super Admin Dashboard - Bori Secondary School
        </h1>
        <div class="user-badge">
            <i class="fas fa-user-shield"></i>
            <?php echo htmlspecialchars($current_user_name); ?>
            <a href="registration.php"><i class="fas fa-user-graduate"></i> Registration</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Simple Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($total_students); ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_teachers); ?></h3>
                <p>Total Teachers</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_classes); ?></h3>
                <p>Total Classes</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_parents); ?></h3>
                <p>Registered Parents</p>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('classes')">📚 Classes</div>
            <div class="tab" onclick="showTab('teachers')">👨‍🏫 Teachers</div>
            <div class="tab" onclick="showTab('students')">🎓 Students</div>
            <div class="tab" onclick="showTab('subjects')">📖 Subjects</div>
            <div class="tab" onclick="showTab('academic')">🏆 Academic Records</div>
            <div class="tab" onclick="showTab('attendance')">⚠️ Attendance Alert</div>
        </div>
        
        <!-- 1. CLASSES TAB - Only class, teacher, student count -->
        <div id="classes-tab" class="tab-content active">
            <div class="section-header">
                <h2><i class="fas fa-chalkboard"></i> Classes Overview</h2>
                <span class="badge badge-primary">Total: <?php echo count($classes); ?> classes</span>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Homeroom Teacher</th>
                            <th>Total Students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><strong>Class <?php echo $class['class_name']; ?></strong></td>
                            <td>
                                <?php if ($class['homeroom_teacher_name']): ?>
                                    <i class="fas fa-chalkboard-teacher" style="color: #667eea;"></i>
                                    <?php echo htmlspecialchars($class['homeroom_teacher_name']); ?>
                                <?php else: ?>
                                    <span style="color: #f44336;">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $class['student_count'] ?? 0; ?> students</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 2. TEACHERS TAB - Phone instead of email, classes count -->
        <div id="teachers-tab" class="tab-content">
            <div class="section-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> Teachers</h2>
                <span class="badge badge-primary">Total: <?php echo count($teachers); ?> teachers</span>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Teacher Name</th>
                            <th>Phone</th>
                            <th>Subject</th>
                            <th>Classes Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($teacher['name']); ?></strong></td>
                            <td>
                                <i class="fas fa-phone" style="color: #667eea;"></i>
                                <?php echo htmlspecialchars($teacher['phone'] ?? 'Not provided'); ?>
                            </td>
                            <td>
                                <span class="badge badge-primary">
                                    <?php echo htmlspecialchars($teacher['subject_name'] ?? 'Not assigned'); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo $teacher['classes_assigned'] ?? 0; ?></strong> classes
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 3. STUDENTS TAB - BS format IDs -->
        <div id="students-tab" class="tab-content">
            <div class="section-header">
                <h2><i class="fas fa-graduation-cap"></i> Students</h2>
                <span class="badge badge-primary">Total: <?php echo count($students); ?> students</span>
            </div>
            
            <div class="filter-bar">
                <input type="text" id="searchStudent" placeholder="🔍 Search by name or roll number...">
            </div>
            
            <div class="table-responsive">
                <table id="students-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Homeroom Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            // Ensure roll number is in BS format
                            $roll = $student['roll_number'];
                            if (!preg_match('/^BS\d+$/', $roll)) {
                                $roll = 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
                            }
                        ?>
                        <tr class="student-row">
                            <td>
                                <span class="student-id"><?php echo htmlspecialchars($roll); ?></span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                            <td>Class <?php echo $student['grade'] . $student['class_section']; ?></td>
                            <td>
                                <?php if ($student['teacher_name']): ?>
                                    <i class="fas fa-chalkboard-teacher" style="color: #667eea;"></i>
                                    <?php echo htmlspecialchars($student['teacher_name']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 4. SUBJECTS TAB - Only name and teachers -->
        <div id="subjects-tab" class="tab-content">
            <div class="section-header">
                <h2><i class="fas fa-book"></i> Subjects</h2>
                <span class="badge badge-primary">Total: <?php echo count($subjects); ?> subjects</span>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Teachers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($subject['name']); ?></strong></td>
                            <td>
                                <?php if ($subject['teachers']): ?>
                                    <?php echo htmlspecialchars($subject['teachers']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">No teachers assigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 5. ACADEMIC RECORDS - First semester, ranks 1-3 only -->
        <div id="academic-tab" class="tab-content">
            <div class="section-header">
                <h2><i class="fas fa-trophy"></i> Top Students - First Semester</h2>
                <span class="badge badge-primary">Showing ranks 1-3 only</span>
            </div>
            
            <?php if (empty($top_students)): ?>
                <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 12px;">
                    <i class="fas fa-info-circle" style="font-size: 3rem; color: #667eea; margin-bottom: 20px;"></i>
                    <h3>No rankings available yet</h3>
                    <p>First semester must be locked and finalized to generate rankings.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Homeroom Teacher</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_students as $rank): ?>
                            <tr>
                                <td>
                                    <span class="<?php 
                                        echo $rank['rank_position'] == 1 ? 'rank-1' : 
                                            ($rank['rank_position'] == 2 ? 'rank-2' : 'rank-3'); 
                                    ?>">
                                        #<?php echo $rank['rank_position']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="student-id">
                                        BS<?php echo str_pad($rank['student_id'], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($rank['student_name']); ?></strong></td>
                                <td>Class <?php echo $rank['class_name']; ?></td>
                                <td><?php echo htmlspecialchars($rank['homeroom_teacher'] ?? 'N/A'); ?></td>
                                <td>
                                    <strong style="color: #667eea;"><?php echo number_format($rank['total_percentage'], 1); ?>%</strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 6. ATTENDANCE TAB - Students absent 30+ days in RED -->
        <div id="attendance-tab" class="tab-content">
            <div class="section-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Chronic Absenteeism</h2>
                <span class="badge badge-primary">Students absent 30+ days</span>
            </div>
            
            <?php if (empty($chronic_absent)): ?>
                <div style="text-align: center; padding: 50px; background: #e8f5e9; border-radius: 12px;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #4CAF50; margin-bottom: 20px;"></i>
                    <h3>Good News!</h3>
                    <p>No students with 30+ days of absence.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Days Absent</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chronic_absent as $student): ?>
                            <tr class="absent-danger">
                                <td>
                                    <span class="student-id" style="background: #c62828;">
                                        BS<?php echo str_pad($student['id'], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                <td>Class <?php echo $student['class_name']; ?></td>
                                <td><strong><?php echo $student['absent_days']; ?> days</strong></td>
                                <td>
                                    <span style="background: #f44336; color: white; padding: 5px 10px; border-radius: 20px;">
                                        <i class="fas fa-exclamation"></i> CRITICAL
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Search students
        document.getElementById('searchStudent')?.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.student-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    </script>
</body>
</html>