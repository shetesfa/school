<?php
require_once 'config.php';

// Only registration office and superadmin can access (but primarily for registration)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['registration', 'superadmin'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

// Handle student edit
if (isset($_POST['edit_student'])) {
    $student_id = (int)$_POST['student_id'];
    $name = trim($_POST['name']);
    $roll_number = trim($_POST['roll_number']);
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $parent_name = trim($_POST['parent_name']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_email = trim($_POST['parent_email']);
    $date_of_birth = $_POST['date_of_birth'] ?: null;
    
    // Check if roll number exists (for another student)
    $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ?");
    $stmt->execute([$roll_number, $student_id]);
    if ($stmt->fetch()) {
        $error = "Roll number already exists for another student!";
    } else {
        // Update student
        $stmt = $pdo->prepare("
            UPDATE students SET 
                name = ?, roll_number = ?, grade = ?, class_section = ?,
                parent_name = ?, parent_phone = ?, parent_email = ?, date_of_birth = ?
            WHERE id = ?
        ");
        if ($stmt->execute([$name, $roll_number, $grade, $section, $parent_name, $parent_phone, $parent_email, $date_of_birth, $student_id])) {
            $message = "Student updated successfully!";
        } else {
            $error = "Failed to update student.";
        }
    }
}

// Handle teacher edit
if (isset($_POST['edit_teacher'])) {
    $teacher_id = (int)$_POST['teacher_id'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']); // Changed from email to phone
    $subject_id = $_POST['subject_id'] ?: null;
    $status = $_POST['status'];
    
    // Get user_id from teacher
    $stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE id = ?");
    $stmt->execute([$teacher_id]);
    $user_id = $stmt->fetchColumn();
    
    if ($user_id) {
        $pdo->beginTransaction();
        try {
            // Update users table (name and status only, email not shown)
            $stmt = $pdo->prepare("UPDATE users SET name = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $status, $user_id]);
            
            // Update teachers table
            $stmt = $pdo->prepare("UPDATE teachers SET subject_id = ?, phone = ? WHERE id = ?");
            $stmt->execute([$subject_id, $phone, $teacher_id]);
            
            $pdo->commit();
            $message = "Teacher updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update teacher: " . $e->getMessage();
        }
    }
}

// Handle class assignment change
if (isset($_POST['change_class'])) {
    $student_id = (int)$_POST['student_id'];
    $new_grade = $_POST['new_grade'];
    $new_section = $_POST['new_section'];
    
    // Get new teacher for this class
    $stmt = $pdo->prepare("SELECT teacher_id FROM classes WHERE grade = ? AND section = ?");
    $stmt->execute([$new_grade, $new_section]);
    $new_teacher_id = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("UPDATE students SET grade = ?, class_section = ?, teacher_id = ? WHERE id = ?");
    if ($stmt->execute([$new_grade, $new_section, $new_teacher_id, $student_id])) {
        $message = "Student class changed successfully!";
    } else {
        $error = "Failed to change class.";
    }
}

// Get all students for dropdown
$students = $pdo->query("
    SELECT s.*, CONCAT(s.grade, s.class_section) as class_name,
           u.name as teacher_name
    FROM students s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY s.grade, s.class_section, s.name
")->fetchAll();

// Get all teachers (show phone instead of email)
$teachers = $pdo->query("
    SELECT t.*, u.name, t.phone, u.status,
           sub.name as subject_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN subjects sub ON t.subject_id = sub.id
    ORDER BY u.name
")->fetchAll();

// Get all subjects
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();

// Get all classes
$classes = $pdo->query("
    SELECT c.*, CONCAT(c.grade, c.section) as class_name,
           u.name as teacher_name
    FROM classes c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY c.grade, c.section
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Edit - Bori Secondary School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }
        
        body {
            background: #f8fafc;
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .tab {
            padding: 15px 25px;
            background: #f1f5f9;
            cursor: pointer;
            font-weight: 600;
            color: #475569;
            border-right: 1px solid #cbd5e1;
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .edit-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .edit-card h3 {
            margin-bottom: 15px;
            color: #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #475569;
        }
        
        label i {
            margin-right: 8px;
            color: #667eea;
        }
        
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        input:focus, select:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .photo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }
        
        .student-id-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .upload-progress {
            display: none;
            margin-top: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            width: 0%;
            transition: width 0.3s;
        }
        
        .role-badge {
            background: #ffd700;
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-edit"></i> Registration Edit Panel
            <span class="role-badge">Registration Office</span>
        </h1>
        <div>
            <span style="margin-right: 20px;"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></span>
            <?php if ($_SESSION['role'] == 'superadmin'): ?>
                <a href="superadmin.php" style="color: white; margin-right: 15px;">Admin</a>
            <?php endif; ?>
            <a href="registration.php" style="color: white; margin-right: 15px;">Registration</a>
            <a href="logout.php" style="color: white;">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('students')">📚 Students</div>
            <div class="tab" onclick="showTab('teachers')">👨‍🏫 Teachers</div>
            <div class="tab" onclick="showTab('classes')">🔄 Change Class</div>
        </div>
        
        <!-- Students Tab -->
        <div id="students-tab" class="tab-content active">
            <div class="search-box">
                <input type="text" id="searchStudent" placeholder="🔍 Search by name or roll number...">
            </div>
            
            <?php foreach ($students as $student): 
                // Format roll number to BS format
                $roll_display = $student['roll_number'];
                if (!preg_match('/^BS\d+$/', $roll_display)) {
                    $roll_display = 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
                }
            ?>
            <div class="edit-card student-card" data-name="<?php echo strtolower($student['name']); ?>" data-roll="<?php echo strtolower($student['roll_number']); ?>">
                <form method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                    
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <?php if ($student['photo']): ?>
                            <img src="<?php echo $student['photo']; ?>" class="photo-preview" alt="Photo" id="photo-<?php echo $student['id']; ?>">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Full Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> Roll Number</label>
                                    <input type="text" name="roll_number" value="<?php echo $student['roll_number']; ?>" required>
                                    <small>BS format recommended</small>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-chalkboard"></i> Grade</label>
                                    <select name="grade">
                                        <?php for($i=1; $i<=12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $student['grade'] == $i ? 'selected' : ''; ?>>
                                            Grade <?php echo $i; ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-columns"></i> Section</label>
                                    <select name="section">
                                        <?php foreach(range('A', 'H') as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $student['class_section'] == $s ? 'selected' : ''; ?>>
                                            Section <?php echo $s; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user-friends"></i> Parent Name</label>
                                    <input type="text" name="parent_name" value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> Parent Phone</label>
                                    <input type="text" name="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Parent Email (Optional)</label>
                                    <input type="email" name="parent_email" value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-calendar"></i> Date of Birth</label>
                                    <input type="date" name="date_of_birth" value="<?php echo $student['date_of_birth']; ?>">
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                <button type="submit" name="edit_student" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-warning" onclick="uploadPhoto(<?php echo $student['id']; ?>, 'student')">
                                    <i class="fas fa-camera"></i> Upload Photo
                                </button>
                            </div>
                            
                            <!-- Upload Progress -->
                            <div id="progress-<?php echo $student['id']; ?>" class="upload-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill-<?php echo $student['id']; ?>"></div>
                                </div>
                                <small id="progress-text-<?php echo $student['id']; ?>">Uploading...</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Teachers Tab (Phone instead of email) -->
        <div id="teachers-tab" class="tab-content">
            <div class="search-box">
                <input type="text" id="searchTeacher" placeholder="🔍 Search by name or phone...">
            </div>
            
            <?php foreach ($teachers as $teacher): ?>
            <div class="edit-card teacher-card" data-name="<?php echo strtolower($teacher['name']); ?>" data-phone="<?php echo strtolower($teacher['phone'] ?? ''); ?>">
                <form method="POST">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                    
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        <?php if ($teacher['photo']): ?>
                            <img src="<?php echo $teacher['photo']; ?>" class="photo-preview" alt="Photo" id="teacher-photo-<?php echo $teacher['id']; ?>">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Full Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> Phone Number</label>
                                    <input type="text" name="phone" value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-book"></i> Subject</label>
                                    <select name="subject_id">
                                        <option value="">-- No Subject --</option>
                                        <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo $teacher['subject_id'] == $subject['id'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-toggle-on"></i> Status</label>
                                    <select name="status">
                                        <option value="active" <?php echo $teacher['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $teacher['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                <button type="submit" name="edit_teacher" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-warning" onclick="uploadPhoto(<?php echo $teacher['id']; ?>, 'teacher')">
                                    <i class="fas fa-camera"></i> Upload Photo
                                </button>
                            </div>
                            
                            <!-- Upload Progress -->
                            <div id="progress-<?php echo $teacher['id']; ?>" class="upload-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill-<?php echo $teacher['id']; ?>"></div>
                                </div>
                                <small id="progress-text-<?php echo $teacher['id']; ?>">Uploading...</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Change Class Tab -->
        <div id="classes-tab" class="tab-content">
            <h3 style="margin-bottom: 20px; color: #667eea;">Change Student's Class</h3>
            
            <div class="search-box">
                <input type="text" id="searchClassStudent" placeholder="🔍 Search student...">
            </div>
            
            <?php foreach ($students as $student): 
                $roll_display = $student['roll_number'];
                if (!preg_match('/^BS\d+$/', $roll_display)) {
                    $roll_display = 'BS' . str_pad($student['id'], 3, '0', STR_PAD_LEFT);
                }
            ?>
            <div class="edit-card class-student-card" data-name="<?php echo strtolower($student['name']); ?>" data-roll="<?php echo strtolower($student['roll_number']); ?>">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php echo htmlspecialchars($student['name']); ?></strong> 
                        <span class="student-id-badge"><?php echo $roll_display; ?></span><br>
                        <small>Current Class: <?php echo $student['class_name']; ?></small>
                    </div>
                    
                    <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                        
                        <select name="new_grade" style="width: auto;">
                            <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $student['grade'] == $i ? 'selected' : ''; ?>>
                                Grade <?php echo $i; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select name="new_section" style="width: auto;">
                            <?php foreach(range('A', 'H') as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $student['class_section'] == $s ? 'selected' : ''; ?>>
                                Section <?php echo $s; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" name="change_class" class="btn btn-primary">
                            <i class="fas fa-arrows-alt"></i> Change
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Hidden file input for photo upload (50MB max) -->
    <input type="file" id="photoUpload" accept="image/*" style="display: none;">
    
    <script>
        let currentUpload = { id: 0, type: '' };
        
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Search students
        document.getElementById('searchStudent')?.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.student-card').forEach(card => {
                const name = card.dataset.name;
                const roll = card.dataset.roll;
                card.style.display = (name.includes(query) || roll.includes(query)) ? '' : 'none';
            });
        });
        
        // Search teachers
        document.getElementById('searchTeacher')?.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.teacher-card').forEach(card => {
                const name = card.dataset.name;
                const phone = card.dataset.phone;
                card.style.display = (name.includes(query) || (phone && phone.includes(query))) ? '' : 'none';
            });
        });
        
        // Search class students
        document.getElementById('searchClassStudent')?.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.class-student-card').forEach(card => {
                const name = card.dataset.name;
                const roll = card.dataset.roll;
                card.style.display = (name.includes(query) || roll.includes(query)) ? '' : 'none';
            });
        });
        
        // Upload photo function (50MB max)
        function uploadPhoto(id, type) {
            currentUpload = { id, type };
            const fileInput = document.getElementById('photoUpload');
            
            // Set max file size message
            fileInput.setAttribute('title', 'Max file size: 50MB');
            fileInput.click();
        }
        
        document.getElementById('photoUpload').addEventListener('change', function(e) {
            if (this.files.length === 0) return;
            
            const file = this.files[0];
            
            // Check file size (50MB max)
            const maxSize = 50 * 1024 * 1024; // 50MB in bytes
            if (file.size > maxSize) {
                alert('❌ File too large! Maximum size is 50MB.');
                return;
            }
            
            // Show progress bar
            const progressDiv = document.getElementById('progress-' + currentUpload.id);
            const progressFill = document.getElementById('progress-fill-' + currentUpload.id);
            const progressText = document.getElementById('progress-text-' + currentUpload.id);
            
            if (progressDiv) {
                progressDiv.style.display = 'block';
                progressFill.style.width = '0%';
                progressText.textContent = 'Uploading... 0%';
            }
            
            const formData = new FormData();
            formData.append('photo', file);
            formData.append('type', currentUpload.type);
            formData.append('id', currentUpload.id);
            
            // Simulate progress (since fetch doesn't show progress easily)
            let progress = 0;
            const interval = setInterval(() => {
                if (progress < 90) {
                    progress += 10;
                    if (progressFill) {
                        progressFill.style.width = progress + '%';
                        progressText.textContent = 'Uploading... ' + progress + '%';
                    }
                }
            }, 200);
            
            fetch('upload_photo.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(interval);
                
                if (progressFill) {
                    progressFill.style.width = '100%';
                    progressText.textContent = 'Completed!';
                }
                
                if (data.success) {
                    alert('✅ Photo uploaded successfully!');
                    
                    // Update photo preview
                    const photoElement = currentUpload.type === 'student' 
                        ? document.getElementById('photo-' + currentUpload.id)
                        : document.getElementById('teacher-photo-' + currentUpload.id);
                    
                    if (photoElement) {
                        photoElement.src = data.path + '?t=' + new Date().getTime();
                    } else {
                        location.reload();
                    }
                    
                    setTimeout(() => {
                        if (progressDiv) progressDiv.style.display = 'none';
                    }, 2000);
                } else {
                    alert('❌ Upload failed: ' + (data.error || 'Unknown error'));
                    if (progressDiv) progressDiv.style.display = 'none';
                }
            })
            .catch(error => {
                clearInterval(interval);
                alert('❌ Upload failed: ' + error.message);
                if (progressDiv) progressDiv.style.display = 'none';
            });
        });
    </script>
</body>
</html>