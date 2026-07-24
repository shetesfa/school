-- ============================================
-- SCHOOL MANAGEMENT SYSTEM - Complete Schema v2.0
-- International School Edition
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `school_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_management`;

-- ── Drop existing tables ──────────────────────
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS chat_messages, chat_conversations, announcements, announcement_reads,
  attendance, calendar_events, class_rankings, class_subject_teachers, comments, marks,
  mark_criteria, mark_criteria_templates, monthly_attendance, notifications, parent_access,
  password_resets, remember_tokens, school_settings, semester_locks, students,
  student_marks_custom, student_marks_detail, subjects, teachers, class_teachers, classes,
  users, academic_years;
SET FOREIGN_KEY_CHECKS = 1;

-- ── academic_years ───────────────────────────
CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_label` varchar(20) NOT NULL COMMENT '2025/2026',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── school_settings ───────────────────────────
CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── users ─────────────────────────────────────
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','teacher','parent','registration') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `dark_mode` tinyint(1) DEFAULT 0,
  `lang` varchar(5) DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── subjects ──────────────────────────────────
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── teachers ──────────────────────────────────
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_teachers_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── classes ───────────────────────────────────
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade` varchar(10) NOT NULL,
  `section` varchar(10) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL COMMENT 'Homeroom teacher',
  `room_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grade_section` (`grade`,`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── class_subject_teachers ────────────────────
CREATE TABLE `class_subject_teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`class_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── students ──────────────────────────────────
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `roll_number` varchar(20) NOT NULL,
  `grade` varchar(10) NOT NULL,
  `class_section` varchar(10) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(30) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT '2025/2026',
  `status` enum('active','inactive','transferred','graduated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `roll_number` (`roll_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── parent_access ─────────────────────────────
CREATE TABLE `parent_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'parent user id',
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_student` (`user_id`,`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── attendance ────────────────────────────────
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
  `remarks` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_date` (`student_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── mark_criteria_templates ───────────────────
CREATE TABLE `mark_criteria_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `criteria_json` text NOT NULL COMMENT '[{"name":"Assignment","max":20},...]',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── student_marks_custom ──────────────────────
CREATE TABLE `student_marks_custom` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `term` varchar(20) NOT NULL DEFAULT 'Term 1',
  `academic_year` varchar(20) DEFAULT '2025/2026',
  `criteria_data` longtext NOT NULL COMMENT 'JSON scores per criterion',
  `criteria_max` longtext DEFAULT NULL COMMENT 'JSON max per criterion',
  `total_mark` decimal(6,2) NOT NULL DEFAULT 0.00,
  `max_mark` decimal(6,2) NOT NULL DEFAULT 100.00,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade` char(2) NOT NULL DEFAULT 'F',
  `remarks` text DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` timestamp NULL DEFAULT NULL,
  `entered_by` int(11) DEFAULT NULL,
  `entered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mark` (`student_id`,`subject_id`,`term`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── class_rankings ────────────────────────────
CREATE TABLE `class_rankings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `term` varchar(20) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `total_percentage` decimal(5,2) NOT NULL,
  `rank_position` int(11) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rank` (`class_id`,`student_id`,`term`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── semester_locks ────────────────────────────
CREATE TABLE `semester_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL COMMENT 'NULL = entire class',
  `term` varchar(20) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `is_locked` tinyint(1) DEFAULT 1,
  `locked_by` int(11) DEFAULT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── comments ──────────────────────────────────
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `term` varchar(20) DEFAULT 'Term 1',
  `academic_year` varchar(20) DEFAULT '2025/2026',
  `comment_text` text NOT NULL,
  `type` enum('academic','behavioral','general') DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── calendar_events ───────────────────────────
CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `type` enum('holiday','exam','meeting','event','graduation','other') DEFAULT 'event',
  `color` varchar(7) DEFAULT '#667eea',
  `is_public` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── announcements ─────────────────────────────
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `target_role` enum('teacher','parent','all') DEFAULT 'all',
  `priority` enum('normal','high','urgent') DEFAULT 'normal',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── chat_conversations ────────────────────────
CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── chat_messages ─────────────────────────────
CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_type` enum('image','pdf','voice','file') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── notifications ─────────────────────────────
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── password_resets ───────────────────────────
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── remember_tokens ───────────────────────────
CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA
-- ============================================

-- Academic Year
INSERT INTO `academic_years` (`year_label`, `start_date`, `end_date`, `is_current`) VALUES
('2025/2026', '2025-09-01', '2026-06-30', 1);

-- School Settings
INSERT INTO `school_settings` (`setting_key`, `setting_value`) VALUES
('result_visibility', '4'),
('current_term', 'Term 1'),
('current_year', '2025/2026'),
('school_name', 'EduTrack International School'),
('school_address', '123 Education Street, City'),
('school_phone', '+1-800-SCHOOL'),
('school_email', 'info@edutrack.edu'),
('marks_lock_global', '0'),
('pass_percentage', '50'),
('school_motto', 'Excellence in Education'),
('school_logo', 'images/logo.png');

-- Users (admin=superadmin123, teachers=teacher123, parents=parent123)
INSERT INTO `users` (`id`,`name`,`email`,`password`,`role`,`status`) VALUES
(1, 'Super Admin',       'admin@school.com',           '$2y$10$fpdZ3r7xEFHCeAIC0TC3O.O/QTQVRmhio7DmyGbTgml1iguz2zj1u', 'superadmin','active'),
(2, 'School Director',   'director@school.com',        '$2y$10$fpdZ3r7xEFHCeAIC0TC3O.O/QTQVRmhio7DmyGbTgml1iguz2zj1u', 'superadmin','active'),
(3, 'Mr. James Wilson',  'james@school.com',           '$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu', 'teacher',   'active'),
(4, 'Ms. Sarah Johnson', 'sarah@school.com',           '$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu', 'teacher',   'active'),
(5, 'Mr. Robert Brown',  'robert@school.com',          '$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu', 'teacher',   'active'),
(6, 'Ms. Emily Davis',   'emily@school.com',           '$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu', 'teacher',   'active'),
(7, 'Mr. Michael Lee',   'michael@school.com',         '$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu', 'teacher',   'active'),
(8, 'Parent - Smith',    'parent.smith@mail.com',      '$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO','parent',    'active'),
(9, 'Parent - Johnson',  'parent.johnson@mail.com',    '$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO','parent',    'active'),
(10,'Parent - Brown',    'parent.brown@mail.com',      '$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO','parent',    'active');

-- Subjects
INSERT INTO `subjects` (`id`,`name`,`code`,`color`) VALUES
(1,'Mathematics','MATH','#ef4444'),
(2,'English Language','ENG','#3b82f6'),
(3,'Science','SCI','#10b981'),
(4,'History','HIS','#f59e0b'),
(5,'Geography','GEO','#8b5cf6'),
(6,'Computer Science','CS','#06b6d4'),
(7,'Physical Education','PE','#f97316'),
(8,'Art & Design','ART','#ec4899'),
(9,'Amharic','AMH','#84cc16');

-- Teachers
INSERT INTO `teachers` (`id`,`user_id`,`subject_id`,`phone`,`qualification`) VALUES
(1, 3, 1, '+251911001001','BSc Mathematics'),
(2, 4, 2, '+251911001002','BA English Literature'),
(3, 5, 3, '+251911001003','BSc Physics'),
(4, 6, 4, '+251911001004','BA History'),
(5, 7, 6, '+251911001005','MSc Computer Science');

-- Classes
INSERT INTO `classes` (`id`,`grade`,`section`,`teacher_id`,`created_by`) VALUES
(1,'10','A',1,1),(2,'10','B',2,1),
(3,'9','A', 3,1),(4,'9','B', 4,1),
(5,'8','A', 5,1);

-- Assign subjects to classes
INSERT INTO `class_subject_teachers` (`class_id`,`subject_id`,`teacher_id`) VALUES
(1,1,1),(1,2,2),(1,3,3),(1,4,4),(1,6,5),
(2,1,1),(2,2,2),(2,3,3),
(3,1,1),(3,2,2),(3,3,3),
(4,1,1),(4,4,4),
(5,1,1),(5,6,5);

-- Students (Grade 10A)
INSERT INTO `students` (`id`,`name`,`roll_number`,`grade`,`class_section`,`gender`,`date_of_birth`,`nationality`,`parent_name`,`parent_phone`,`parent_email`,`teacher_id`,`academic_year`) VALUES
(1, 'John Smith',       'STU0001','10','A','Male',  '2008-03-15','American','Robert Smith',   '+1-555-0101','parent.smith@mail.com',  1,'2025/2026'),
(2, 'Emma Johnson',     'STU0002','10','A','Female','2008-05-22','British', 'David Johnson',   '+1-555-0102','parent.johnson@mail.com', 1,'2025/2026'),
(3, 'Michael Brown',    'STU0003','10','A','Male',  '2008-07-10','American','James Brown',     '+1-555-0103','parent.brown@mail.com',   1,'2025/2026'),
(4, 'Sophia Williams',  'STU0004','10','A','Female','2008-01-30','Canadian','Mark Williams',   '+1-555-0104','williams@mail.com',       1,'2025/2026'),
(5, 'William Jones',    'STU0005','10','A','Male',  '2008-09-14','American','Paul Jones',      '+1-555-0105','jones@mail.com',          1,'2025/2026'),
(6, 'Olivia Garcia',    'STU0006','10','A','Female','2008-11-05','Spanish', 'Carlos Garcia',   '+1-555-0106','garcia@mail.com',         1,'2025/2026'),
(7, 'James Miller',     'STU0007','10','A','Male',  '2008-12-20','American','Henry Miller',    '+1-555-0107','miller@mail.com',         1,'2025/2026'),
(8, 'Ava Davis',        'STU0008','10','A','Female','2008-02-18','British', 'Thomas Davis',    '+1-555-0108','davis@mail.com',          1,'2025/2026'),
(9, 'Liam Wilson',      'STU0009','10','A','Male',  '2008-06-25','American','Gary Wilson',     '+1-555-0109','wilson@mail.com',         1,'2025/2026'),
(10,'Isabella Moore',   'STU0010','10','A','Female','2008-04-08','Canadian','Brian Moore',     '+1-555-0110','moore@mail.com',          1,'2025/2026'),
-- Grade 10B
(11,'Noah Martinez',    'STU0011','10','B','Male',  '2008-08-12','Mexican', 'Carlos Martinez', '+251-911-011','mart@mail.com',          2,'2025/2026'),
(12,'Mia Thompson',     'STU0012','10','B','Female','2008-03-22','American','John Thompson',   '+251-911-012','thompson@mail.com',      2,'2025/2026'),
(13,'Ethan Anderson',   'STU0013','10','B','Male',  '2008-07-15','American','Mike Anderson',   '+251-911-013','anderson@mail.com',      2,'2025/2026'),
-- Grade 9A
(14,'Charlotte Taylor', 'STU0014','9','A','Female','2009-01-20','British', 'Paul Taylor',     '+251-911-014','taylor@mail.com',        3,'2025/2026'),
(15,'Mason Jackson',    'STU0015','9','A','Male',  '2009-04-18','American','George Jackson',  '+251-911-015','jackson@mail.com',       3,'2025/2026'),
(16,'Amara Tesfaye',    'STU0016','9','A','Female','2009-09-05','Ethiopian','Tesfaye Kebede', '+251-911-016','tesfaye@mail.com',       3,'2025/2026'),
-- Grade 9B  
(17,'Lucas Haile',      'STU0017','9','B','Male',  '2009-11-11','Ethiopian','Haile Girma',    '+251-911-017','haile@mail.com',         4,'2025/2026'),
(18,'Harper Alemu',     'STU0018','9','B','Female','2009-02-28','Ethiopian','Alemu Bekele',   '+251-911-018','alemu@mail.com',         4,'2025/2026'),
-- Grade 8A
(19,'Aiden Bekele',     'STU0019','8','A','Male',  '2010-06-14','Ethiopian','Bekele Tadesse', '+251-911-019','bekele@mail.com',        5,'2025/2026'),
(20,'Zoe Tadesse',      'STU0020','8','A','Female','2010-08-30','Ethiopian','Tadesse Yonas',  '+251-911-020','tadesse@mail.com',       5,'2025/2026');

-- Parent access links
INSERT INTO `parent_access` (`user_id`,`student_id`) VALUES
(8,1),(9,2),(10,3);

-- Sample Marks
INSERT INTO `student_marks_custom` (`student_id`,`subject_id`,`teacher_id`,`class_id`,`term`,`academic_year`,`criteria_data`,`criteria_max`,`total_mark`,`max_mark`,`percentage`,`grade`,`entered_by`) VALUES
(1,1,1,1,'Term 1','2025/2026','{"Assignment":18,"Mid Exam":22,"Final Exam":45,"Attendance":9}','{"Assignment":20,"Mid Exam":25,"Final Exam":50,"Attendance":10}',94,105,89.52,'A',3),
(1,2,2,1,'Term 1','2025/2026','{"Assignment":15,"Mid Exam":20,"Final Exam":38,"Attendance":8}','{"Assignment":20,"Mid Exam":25,"Final Exam":45,"Attendance":10}',81,100,81.00,'A',4),
(2,1,1,1,'Term 1','2025/2026','{"Assignment":20,"Mid Exam":24,"Final Exam":47,"Attendance":10}','{"Assignment":20,"Mid Exam":25,"Final Exam":50,"Attendance":10}',101,105,96.19,'A+',3),
(2,2,2,1,'Term 1','2025/2026','{"Assignment":19,"Mid Exam":23,"Final Exam":44,"Attendance":10}','{"Assignment":20,"Mid Exam":25,"Final Exam":45,"Attendance":10}',96,100,96.00,'A+',4),
(3,1,1,1,'Term 1','2025/2026','{"Assignment":12,"Mid Exam":17,"Final Exam":35,"Attendance":7}','{"Assignment":20,"Mid Exam":25,"Final Exam":50,"Attendance":10}',71,105,67.62,'B',3);

-- Sample Attendance
INSERT INTO `attendance` (`student_id`,`date`,`status`,`marked_by`) VALUES
(1,'2026-06-01','Present',3),(1,'2026-06-02','Present',3),(1,'2026-06-03','Absent',3),
(1,'2026-06-04','Present',3),(1,'2026-06-05','Late',3),(1,'2026-06-08','Present',3),
(2,'2026-06-01','Present',3),(2,'2026-06-02','Present',3),(2,'2026-06-03','Present',3),
(2,'2026-06-04','Present',3),(2,'2026-06-05','Present',3),(2,'2026-06-08','Excused',3),
(3,'2026-06-01','Absent',3),(3,'2026-06-02','Present',3),(3,'2026-06-03','Present',3);

-- Calendar Events
INSERT INTO `calendar_events` (`title`,`description`,`event_date`,`end_date`,`type`,`color`) VALUES
('Term 1 Start','First day of Term 1','2025-09-01',NULL,'event','#10b981'),
('Mid-Term Exams','Mid-term examination period','2025-10-20','2025-10-25','exam','#f59e0b'),
('Winter Holiday','School holiday break','2025-12-20','2026-01-05','holiday','#ef4444'),
('Term 2 Start','First day of Term 2','2026-01-06',NULL,'event','#10b981'),
('Parent-Teacher Meeting','Semester 1 parent meetings','2026-02-14',NULL,'meeting','#8b5cf6'),
('Final Exams','End of year examinations','2026-05-15','2026-05-30','exam','#f59e0b'),
('Graduation Day','Annual graduation ceremony','2026-06-20',NULL,'graduation','#667eea');

-- Announcements
INSERT INTO `announcements` (`title`,`message`,`created_by`,`target_role`,`priority`) VALUES
('Welcome to 2025/2026 Academic Year!','Dear students and parents, we are excited to welcome you to a new academic year. Please check the calendar for important dates.', 1,'all','normal'),
('Exam Schedule Released','The mid-term examination schedule has been published. Please check the school calendar section for details.',1,'all','high'),
('Parent-Teacher Meeting','All parents are invited to attend the parent-teacher conference on February 14th. Please confirm attendance through the parent portal.',1,'parent','urgent');

-- Sample Conversations & Messages
INSERT INTO `chat_conversations` (`id`,`user1_id`,`user2_id`,`last_message_at`) VALUES
(1,3,8,'2026-06-15 10:30:00'),
(2,4,8,'2026-06-14 14:20:00');

INSERT INTO `chat_messages` (`conversation_id`,`sender_id`,`message`,`is_read`,`created_at`) VALUES
(1,8,'Hello Mr. Wilson, how is John doing in Mathematics?',1,'2026-06-15 09:00:00'),
(1,3,'John is doing very well! He scored 94 in Term 1. Keep encouraging him.',1,'2026-06-15 09:15:00'),
(1,8,'Thank you so much! We are very proud of him.',1,'2026-06-15 09:20:00'),
(1,3,'You are welcome. Please attend the parent meeting on Feb 14th.',0,'2026-06-15 10:30:00'),
(2,4,'Dear Parent, Emma is an exceptional student. Her English is outstanding.',1,'2026-06-14 14:00:00'),
(2,8,'Thank you Ms. Johnson! She works very hard.',0,'2026-06-14 14:20:00');

COMMIT;

-- ============================================
-- PASSWORD FIX (run if upgrading from v1)
-- superadmin123 for admins, teacher123 for teachers, parent123 for parents
-- ============================================
UPDATE `users` SET password='$2y$10$fpdZ3r7xEFHCeAIC0TC3O.O/QTQVRmhio7DmyGbTgml1iguz2zj1u' WHERE role='superadmin';
UPDATE `users` SET password='$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu' WHERE role='teacher';
UPDATE `users` SET password='$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO' WHERE role='parent';
