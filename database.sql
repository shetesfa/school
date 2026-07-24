-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: school_management
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `school_management`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `school_management` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `school_management`;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_label` varchar(20) NOT NULL COMMENT '2025/2026',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (1,'2025/2026','2025-09-01','2026-06-30',1,'2026-06-20 10:21:20');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'Welcome to 2025/2026 Academic Year!','Dear students and parents, we are excited to welcome you to a new academic year. Please check the calendar for important dates.',1,'all','normal',1,'2026-06-20 10:21:23',NULL),(2,'Exam Schedule Released','The mid-term examination schedule has been published. Please check the school calendar section for details.',1,'all','high',1,'2026-06-20 10:21:23',NULL),(3,'Parent-Teacher Meeting','All parents are invited to attend the parent-teacher conference on February 14th. Please confirm attendance through the parent portal.',1,'parent','urgent',1,'2026-06-20 10:21:23',NULL);
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,1,'2026-06-01','Present',NULL,3,'2026-06-20 10:21:23'),(2,1,'2026-06-02','Present',NULL,3,'2026-06-20 10:21:23'),(3,1,'2026-06-03','Absent',NULL,3,'2026-06-20 10:21:23'),(4,1,'2026-06-04','Present',NULL,3,'2026-06-20 10:21:23'),(5,1,'2026-06-05','Late',NULL,3,'2026-06-20 10:21:23'),(6,1,'2026-06-08','Present',NULL,3,'2026-06-20 10:21:23'),(7,2,'2026-06-01','Present',NULL,3,'2026-06-20 10:21:23'),(8,2,'2026-06-02','Present',NULL,3,'2026-06-20 10:21:23'),(9,2,'2026-06-03','Present',NULL,3,'2026-06-20 10:21:23'),(10,2,'2026-06-04','Present',NULL,3,'2026-06-20 10:21:23'),(11,2,'2026-06-05','Present',NULL,3,'2026-06-20 10:21:23'),(12,2,'2026-06-08','Excused',NULL,3,'2026-06-20 10:21:23'),(13,3,'2026-06-01','Absent',NULL,3,'2026-06-20 10:21:23'),(14,3,'2026-06-02','Present',NULL,3,'2026-06-20 10:21:23'),(15,3,'2026-06-03','Present',NULL,3,'2026-06-20 10:21:23');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_events`
--

DROP TABLE IF EXISTS `calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_events`
--

LOCK TABLES `calendar_events` WRITE;
/*!40000 ALTER TABLE `calendar_events` DISABLE KEYS */;
INSERT INTO `calendar_events` VALUES (1,'Term 1 Start','First day of Term 1','2025-09-01',NULL,'event','#10b981',1,NULL,'2026-06-20 10:21:23'),(2,'Mid-Term Exams','Mid-term examination period','2025-10-20','2025-10-25','exam','#f59e0b',1,NULL,'2026-06-20 10:21:23'),(3,'Winter Holiday','School holiday break','2025-12-20','2026-01-05','holiday','#ef4444',1,NULL,'2026-06-20 10:21:23'),(4,'Term 2 Start','First day of Term 2','2026-01-06',NULL,'event','#10b981',1,NULL,'2026-06-20 10:21:23'),(5,'Parent-Teacher Meeting','Semester 1 parent meetings','2026-02-14',NULL,'meeting','#8b5cf6',1,NULL,'2026-06-20 10:21:23'),(6,'Final Exams','End of year examinations','2026-05-15','2026-05-30','exam','#f59e0b',1,NULL,'2026-06-20 10:21:23'),(7,'Graduation Day','Annual graduation ceremony','2026-06-20',NULL,'graduation','#667eea',1,NULL,'2026-06-20 10:21:23');
/*!40000 ALTER TABLE `calendar_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_conversations`
--

DROP TABLE IF EXISTS `chat_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_conversations`
--

LOCK TABLES `chat_conversations` WRITE;
/*!40000 ALTER TABLE `chat_conversations` DISABLE KEYS */;
INSERT INTO `chat_conversations` VALUES (1,3,8,'2026-06-20 10:22:50','2026-06-20 10:21:23'),(2,4,8,'2026-06-14 14:20:00','2026-06-20 10:21:23');
/*!40000 ALTER TABLE `chat_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
INSERT INTO `chat_messages` VALUES (1,1,8,'Hello Mr. Wilson, how is John doing in Mathematics?',NULL,NULL,1,NULL,'2026-06-15 09:00:00'),(2,1,3,'John is doing very well! He scored 94 in Term 1. Keep encouraging him.',NULL,NULL,1,NULL,'2026-06-15 09:15:00'),(3,1,8,'Thank you so much! We are very proud of him.',NULL,NULL,1,NULL,'2026-06-15 09:20:00'),(4,1,3,'You are welcome. Please attend the parent meeting on Feb 14th.',NULL,NULL,1,'2026-06-20 10:22:34','2026-06-15 10:30:00'),(5,2,4,'Dear Parent, Emma is an exceptional student. Her English is outstanding.',NULL,NULL,1,NULL,'2026-06-14 14:00:00'),(6,2,8,'Thank you Ms. Johnson! She works very hard.',NULL,NULL,1,'2026-06-20 10:23:54','2026-06-14 14:20:00'),(7,1,3,'hi',NULL,NULL,1,'2026-06-20 10:22:34','2026-06-20 10:22:21'),(8,1,8,'hi',NULL,NULL,1,'2026-06-20 10:23:08','2026-06-20 10:22:50');
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_rankings`
--

DROP TABLE IF EXISTS `class_rankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_rankings`
--

LOCK TABLES `class_rankings` WRITE;
/*!40000 ALTER TABLE `class_rankings` DISABLE KEYS */;
/*!40000 ALTER TABLE `class_rankings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_subject_teachers`
--

DROP TABLE IF EXISTS `class_subject_teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_subject_teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`class_id`,`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_subject_teachers`
--

LOCK TABLES `class_subject_teachers` WRITE;
/*!40000 ALTER TABLE `class_subject_teachers` DISABLE KEYS */;
INSERT INTO `class_subject_teachers` VALUES (1,1,1,1,'2026-06-20 10:21:21'),(2,1,2,2,'2026-06-20 10:21:21'),(3,1,3,3,'2026-06-20 10:21:21'),(4,1,4,4,'2026-06-20 10:21:21'),(5,1,6,5,'2026-06-20 10:21:21'),(6,2,1,1,'2026-06-20 10:21:21'),(7,2,2,2,'2026-06-20 10:21:21'),(8,2,3,3,'2026-06-20 10:21:21'),(9,3,1,1,'2026-06-20 10:21:21'),(10,3,2,2,'2026-06-20 10:21:21'),(11,3,3,3,'2026-06-20 10:21:21'),(12,4,1,1,'2026-06-20 10:21:21'),(13,4,4,4,'2026-06-20 10:21:21'),(14,5,1,1,'2026-06-20 10:21:21'),(15,5,6,5,'2026-06-20 10:21:21');
/*!40000 ALTER TABLE `class_subject_teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,'10','A',1,NULL,'2026-06-20 10:21:20',1),(2,'10','B',2,NULL,'2026-06-20 10:21:20',1),(3,'9','A',3,NULL,'2026-06-20 10:21:20',1),(4,'9','B',4,NULL,'2026-06-20 10:21:20',1),(5,'8','A',5,NULL,'2026-06-20 10:21:20',1);
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mark_criteria_templates`
--

DROP TABLE IF EXISTS `mark_criteria_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mark_criteria_templates`
--

LOCK TABLES `mark_criteria_templates` WRITE;
/*!40000 ALTER TABLE `mark_criteria_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `mark_criteria_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parent_access`
--

DROP TABLE IF EXISTS `parent_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parent_access` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'parent user id',
  `student_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_student` (`user_id`,`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parent_access`
--

LOCK TABLES `parent_access` WRITE;
/*!40000 ALTER TABLE `parent_access` DISABLE KEYS */;
INSERT INTO `parent_access` VALUES (1,8,1,'2026-06-20 10:21:22'),(2,9,2,'2026-06-20 10:21:22'),(3,10,3,'2026-06-20 10:21:22');
/*!40000 ALTER TABLE `parent_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remember_tokens`
--

LOCK TABLES `remember_tokens` WRITE;
/*!40000 ALTER TABLE `remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `school_settings`
--

DROP TABLE IF EXISTS `school_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `school_settings`
--

LOCK TABLES `school_settings` WRITE;
/*!40000 ALTER TABLE `school_settings` DISABLE KEYS */;
INSERT INTO `school_settings` VALUES (1,'result_visibility','4','2026-06-20 10:21:20'),(2,'current_term','Term 1','2026-06-20 10:21:20'),(3,'current_year','2025/2026','2026-06-20 10:21:20'),(4,'school_name','EduTrack International School','2026-06-20 10:21:20'),(5,'school_address','123 Education Street, City','2026-06-20 10:21:20'),(6,'school_phone','+1-800-SCHOOL','2026-06-20 10:21:20'),(7,'school_email','info@edutrack.edu','2026-06-20 10:21:20'),(8,'marks_lock_global','0','2026-06-20 10:21:20'),(9,'pass_percentage','50','2026-06-20 10:21:20'),(10,'school_motto','Excellence in Education','2026-06-20 10:21:20'),(11,'school_logo','images/logo.png','2026-06-20 10:21:20');
/*!40000 ALTER TABLE `school_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `semester_locks`
--

DROP TABLE IF EXISTS `semester_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `semester_locks`
--

LOCK TABLES `semester_locks` WRITE;
/*!40000 ALTER TABLE `semester_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `semester_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_marks_custom`
--

DROP TABLE IF EXISTS `student_marks_custom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_marks_custom`
--

LOCK TABLES `student_marks_custom` WRITE;
/*!40000 ALTER TABLE `student_marks_custom` DISABLE KEYS */;
INSERT INTO `student_marks_custom` VALUES (1,1,1,1,1,'Term 1','2025/2026','{\"Assignment\":18,\"Mid Exam\":22,\"Final Exam\":45,\"Attendance\":9}','{\"Assignment\":20,\"Mid Exam\":25,\"Final Exam\":50,\"Attendance\":10}',94.00,105.00,89.52,'A',NULL,0,NULL,NULL,3,'2026-06-20 10:21:23','2026-06-20 10:21:23'),(2,1,2,2,1,'Term 1','2025/2026','{\"Assignment\":15,\"Mid Exam\":20,\"Final Exam\":38,\"Attendance\":8}','{\"Assignment\":20,\"Mid Exam\":25,\"Final Exam\":45,\"Attendance\":10}',81.00,100.00,81.00,'A',NULL,0,NULL,NULL,4,'2026-06-20 10:21:23','2026-06-20 10:21:23'),(3,2,1,1,1,'Term 1','2025/2026','{\"Assignment\":20,\"Mid Exam\":24,\"Final Exam\":47,\"Attendance\":10}','{\"Assignment\":20,\"Mid Exam\":25,\"Final Exam\":50,\"Attendance\":10}',101.00,105.00,96.19,'A+',NULL,0,NULL,NULL,3,'2026-06-20 10:21:23','2026-06-20 10:21:23'),(4,2,2,2,1,'Term 1','2025/2026','{\"Assignment\":19,\"Mid Exam\":23,\"Final Exam\":44,\"Attendance\":10}','{\"Assignment\":20,\"Mid Exam\":25,\"Final Exam\":45,\"Attendance\":10}',96.00,100.00,96.00,'A+',NULL,0,NULL,NULL,4,'2026-06-20 10:21:23','2026-06-20 10:21:23'),(5,3,1,1,1,'Term 1','2025/2026','{\"Assignment\":12,\"Mid Exam\":17,\"Final Exam\":35,\"Attendance\":7}','{\"Assignment\":20,\"Mid Exam\":25,\"Final Exam\":50,\"Attendance\":10}',71.00,105.00,67.62,'B',NULL,0,NULL,NULL,3,'2026-06-20 10:21:23','2026-06-20 10:21:23');
/*!40000 ALTER TABLE `student_marks_custom` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,'John Smith','STU0001','10','A','Male','2008-03-15','American',NULL,'Robert Smith','+1-555-0101','parent.smith@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(2,'Emma Johnson','STU0002','10','A','Female','2008-05-22','British',NULL,'David Johnson','+1-555-0102','parent.johnson@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(3,'Michael Brown','STU0003','10','A','Male','2008-07-10','American',NULL,'James Brown','+1-555-0103','parent.brown@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(4,'Sophia Williams','STU0004','10','A','Female','2008-01-30','Canadian',NULL,'Mark Williams','+1-555-0104','williams@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(5,'William Jones','STU0005','10','A','Male','2008-09-14','American',NULL,'Paul Jones','+1-555-0105','jones@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(6,'Olivia Garcia','STU0006','10','A','Female','2008-11-05','Spanish',NULL,'Carlos Garcia','+1-555-0106','garcia@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(7,'James Miller','STU0007','10','A','Male','2008-12-20','American',NULL,'Henry Miller','+1-555-0107','miller@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(8,'Ava Davis','STU0008','10','A','Female','2008-02-18','British',NULL,'Thomas Davis','+1-555-0108','davis@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(9,'Liam Wilson','STU0009','10','A','Male','2008-06-25','American',NULL,'Gary Wilson','+1-555-0109','wilson@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(10,'Isabella Moore','STU0010','10','A','Female','2008-04-08','Canadian',NULL,'Brian Moore','+1-555-0110','moore@mail.com',NULL,NULL,1,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(11,'Noah Martinez','STU0011','10','B','Male','2008-08-12','Mexican',NULL,'Carlos Martinez','+251-911-011','mart@mail.com',NULL,NULL,2,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(12,'Mia Thompson','STU0012','10','B','Female','2008-03-22','American',NULL,'John Thompson','+251-911-012','thompson@mail.com',NULL,NULL,2,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(13,'Ethan Anderson','STU0013','10','B','Male','2008-07-15','American',NULL,'Mike Anderson','+251-911-013','anderson@mail.com',NULL,NULL,2,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(14,'Charlotte Taylor','STU0014','9','A','Female','2009-01-20','British',NULL,'Paul Taylor','+251-911-014','taylor@mail.com',NULL,NULL,3,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(15,'Mason Jackson','STU0015','9','A','Male','2009-04-18','American',NULL,'George Jackson','+251-911-015','jackson@mail.com',NULL,NULL,3,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(16,'Amara Tesfaye','STU0016','9','A','Female','2009-09-05','Ethiopian',NULL,'Tesfaye Kebede','+251-911-016','tesfaye@mail.com',NULL,NULL,3,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(17,'Lucas Haile','STU0017','9','B','Male','2009-11-11','Ethiopian',NULL,'Haile Girma','+251-911-017','haile@mail.com',NULL,NULL,4,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(18,'Harper Alemu','STU0018','9','B','Female','2009-02-28','Ethiopian',NULL,'Alemu Bekele','+251-911-018','alemu@mail.com',NULL,NULL,4,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(19,'Aiden Bekele','STU0019','8','A','Male','2010-06-14','Ethiopian',NULL,'Bekele Tadesse','+251-911-019','bekele@mail.com',NULL,NULL,5,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21'),(20,'Zoe Tadesse','STU0020','8','A','Female','2010-08-30','Ethiopian',NULL,'Tadesse Yonas','+251-911-020','tadesse@mail.com',NULL,NULL,5,'2025/2026','active','2026-06-20 10:21:21','2026-06-20 10:21:21');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'Mathematics','MATH','#ef4444',NULL,'2026-06-20 10:21:20'),(2,'English Language','ENG','#3b82f6',NULL,'2026-06-20 10:21:20'),(3,'Science','SCI','#10b981',NULL,'2026-06-20 10:21:20'),(4,'History','HIS','#f59e0b',NULL,'2026-06-20 10:21:20'),(5,'Geography','GEO','#8b5cf6',NULL,'2026-06-20 10:21:20'),(6,'Computer Science','CS','#06b6d4',NULL,'2026-06-20 10:21:20'),(7,'Physical Education','PE','#f97316',NULL,'2026-06-20 10:21:20'),(8,'Art & Design','ART','#ec4899',NULL,'2026-06-20 10:21:20'),(9,'Amharic','AMH','#84cc16',NULL,'2026-06-20 10:21:20');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES (1,3,1,'+251911001001','BSc Mathematics',NULL,NULL,'2026-06-20 10:21:20'),(2,4,2,'+251911001002','BA English Literature',NULL,NULL,'2026-06-20 10:21:20'),(3,5,3,'+251911001003','BSc Physics',NULL,NULL,'2026-06-20 10:21:20'),(4,6,4,'+251911001004','BA History',NULL,NULL,'2026-06-20 10:21:20'),(5,7,6,'+251911001005','MSc Computer Science',NULL,NULL,'2026-06-20 10:21:20');
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super Admin','admin@school.com','$2y$10$fpdZ3r7xEFHCeAIC0TC3O.O/QTQVRmhio7DmyGbTgml1iguz2zj1u','superadmin','active',0,'en','2026-06-20 10:21:20','2026-06-20 10:22:58'),(2,'School Director','director@school.com','$2y$10$fpdZ3r7xEFHCeAIC0TC3O.O/QTQVRmhio7DmyGbTgml1iguz2zj1u','superadmin','active',0,'en','2026-06-20 10:21:20',NULL),(3,'Mr. James Wilson','james@school.com','$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu','teacher','active',1,'en','2026-06-20 10:21:20','2026-06-20 10:24:04'),(4,'Ms. Sarah Johnson','sarah@school.com','$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu','teacher','active',0,'en','2026-06-20 10:21:20','2026-06-20 10:23:52'),(5,'Mr. Robert Brown','robert@school.com','$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu','teacher','active',0,'en','2026-06-20 10:21:20',NULL),(6,'Ms. Emily Davis','emily@school.com','$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu','teacher','active',0,'en','2026-06-20 10:21:20',NULL),(7,'Mr. Michael Lee','michael@school.com','$2y$10$zWt96kBMfFwsFiKYP50swubwe6XyxIsEsCKFrh3Lc6Tjjgxfr2xMu','teacher','active',0,'en','2026-06-20 10:21:20',NULL),(8,'Parent - Smith','parent.smith@mail.com','$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO','parent','active',0,'en','2026-06-20 10:21:20','2026-06-20 10:22:32'),(9,'Parent - Johnson','parent.johnson@mail.com','$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO','parent','active',0,'en','2026-06-20 10:21:20',NULL),(10,'Parent - Brown','parent.brown@mail.com','$2y$10$i48btu5jobwF.8KiazfGfOUwM7bCaNBZageOTLQPZtbLy70aBpDOO','parent','active',0,'en','2026-06-20 10:21:20',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'school_management'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-25  2:07:05
