<?php
session_start();
require_once 'config.php';

// Only superadmin and registration can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'registration'])) {
    header("Location: login.php");
    exit();
}

$current_user_name = $_SESSION['name'] ?? 'Registration Officer';
$is_superadmin = ($_SESSION['role'] === 'superadmin');

$message = '';
$error = '';

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

// 1. CREATE SUBJECT - Only name needed (no code)
if (isset($_POST['create_subject'])) {
    $name = trim($_POST['subject_name']);
    
    if ($name) {
        try {
            // Generate code automatically from name
            $words = explode(' ', $name);
            $code = '';
            foreach ($words as $w) {
                $code .= strtoupper(substr($w, 0, 1));
            }
            $code = substr($code, 0, 3);
            
            $stmt = $pdo->prepare("INSERT INTO subjects (name, code) VALUES (?, ?)");
            $stmt->execute([$name, $code]);
            $message = "✅ Subject '$name' created successfully!";
        } catch (PDOException $e) {
            $error = "❌ Failed: " . $e->getMessage();
        }
    }
}

// 2. CREATE CLASS - Shows only available sections
if (isset($_POST['create_class'])) {
    $grade = trim($_POST['grade']);
    $section = trim($_POST['section']);
    
    if ($grade && $section) {
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE grade = ? AND section = ?");
        $stmt->execute([$grade, $section]);
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO classes (grade, section, created_by) VALUES (?, ?, ?)");
            if ($stmt->execute([$grade, $section, $_SESSION['user_id']])) {
                $message = "✅ Class $grade$section created successfully!";
            }
        } else {
            $error = "❌ Class $grade$section already exists!";
        }
    }
}

// 3. ASSIGN HOMEROOM TEACHER - After assign, teacher removed from dropdown
if (isset($_POST['assign_homeroom'])) {
    $class_id = $_POST['class_id'];
    $teacher_id = $_POST['teacher_id'];
    
    if ($class_id && $teacher_id) {
        $pdo->beginTransaction();
        try {
            // Update classes table with homeroom teacher
            $stmt = $pdo->prepare("UPDATE classes SET teacher_id = ? WHERE id = ?");
            $stmt->execute([$teacher_id, $class_id]);
            
            // Get grade and section
            $stmt = $pdo->prepare("SELECT grade, section FROM classes WHERE id = ?");
            $stmt->execute([$class_id]);
            $class = $stmt->fetch();
            
            if ($class) {
                // Update all students in this class
                $stmt = $pdo->prepare("UPDATE students SET teacher_id = ? WHERE grade = ? AND class_section = ?");
                $stmt->execute([$teacher_id, $class['grade'], $class['section']]);
            }
            
            $pdo->commit();
            $message = "✅ Homeroom teacher assigned to Class " . $class['grade'] . $class['section'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ Failed: " . $e->getMessage();
        }
    }
}

// 4. ASSIGN SUBJECT TEACHER - Shows only teachers for selected subject
if (isset($_POST['assign_subject_teacher'])) {
    $class_id = $_POST['class_id'];
    $teacher_id = $_POST['teacher_id'];
    $subject_id = $_POST['subject_id'];
    
    if ($class_id && $teacher_id && $subject_id) {
        // Check if this teacher already teaches this subject in this class
        $stmt = $pdo->prepare("
            SELECT id FROM class_subject_teachers 
            WHERE class_id = ? AND subject_id = ?
        ");
        $stmt->execute([$class_id, $subject_id]);
        
        if ($stmt->rowCount() > 0) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE class_subject_teachers 
                SET teacher_id = ?, assigned_by = ?, assigned_at = NOW() 
                WHERE class_id = ? AND subject_id = ?
            ");
            $stmt->execute([$teacher_id, $_SESSION['user_id'], $class_id, $subject_id]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("
                INSERT INTO class_subject_teachers (class_id, subject_id, teacher_id, assigned_by) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$class_id, $subject_id, $teacher_id, $_SESSION['user_id']]);
        }
        
        $message = "✅ Subject teacher assigned!";
    }
}

// 5. REGISTER STUDENT - Auto roll number (BS format), parent email optional
if (isset($_POST['register_student'])) {
    $name = trim($_POST['student_name']);
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $dob = $_POST['dob'] ?: null;
    $parent_name = trim($_POST['parent_name'] ?: '');
    $parent_phone = trim($_POST['parent_phone'] ?: '');
    $parent_email = trim($_POST['parent_email'] ?: ''); // Optional
    
    if ($name && $grade && $section) {
        // Generate auto roll number (BS format)
        $roll_number = generateStudentRollNumber($pdo);
        
        // Get homeroom teacher for this class
        $stmt = $pdo->prepare("SELECT teacher_id FROM classes WHERE grade = ? AND section = ?");
        $stmt->execute([$grade, $section]);
        $class_info = $stmt->fetch();
        $homeroom_teacher_id = $class_info['teacher_id'] ?? null;
        
        $pdo->beginTransaction();
        try {
            // Insert student
            $stmt = $pdo->prepare("
                INSERT INTO students (
                    name, roll_number, grade, class_section, date_of_birth, 
                    parent_name, parent_phone, parent_email, teacher_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $roll_number, $grade, $section, $dob, 
                $parent_name, $parent_phone, $parent_email, $homeroom_teacher_id
            ]);
            
            // Create parent account if email provided (optional)
            if ($parent_email) {
                $check = $pdo->prepare("SELECT id FROM users WHERE name = ?");
                $check->execute([$name]);
                if ($check->rowCount() == 0) {
                    $parent_password = password_hash('parent123', PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, password, role, status) 
                        VALUES (?, ?, ?, 'parent', 'active')
                    ");
                    $stmt->execute([$name, $parent_email ?: $roll_number, $parent_password]);
                }
            }
            
            $pdo->commit();
            $message = "✅ Student '$name' registered! Roll Number: <strong>$roll_number</strong>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ Failed: " . $e->getMessage();
        }
    }
}

// ============================================
// FETCH DATA
// ============================================

// Subjects - No codes shown
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

// All teachers with their subject
$teachers = $pdo->query("
    SELECT t.*, u.name, sub.name AS subject_name 
    FROM teachers t 
    JOIN users u ON u.id = t.user_id 
    LEFT JOIN subjects sub ON sub.id = t.subject_id 
    WHERE u.role = 'teacher'
    ORDER BY u.name
")->fetchAll();

// Get all classes
$classes = $pdo->query("
    SELECT c.*, CONCAT(c.grade, c.section) AS class_name, 
           u.name AS homeroom_teacher_name
    FROM classes c 
    LEFT JOIN teachers t ON t.id = c.teacher_id 
    LEFT JOIN users u ON u.id = t.user_id 
    ORDER BY c.grade, c.section
")->fetchAll();

// Get all available sections for each grade
$grades = range(1, 12);
$all_sections = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

// Get subject teachers for each class
$class_subject_teachers = [];
foreach ($classes as $class) {
    $stmt = $pdo->prepare("
        SELECT cst.*, sub.name as subject_name, u.name as teacher_name
        FROM class_subject_teachers cst
        JOIN subjects sub ON cst.subject_id = sub.id
        JOIN teachers t ON cst.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE cst.class_id = ?
    ");
    $stmt->execute([$class['id']]);
    $class_subject_teachers[$class['id']] = $stmt->fetchAll();
}

// Get unassigned teachers for homeroom (teachers not already assigned as homeroom)
$unassigned_teachers = $pdo->query("
    SELECT t.*, u.name 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.id NOT IN (SELECT teacher_id FROM classes WHERE teacher_id IS NOT NULL)
    ORDER BY u.name
")->fetchAll();

// Check if class_subject_teachers table exists
try {
    $pdo->query("SELECT 1 FROM class_subject_teachers LIMIT 1");
} catch (Exception $e) {
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS class_subject_teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            subject_id INT NOT NULL,
            teacher_id INT NOT NULL,
            assigned_by INT,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_class_subject (class_id, subject_id),
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration - Bori Secondary School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f5f7fa;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .container {
            max-width: 1600px;
            margin: 30px auto;
            padding: 0 25px;
        }
        
        .alert {
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #1e4620;
            border-left: 6px solid #4caf50;
        }
        
        .alert-error {
            background: #ffebee;
            color: #621b1b;
            border-left: 6px solid #f44336;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
            margin-top: 20px;
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
            padding: 35px;
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
        
        .section {
            background: #fafbfc;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 35px;
            border: 1px solid #e9ecef;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .section-header h3 {
            color: #667eea;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        label i {
            margin-right: 8px;
            color: #667eea;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
        }
        
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
            background: #f1f8e9;
            color: #667eea;
            font-weight: 600;
            padding: 16px;
            text-align: left;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #fafafa;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .badge-info {
            background: #e3f2fd;
            color: #667eea;
        }
        
        .badge-primary {
            background: #e8eaf6;
            color: #667eea;
        }
        
        .badge-assigned {
            background: #ffebee;
            color: #c62828;
        }
        
        /* Available sections styling */
        .section-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .section-item {
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
        }
        
        .section-created {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        
        .section-available {
            background: #e3f2fd;
            color: #667eea;
            border: 1px dashed #667eea;
            cursor: pointer;
        }
        
        .section-available:hover {
            background: #bbdefb;
        }
        
        .info-box {
            background: #e8f5e9;
            border-left: 6px solid #667eea;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .auto-roll {
            background: #f0f4ff;
            padding: 12px;
            border-radius: 8px;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .auto-roll i {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- ===== HEADER WITH EDIT LINK ADDED ===== -->
    <div class="header">
        <h1><i class="fas fa-user-graduate"></i> Registration Office - Bori Secondary School</h1>
        <div>
            <span style="margin-right:20px;"><i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user_name); ?></span>
            <?php if($is_superadmin): ?>
                <a href="superadmin.php" style="color:white; margin-right:15px;">Admin</a>
            <?php endif; ?>
            <!-- ===== EDIT LINK ADDED HERE ===== -->
            <a href="admin_edit.php" style="color:white; margin-right:15px;">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="logout.php" style="color:white;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <?php if($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- TABS -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('subjects')"><i class="fas fa-book"></i> 1. Subjects</div>
            <div class="tab" onclick="showTab('classes')"><i class="fas fa-chalkboard"></i> 2. Classes</div>
            <div class="tab" onclick="showTab('homeroom')"><i class="fas fa-house-user"></i> 3. Homeroom</div>
            <div class="tab" onclick="showTab('subject_teachers')"><i class="fas fa-users"></i> 4. Subject Teachers</div>
            <div class="tab" onclick="showTab('students')"><i class="fas fa-user-plus"></i> 5. Register Students</div>
            <div class="tab" onclick="showTab('view')"><i class="fas fa-eye"></i> 6. View Assignments</div>
        </div>
        
        <!-- TAB 1: SUBJECTS - Only name needed -->
        <div id="subjects-tab" class="tab-content active">
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-book-medical"></i> Create New Subject</h3>
                </div>
                <div style="max-width: 600px;">
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-book"></i> Subject Name</label>
                            <input type="text" name="subject_name" placeholder="e.g. Mathematics, English, Physics" required>
                            <small style="color: #666; margin-top: 5px; display: block;">Code will be auto-generated</small>
                        </div>
                        <button type="submit" name="create_subject" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Create Subject
                        </button>
                    </form>
                </div>
                
                <h4 style="margin-top: 30px;">Existing Subjects</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px;">
                    <?php foreach ($subjects as $subject): ?>
                        <span class="badge badge-info" style="padding: 10px 20px;">
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- TAB 2: CLASSES - Show created and available sections -->
        <div id="classes-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-plus-circle"></i> Create New Class</h3>
                </div>
                
                <div class="form-grid">
                    <?php foreach ($grades as $grade): 
                        // Get created sections for this grade
                        $created = [];
                        foreach ($classes as $c) {
                            if ($c['grade'] == $grade) {
                                $created[] = $c['section'];
                            }
                        }
                    ?>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                        <h4 style="color: #667eea; margin-bottom: 15px;">Grade <?php echo $grade; ?></h4>
                        
                        <div style="margin-bottom: 15px;">
                            <strong>Created Sections:</strong>
                            <div class="section-grid">
                                <?php 
                                $created_sections = array_filter($all_sections, function($s) use ($created) {
                                    return in_array($s, $created);
                                });
                                ?>
                                <?php if (!empty($created_sections)): ?>
                                    <?php foreach ($created_sections as $sec): ?>
                                        <span class="section-item section-created">
                                            Section <?php echo $sec; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: #999;">No sections created yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <strong>Available to Create:</strong>
                            <div class="section-grid">
                                <?php 
                                $available = array_filter($all_sections, function($s) use ($created) {
                                    return !in_array($s, $created);
                                });
                                
                                foreach ($available as $sec): 
                                ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="grade" value="<?php echo $grade; ?>">
                                    <input type="hidden" name="section" value="<?php echo $sec; ?>">
                                    <button type="submit" name="create_class" class="section-item section-available" style="border: none; cursor: pointer;">
                                        + Section <?php echo $sec; ?>
                                    </button>
                                </form>
                                <?php endforeach; ?>
                                
                                <?php if (empty($available)): ?>
                                    <span style="color: #4caf50;">All sections created!</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- TAB 3: ASSIGN HOMEROOM - After assign, teacher removed -->
        <div id="homeroom-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-house-user"></i> Assign Homeroom Teacher</h3>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> Once a teacher is assigned as homeroom, they will be removed from the dropdown.
                </div>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Class</label>
                            <select name="class_id" required>
                                <option value="">-- Choose Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <?php if (!$class['homeroom_teacher_name']): // Only show unassigned classes ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        Class <?php echo $class['class_name']; ?> 
                                        (No homeroom yet)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Homeroom Teacher</label>
                            <select name="teacher_id" required>
                                <option value="">-- Choose Teacher --</option>
                                <?php foreach ($unassigned_teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?> 
                                        (<?php echo $teacher['subject_name'] ?? 'No subject'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="assign_homeroom" class="btn btn-primary">
                        <i class="fas fa-house-user"></i> Assign Homeroom Teacher
                    </button>
                </form>
                
                <div style="margin-top: 30px;">
                    <h4>Already Assigned Homeroom Teachers:</h4>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Homeroom Teacher</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <?php if ($class['homeroom_teacher_name']): ?>
                                    <tr>
                                        <td><strong>Class <?php echo $class['class_name']; ?></strong></td>
                                        <td>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check"></i> 
                                                <?php echo htmlspecialchars($class['homeroom_teacher_name']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TAB 4: ASSIGN SUBJECT TEACHERS - Filter by subject -->
        <div id="subject_teachers-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-users"></i> Assign Subject Teachers</h3>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> When you select a subject, only teachers who teach that subject will appear.
                </div>
                
                <form method="POST" id="subjectTeacherForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Class</label>
                            <select name="class_id" id="class_select" required>
                                <option value="">-- Choose Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        Class <?php echo $class['class_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Subject</label>
                            <select name="subject_id" id="subject_select" required onchange="filterTeachersBySubject()">
                                <option value="">-- Choose Subject --</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Teacher</label>
                            <select name="teacher_id" id="teacher_select" required>
                                <option value="">-- Choose Teacher --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" data-subject="<?php echo $teacher['subject_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?> 
                                        (<?php echo $teacher['subject_name'] ?? 'No subject'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="assign_subject_teacher" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Assign Subject Teacher
                    </button>
                </form>
                
                <script>
                function filterTeachersBySubject() {
                    const subjectId = document.getElementById('subject_select').value;
                    const teacherSelect = document.getElementById('teacher_select');
                    const options = teacherSelect.options;
                    
                    for (let i = 0; i < options.length; i++) {
                        const option = options[i];
                        if (option.value === "") continue;
                        
                        const teacherSubject = option.getAttribute('data-subject');
                        if (teacherSubject === subjectId) {
                            option.style.display = '';
                        } else {
                            option.style.display = 'none';
                        }
                    }
                    
                    // Reset selection
                    teacherSelect.value = "";
                }
                </script>
            </div>
        </div>
        
        <!-- TAB 5: REGISTER STUDENTS - Auto roll number -->
        <div id="students-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-user-plus"></i> Register New Student</h3>
                </div>
                
                <div class="auto-roll">
                    <i class="fas fa-id-card"></i>
                    Roll Number will be auto-generated: <strong>BS***</strong> format
                </div>
                
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Student Full Name *</label>
                            <input type="text" name="student_name" placeholder="e.g. John Smith" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date of Birth</label>
                            <input type="date" name="dob">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-chalkboard"></i> Grade *</label>
                            <select name="grade" required>
                                <option value="">Select Grade</option>
                                <?php for($i=1; $i<=12; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-columns"></i> Section *</label>
                            <select name="section" required>
                                <option value="">Select Section</option>
                                <?php foreach ($all_sections as $s): ?>
                                <option value="<?php echo $s; ?>">Section <?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-friends"></i> Parent Name</label>
                            <input type="text" name="parent_name" placeholder="Parent full name">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Parent Phone</label>
                            <input type="text" name="parent_phone" placeholder="+251 XXX XXX XXX">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Parent Email (Optional)</label>
                            <input type="email" name="parent_email" placeholder="parent@example.com">
                            <small style="color: #666;">Parent account will be created if email provided</small>
                        </div>
                    </div>
                    <button type="submit" name="register_student" class="btn btn-success">
                        <i class="fas fa-save"></i> Register Student (Auto Roll Number)
                    </button>
                </form>
            </div>
        </div>
        
        <!-- TAB 6: VIEW ALL ASSIGNMENTS -->
        <div id="view-tab" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h3><i class="fas fa-eye"></i> Current Assignments</h3>
                </div>
                
                <?php foreach ($classes as $class): ?>
                <div style="margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 12px;">
                    <h4 style="color: #667eea; margin-bottom: 15px;">
                        Class <?php echo $class['class_name']; ?>
                        <?php if($class['homeroom_teacher_name']): ?>
                            <span class="badge badge-success" style="margin-left: 15px;">
                                Homeroom: <?php echo $class['homeroom_teacher_name']; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-warning" style="margin-left: 15px;">
                                No Homeroom Teacher
                            </span>
                        <?php endif; ?>
                    </h4>
                    
                    <h5>Subject Teachers:</h5>
                    <?php if(!empty($class_subject_teachers[$class['id']])): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;">
                            <?php foreach($class_subject_teachers[$class['id']] as $assignment): ?>
                                <div style="background: white; padding: 10px 15px; border-radius: 30px; border: 1px solid #ddd;">
                                    <span class="badge badge-primary"><?php echo $assignment['subject_name']; ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <?php echo $assignment['teacher_name']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #999;">No subject teachers assigned yet.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>