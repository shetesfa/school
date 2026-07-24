<?php
// ============================================
// SCHOOL MANAGEMENT SYSTEM - SETUP
// RUN THIS FIRST TO CREATE DATABASE AND TABLES
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect without database
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("<div style='background:#f44336;color:white;padding:20px;font-family:Arial;'>
            <h1>❌ MySQL Connection Failed</h1>
            <p>Error: " . $e->getMessage() . "</p>
            <p>Make sure XAMPP MySQL is running!</p>
         </div>");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Drop and recreate database
       
        $pdo->exec("CREATE DATABASE school_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE school_management");
        
        // ============================================
        // CREATE TABLES
        // ============================================
        
        // Users table
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('superadmin','teacher','parent','registration') NOT NULL,
                status ENUM('active','inactive') DEFAULT 'active',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Subjects table
        $pdo->exec("
            CREATE TABLE subjects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                code VARCHAR(10) UNIQUE,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Teachers table
        $pdo->exec("
            CREATE TABLE teachers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                subject_id INT,
                phone VARCHAR(20),
                address TEXT,
                photo VARCHAR(255) DEFAULT NULL,
                join_date DATE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Classes table
        $pdo->exec("
            CREATE TABLE classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                grade VARCHAR(10) NOT NULL,
                section VARCHAR(10) NOT NULL,
                teacher_id INT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_class (grade, section),
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Students table
        $pdo->exec("
            CREATE TABLE students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                roll_number VARCHAR(20) NOT NULL UNIQUE,
                grade VARCHAR(10) NOT NULL,
                class_section VARCHAR(10) NOT NULL,
                date_of_birth DATE,
                photo VARCHAR(255) DEFAULT NULL,
                parent_name VARCHAR(100),
                parent_phone VARCHAR(20),
                parent_email VARCHAR(100),
                address TEXT,
                teacher_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Attendance table
        $pdo->exec("
            CREATE TABLE attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                date DATE NOT NULL,
                status ENUM('Present','Absent','Permission') NOT NULL DEFAULT 'Present',
                remarks TEXT,
                marked_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_attendance (student_id, date),
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Mark Criteria table
        $pdo->exec("
            CREATE TABLE mark_criteria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                subject_id INT NOT NULL,
                criteria_name VARCHAR(50) NOT NULL,
                criteria_weight INT NOT NULL DEFAULT 0,
                criteria_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_teacher_subject_criteria (teacher_id, subject_id, criteria_name),
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Student Marks Custom table
        $pdo->exec("
            CREATE TABLE student_marks_custom (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                subject_id INT NOT NULL,
                teacher_id INT NOT NULL,
                term VARCHAR(20) NOT NULL DEFAULT 'Term 1',
                criteria_data LONGTEXT NOT NULL COMMENT 'JSON criteria values',
                total_mark DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                grade CHAR(2) NOT NULL DEFAULT 'F',
                remarks TEXT,
                is_locked TINYINT(1) DEFAULT 0,
                locked_by INT,
                locked_at TIMESTAMP NULL,
                entered_by INT,
                entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_student_subject_term (student_id, subject_id, term),
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Mark Criteria Templates
        $pdo->exec("
            CREATE TABLE mark_criteria_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                criteria_data LONGTEXT NOT NULL COMMENT 'JSON criteria',
                subject_id INT,
                created_by INT,
                is_public TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
        
        // Comments table
        $pdo->exec("
            CREATE TABLE comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                teacher_id INT,
                subject_id INT,
                comment_type ENUM('subject_teacher','homeroom','director_warning','director_praise') NOT NULL,
                comment_text TEXT NOT NULL,
                is_private TINYINT(1) DEFAULT 0,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Parent Access Control table
        $pdo->exec("
            CREATE TABLE parent_access (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                unlocked_by INT NOT NULL,
                unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                unlocked_until DATETIME NOT NULL,
                access_type ENUM('single','whole_class') DEFAULT 'single',
                is_active TINYINT(1) DEFAULT 1,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (unlocked_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Semester Locks table
        $pdo->exec("
            CREATE TABLE semester_locks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                term VARCHAR(20) NOT NULL,
                academic_year VARCHAR(9) NOT NULL,
                locked_by INT,
                locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_finalized TINYINT(1) DEFAULT 0,
                UNIQUE KEY unique_class_term (class_id, term, academic_year),
                FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Class Rankings table
        $pdo->exec("
            CREATE TABLE class_rankings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                student_id INT NOT NULL,
                term VARCHAR(20) NOT NULL,
                academic_year VARCHAR(9) NOT NULL,
                total_percentage DECIMAL(5,2) NOT NULL,
                rank_position INT NOT NULL,
                calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_student_rank (class_id, student_id, term, academic_year),
                FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Password Resets table
        $pdo->exec("
            CREATE TABLE password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Remember Tokens table
        $pdo->exec("
            CREATE TABLE remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                user_agent TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // ============================================
        // INSERT DEFAULT DATA
        // ============================================
        
        // Create Super Admin
        $superadmin_pass = password_hash('superadmin123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (name, email, password, role, status) VALUES
            ('Super Admin', 'superadmin@school.com', '$superadmin_pass', 'superadmin', 'active')
        ");
        
        // Create Registration Officers
        $reg_pass = password_hash('reg123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (name, email, password, role, status) VALUES
            ('Registration Officer', 'registrar@school.com', '$reg_pass', 'registration', 'active'),
            ('Assistant Registrar', 'assistant@school.com', '$reg_pass', 'registration', 'active')
        ");
        
        // Create Subjects
        $pdo->exec("
            INSERT INTO subjects (name, code) VALUES
            ('Mathematics', 'MATH101'),
            ('English', 'ENG101'),
            ('Science', 'SCI101'),
            ('History', 'HIS101'),
            ('Geography', 'GEO101'),
            ('Computer Science', 'CS101'),
            ('Physical Education', 'PE101'),
            ('Art', 'ART101'),
            ('Music', 'MUS101')
        ");
        
        // Create Mark Criteria Templates
        $pdo->exec("
            INSERT INTO mark_criteria_templates (name, description, criteria_data, is_public) VALUES
            ('Standard Academic', 'Assignment 20%, Mid Exam 20%, Attendance 10%, Final 50%', '[{\"name\":\"Assignment\",\"weight\":20},{\"name\":\"Mid Exam\",\"weight\":20},{\"name\":\"Attendance\",\"weight\":10},{\"name\":\"Final Exam\",\"weight\":50}]', 1),
            ('Physical Education', 'Fitness Test 40%, Skills 30%, Sportsmanship 20%, Attendance 10%', '[{\"name\":\"Fitness Test\",\"weight\":40},{\"name\":\"Skills\",\"weight\":30},{\"name\":\"Sportsmanship\",\"weight\":20},{\"name\":\"Attendance\",\"weight\":10}]', 1),
            ('Project Based', 'Project 50%, Presentation 25%, Report 25%', '[{\"name\":\"Project\",\"weight\":50},{\"name\":\"Presentation\",\"weight\":25},{\"name\":\"Report\",\"weight\":25}]', 1)
        ");
        
        // Create sample teachers
        $teacher_pass = password_hash('teacher123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (name, email, password, role, status) VALUES
            ('Mr. James Wilson', 'james@school.com', '$teacher_pass', 'teacher', 'active'),
            ('Ms. Sarah Johnson', 'sarah@school.com', '$teacher_pass', 'teacher', 'active'),
            ('Mr. Robert Brown', 'robert@school.com', '$teacher_pass', 'teacher', 'active'),
            ('Ms. Emily Davis', 'emily@school.com', '$teacher_pass', 'teacher', 'active'),
            ('Mr. Michael Lee', 'michael@school.com', '$teacher_pass', 'teacher', 'active')
        ");
        
        // Get subject IDs
        $math = $pdo->query("SELECT id FROM subjects WHERE name='Mathematics'")->fetchColumn();
        $eng = $pdo->query("SELECT id FROM subjects WHERE name='English'")->fetchColumn();
        $sci = $pdo->query("SELECT id FROM subjects WHERE name='Science'")->fetchColumn();
        $geo = $pdo->query("SELECT id FROM subjects WHERE name='Geography'")->fetchColumn();
        $cs = $pdo->query("SELECT id FROM subjects WHERE name='Computer Science'")->fetchColumn();
        
        // Get teacher user IDs
        $james = $pdo->query("SELECT id FROM users WHERE email='james@school.com'")->fetchColumn();
        $sarah = $pdo->query("SELECT id FROM users WHERE email='sarah@school.com'")->fetchColumn();
        $robert = $pdo->query("SELECT id FROM users WHERE email='robert@school.com'")->fetchColumn();
        $emily = $pdo->query("SELECT id FROM users WHERE email='emily@school.com'")->fetchColumn();
        $michael = $pdo->query("SELECT id FROM users WHERE email='michael@school.com'")->fetchColumn();
        
        // Insert teachers
        $pdo->exec("
            INSERT INTO teachers (user_id, subject_id, phone) VALUES
            ($james, $math, '+1234567890'),
            ($sarah, $eng, '+1234567891'),
            ($robert, $sci, '+1234567892'),
            ($emily, $geo, '+1234567893'),
            ($michael, $cs, '+1234567894')
        ");
        
        // Get teacher IDs
        $t1 = $pdo->query("SELECT id FROM teachers WHERE user_id=$james")->fetchColumn();
        $t2 = $pdo->query("SELECT id FROM teachers WHERE user_id=$sarah")->fetchColumn();
        $t3 = $pdo->query("SELECT id FROM teachers WHERE user_id=$robert")->fetchColumn();
        $t4 = $pdo->query("SELECT id FROM teachers WHERE user_id=$emily")->fetchColumn();
        $t5 = $pdo->query("SELECT id FROM teachers WHERE user_id=$michael")->fetchColumn();
        
        // Create classes
        $pdo->exec("
            INSERT INTO classes (grade, section, teacher_id, created_by) VALUES
            ('10', 'A', $t1, 1),
            ('10', 'B', $t5, 1),
            ('9', 'A', $t4, 1),
            ('9', 'B', $t2, 1),
            ('8', 'A', $t3, 1)
        ");
        
        // Create sample students
        $pdo->exec("
            INSERT INTO students (name, roll_number, grade, class_section, teacher_id) VALUES
            ('John Smith', 'STU001', '10', 'A', $t1),
            ('Emma Johnson', 'STU002', '10', 'A', $t1),
            ('Michael Brown', 'STU003', '10', 'A', $t1),
            ('Sophia Williams', 'STU004', '10', 'A', $t1),
            ('William Jones', 'STU005', '10', 'A', $t1),
            ('Olivia Garcia', 'STU006', '10', 'A', $t1),
            ('James Miller', 'STU007', '10', 'A', $t1),
            ('Ava Davis', 'STU008', '10', 'A', $t1),
            ('Benjamin Rodriguez', 'STU009', '10', 'A', $t1),
            ('Mia Martinez', 'STU010', '10', 'A', $t1),
            ('Ethan Hernandez', 'STU011', '10', 'B', $t5),
            ('Charlotte Lopez', 'STU012', '10', 'B', $t5)
        ");
        
        // Create parent accounts
        $parent_pass = password_hash('parent123', PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT INTO users (name, email, password, role, status) VALUES
            ('John Smith', 'STU001', '$parent_pass', 'parent', 'active'),
            ('Emma Johnson', 'STU002', '$parent_pass', 'parent', 'active'),
            ('Michael Brown', 'STU003', '$parent_pass', 'parent', 'active')
        ");
        
        $message = "✅ Database created successfully! All tables and sample data installed.";
        
    } catch (PDOException $e) {
        $error = "❌ Setup failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>School Management System - Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #1e3c72, #2a5298); min-height: 100vh; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; }
        .setup-box { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        h1 { color: #1e3c72; margin-bottom: 20px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .message { background: #e8f5e9; border-left: 4px solid #4CAF50; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #ffebee; border-left: 4px solid #f44336; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .btn { background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border: none; padding: 16px 30px; font-size: 1.1rem; border-radius: 30px; cursor: pointer; font-weight: 600; width: 100%; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .credentials { background: #f8f9fa; border-radius: 12px; padding: 20px; margin-top: 30px; }
        .cred-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .cred-item { background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #1e3c72; }
    </style>
</head>
<body>
    <div class="container">
        <div class="setup-box">
            <h1>🏫 School Management System - Setup</h1>
            
            <?php if($message): ?>
                <div class="message"><?php echo $message; ?></div>
                <div class="credentials">
                    <h3>🔐 Login Credentials:</h3>
                    <div class="cred-grid">
                        <div class="cred-item"><strong>Super Admin</strong><br>superadmin@school.com / superadmin123</div>
                        <div class="cred-item"><strong>Registration</strong><br>registrar@school.com / reg123</div>
                        <div class="cred-item"><strong>Teacher</strong><br>james@school.com / teacher123</div>
                        <div class="cred-item"><strong>Parent</strong><br>John Smith / parent123</div>
                    </div>
                </div>
                <div style="margin-top: 30px; text-align: center;">
                    <a href="login.php" style="background: #4CAF50; color: white; text-decoration: none; padding: 12px 30px; border-radius: 30px; font-weight: 600;">Go to Login</a>
                    <p style="margin-top: 15px; color: #f44336;"><strong>⚠️ DELETE setup.php AFTER installation!</strong></p>
                </div>
            <?php elseif($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php else: ?>
                <div class="warning">
                    <h3>⚠️ Important!</h3>
                    <p>This will create a new database called <strong>school_management</strong> and overwrite any existing data.</p>
                    <p>Make sure MySQL is running in XAMPP.</p>
                </div>
                <form method="POST">
                    <button type="submit" class="btn" onclick="return confirm('This will DELETE all existing data! Continue?')">
                        Create Database & Install System
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>