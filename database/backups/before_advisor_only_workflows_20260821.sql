-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: online_request_system
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
-- Table structure for table `review_workflows`
--

DROP TABLE IF EXISTS `review_workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_workflows` (
  `workflow_id` int(11) NOT NULL AUTO_INCREMENT,
  `document_type_id` int(11) NOT NULL,
  `workflow_name` varchar(150) NOT NULL,
  `version_no` int(11) NOT NULL DEFAULT 1,
  `workflow_status` enum('draft','active','inactive') NOT NULL DEFAULT 'draft',
  `revision_return_policy` enum('same_step','first_step') NOT NULL DEFAULT 'same_step',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`workflow_id`),
  UNIQUE KEY `uq_review_workflow_version` (`document_type_id`,`version_no`),
  KEY `idx_review_workflow_status` (`document_type_id`,`workflow_status`),
  KEY `fk_review_workflow_creator` (`created_by`),
  CONSTRAINT `fk_review_workflow_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_workflow_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_type` (`document_type_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_workflows`
--

LOCK TABLES `review_workflows` WRITE;
/*!40000 ALTER TABLE `review_workflows` DISABLE KEYS */;
INSERT INTO `review_workflows` VALUES (6,1,'R.01 - คำร้องทั่วไป',1,'active','same_step',1,'2026-08-19 02:07:45','2026-08-19 02:07:45'),(7,2,'R.12 - คำร้องขอแก้ไขกลุ่มวิชาตามโครงสร้างหลักสูตร',1,'active','same_step',1,'2026-08-19 02:08:14','2026-08-19 02:08:14'),(9,4,'R.18 - คำร้องขอเปิดรายวิชาเรียน',1,'active','same_step',1,'2026-08-19 02:08:39','2026-08-19 02:08:39'),(10,5,'R.24 - คำร้องขอลาป่วย/ลากิจ',1,'active','same_step',1,'2026-08-19 02:08:47','2026-08-19 02:08:47'),(11,8,'INTERNSHIP_INFO - แบบคำร้องขอฝึกประสบการณ์วิชาชีพ ณ สถานประกอบการ',1,'active','same_step',1,'2026-08-19 02:08:57','2026-08-19 02:08:57'),(16,10,'R.14 - อาจารย์ที่ปรึกษาพิจารณา',1,'active','same_step',NULL,'2026-08-21 03:36:17','2026-08-21 03:36:17');
/*!40000 ALTER TABLE `review_workflows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_workflow_steps`
--

DROP TABLE IF EXISTS `review_workflow_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_workflow_steps` (
  `workflow_step_id` int(11) NOT NULL AUTO_INCREMENT,
  `workflow_id` int(11) NOT NULL,
  `step_order` smallint(5) unsigned NOT NULL,
  `role_code` varchar(60) NOT NULL,
  `step_name` varchar(150) NOT NULL,
  `reviewer_source` enum('student_advisor','document_teacher','office_position','employee') NOT NULL,
  `reviewer_employee_id` int(11) DEFAULT NULL,
  `reviewer_position_id` int(11) DEFAULT NULL,
  `scope_type` enum('department','faculty','university') NOT NULL DEFAULT 'department',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`workflow_step_id`),
  UNIQUE KEY `uq_review_workflow_step_order` (`workflow_id`,`step_order`),
  UNIQUE KEY `uq_review_workflow_role` (`workflow_id`,`role_code`),
  KEY `idx_review_step_employee` (`reviewer_employee_id`),
  KEY `idx_review_step_position` (`reviewer_position_id`),
  CONSTRAINT `fk_review_step_employee` FOREIGN KEY (`reviewer_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_step_position` FOREIGN KEY (`reviewer_position_id`) REFERENCES `positions` (`position_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_review_step_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `review_workflows` (`workflow_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_workflow_steps`
--

LOCK TABLES `review_workflow_steps` WRITE;
/*!40000 ALTER TABLE `review_workflow_steps` DISABLE KEYS */;
INSERT INTO `review_workflow_steps` VALUES (11,6,1,'step_1','อาจารย์ที่ปรึกษา','document_teacher',NULL,NULL,'department',1,'2026-08-19 02:07:45'),(12,6,2,'step_2','ประธานหลักสูตร','office_position',NULL,24,'department',1,'2026-08-19 02:07:45'),(13,6,3,'step_3','หัวหน้าสาขา','office_position',NULL,21,'department',1,'2026-08-19 02:07:45'),(14,7,1,'step_1','อาจารย์ที่ปรึกษา','document_teacher',NULL,NULL,'department',1,'2026-08-19 02:08:14'),(15,7,2,'step_2','ประธานหลักสูตร','office_position',NULL,24,'department',1,'2026-08-19 02:08:14'),(17,9,1,'step_1','อาจารย์ที่ปรึกษา','student_advisor',NULL,NULL,'department',1,'2026-08-19 02:08:39'),(18,9,2,'step_2','อาจารย์ผู้รับผิดชอบรายวิชา','document_teacher',NULL,NULL,'department',1,'2026-08-19 02:08:39'),(19,9,3,'step_3','หัวหน้าสาขา','office_position',NULL,21,'department',1,'2026-08-19 02:08:39'),(20,10,1,'step_1','อาจารย์ที่ปรึกษา','student_advisor',NULL,NULL,'department',1,'2026-08-19 02:08:47'),(21,10,2,'step_2','อาจารย์ผู้รับผิดชอบรายวิชา','document_teacher',NULL,NULL,'department',1,'2026-08-19 02:08:47'),(22,10,3,'step_3','หัวหน้าสาขา','office_position',NULL,21,'department',1,'2026-08-19 02:08:47'),(23,11,1,'step_1','อาจารย์ที่ปรึกษา','student_advisor',NULL,NULL,'department',1,'2026-08-19 02:08:57'),(24,11,2,'step_2','อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ','office_position',NULL,31,'department',1,'2026-08-19 02:08:57'),(33,16,1,'advisor','อาจารย์ที่ปรึกษา','student_advisor',NULL,NULL,'department',1,'2026-08-21 03:36:17');
/*!40000 ALTER TABLE `review_workflow_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_review_processes`
--

DROP TABLE IF EXISTS `document_review_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_review_processes` (
  `process_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `workflow_id` int(11) NOT NULL,
  `workflow_version_no` int(11) NOT NULL,
  `current_step_order` smallint(5) unsigned DEFAULT NULL,
  `process_status` enum('pending','in_review','revision_requested','approved','cancelled') NOT NULL DEFAULT 'pending',
  `revision_count` int(10) unsigned NOT NULL DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`process_id`),
  UNIQUE KEY `uq_document_review_process` (`document_id`),
  KEY `idx_review_process_workflow` (`workflow_id`),
  KEY `idx_review_process_status` (`process_status`,`current_step_order`),
  CONSTRAINT `fk_review_process_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_review_process_workflow` FOREIGN KEY (`workflow_id`) REFERENCES `review_workflows` (`workflow_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_review_processes`
--

LOCK TABLES `document_review_processes` WRITE;
/*!40000 ALTER TABLE `document_review_processes` DISABLE KEYS */;
INSERT INTO `document_review_processes` VALUES (6,12,11,1,1,'in_review',0,'2026-08-19 02:15:52',NULL,'2026-08-19 02:15:52');
/*!40000 ALTER TABLE `document_review_processes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_review_step_instances`
--

DROP TABLE IF EXISTS `document_review_step_instances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_review_step_instances` (
  `document_step_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `process_id` bigint(20) unsigned NOT NULL,
  `source_workflow_step_id` int(11) DEFAULT NULL,
  `step_order` smallint(5) unsigned NOT NULL,
  `role_code` varchar(60) NOT NULL,
  `step_name` varchar(150) NOT NULL,
  `reviewer_employee_id` int(11) DEFAULT NULL,
  `reviewer_user_id` int(11) DEFAULT NULL,
  `step_status` enum('waiting','current','revision_requested','approved','skipped') NOT NULL DEFAULT 'waiting',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `activated_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`document_step_id`),
  UNIQUE KEY `uq_document_review_step_order` (`process_id`,`step_order`),
  KEY `idx_document_step_reviewer` (`reviewer_user_id`,`step_status`),
  KEY `idx_document_step_employee` (`reviewer_employee_id`),
  KEY `idx_document_step_source` (`source_workflow_step_id`),
  CONSTRAINT `fk_document_step_employee` FOREIGN KEY (`reviewer_employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_document_step_process` FOREIGN KEY (`process_id`) REFERENCES `document_review_processes` (`process_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_document_step_source` FOREIGN KEY (`source_workflow_step_id`) REFERENCES `review_workflow_steps` (`workflow_step_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_document_step_user` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_review_step_instances`
--

LOCK TABLES `document_review_step_instances` WRITE;
/*!40000 ALTER TABLE `document_review_step_instances` DISABLE KEYS */;
INSERT INTO `document_review_step_instances` VALUES (11,6,23,1,'step_1','อาจารย์ที่ปรึกษา',8,5,'current',1,'2026-08-19 09:15:52',NULL,'2026-08-19 02:15:52','2026-08-19 02:15:52'),(12,6,24,2,'step_2','อาจารย์ประสานงานฝึกประสบการณ์วิชาชีพ',11,8,'waiting',0,NULL,NULL,'2026-08-19 02:15:52','2026-08-19 02:15:52');
/*!40000 ALTER TABLE `document_review_step_instances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_events`
--

DROP TABLE IF EXISTS `document_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_events` (
  `event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `document_step_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_document_event_document` (`document_id`,`created_at`),
  KEY `idx_document_event_step` (`document_step_id`,`created_at`),
  KEY `idx_document_event_actor` (`actor_user_id`,`created_at`),
  KEY `idx_document_event_type` (`event_type`,`created_at`),
  CONSTRAINT `fk_document_event_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_document_event_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_document_event_step` FOREIGN KEY (`document_step_id`) REFERENCES `document_review_step_instances` (`document_step_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_events`
--

LOCK TABLES `document_events` WRITE;
/*!40000 ALTER TABLE `document_events` DISABLE KEYS */;
INSERT INTO `document_events` VALUES (1,12,11,NULL,'activated','เริ่มขั้นตอนพิจารณา','2026-08-19 02:15:52'),(2,1,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(3,2,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(4,3,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(5,4,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(6,5,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(7,6,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(8,7,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(9,8,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(10,9,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(11,10,NULL,18,'created','นำเข้าประวัติเอกสารเดิม','2026-08-01 14:33:55'),(12,6,NULL,18,'submitted','เอกสารถูกส่งก่อนปรับปรุงระบบประวัติ','2026-08-01 14:33:55'),(13,10,NULL,18,'submitted','เอกสารถูกส่งก่อนปรับปรุงระบบประวัติ','2026-08-01 14:33:55'),(14,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-01 17:29:46'),(15,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-01 17:29:50'),(16,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-01 17:33:08'),(17,9,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-02 09:54:04'),(18,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-02 09:54:12'),(19,9,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-02 10:23:41'),(20,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-02 10:27:12'),(21,13,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-02 10:53:18'),(22,13,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-02 11:44:20'),(23,13,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-02 11:44:25'),(24,14,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-02 11:46:56'),(25,9,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-02 11:50:53'),(26,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-02 11:50:59'),(27,13,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-02 11:51:27'),(28,13,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-02 11:51:33'),(29,13,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-02 12:25:29'),(30,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-03 09:02:50'),(31,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-03 09:05:52'),(32,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-03 10:58:01'),(33,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-03 11:05:48'),(34,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-03 11:05:58'),(35,12,NULL,18,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-03 11:06:21'),(36,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-04 09:51:22'),(37,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-04 10:06:42'),(38,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 05:01:41'),(39,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 05:01:52'),(41,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 07:02:05'),(42,13,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 07:12:26'),(43,13,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-09 07:16:16'),(47,16,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-09 09:11:01'),(49,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-09 09:31:04'),(50,16,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 09:51:50'),(51,16,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 10:31:21'),(52,16,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-09 10:34:02'),(53,18,NULL,24,'created','สร้างแบบร่างเอกสาร','2026-08-11 06:31:47'),(54,19,NULL,24,'created','สร้างแบบร่างเอกสาร','2026-08-11 06:32:03'),(56,21,NULL,24,'created','สร้างแบบร่างเอกสาร','2026-08-11 06:35:36'),(58,14,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-11 06:44:18'),(59,13,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 06:44:36'),(60,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 06:47:34'),(63,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-11 07:08:09'),(64,14,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 07:09:12'),(66,16,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 07:10:46'),(67,23,NULL,24,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-11 08:17:35'),(68,23,NULL,24,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-11 08:17:57'),(69,23,NULL,24,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-11 08:19:16'),(70,23,NULL,24,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-11 08:40:55'),(71,23,NULL,24,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-11 08:41:17'),(72,23,NULL,24,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-11 08:41:39'),(73,23,NULL,24,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-11 08:41:58'),(74,12,NULL,18,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-11 08:43:39'),(75,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-11 08:47:55'),(76,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-11 08:47:58'),(77,12,NULL,18,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-11 08:48:16'),(78,24,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-11 10:50:25'),(79,9,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-11 10:53:40'),(80,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 11:04:05'),(81,8,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-11 11:15:35'),(82,9,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-11 11:24:59'),(83,9,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 11:25:04'),(84,13,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-11 11:35:53'),(85,13,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 11:35:59'),(86,13,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-11 11:41:46'),(92,25,NULL,25,'created','สร้างแบบร่างเอกสาร','2026-08-11 15:02:03'),(93,12,NULL,18,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-11 15:32:27'),(94,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-11 15:32:40'),(95,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-11 15:32:42'),(96,12,NULL,18,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-11 15:32:57'),(97,23,NULL,24,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-11 15:33:34'),(98,23,NULL,24,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-11 15:33:49'),(99,23,NULL,24,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-11 15:33:52'),(100,23,NULL,24,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-11 15:34:21'),(101,12,NULL,18,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-11 15:34:36'),(102,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-11 15:34:38'),(103,12,NULL,18,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-11 15:34:47'),(105,28,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-12 08:25:59'),(106,28,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 08:29:52'),(107,28,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 08:31:34'),(108,28,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 08:38:36'),(109,28,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-12 08:42:08'),(110,28,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-12 08:55:59'),(111,28,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-12 09:07:48'),(112,29,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-12 09:42:43'),(113,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 09:53:42'),(114,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 09:55:02'),(115,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 10:09:31'),(116,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 10:42:31'),(117,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 10:57:33'),(118,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 11:00:41'),(119,29,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-12 11:23:55'),(120,29,NULL,18,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-12 11:25:07'),(121,30,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-12 12:17:46'),(122,31,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-12 12:17:52'),(123,32,NULL,26,'created','สร้างแบบร่างเอกสาร','2026-08-14 03:02:15'),(124,32,NULL,26,'submitted','ส่งเอกสารเพื่อดำเนินการ','2026-08-14 03:07:21'),(125,12,NULL,18,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-14 04:31:29'),(126,12,NULL,18,'saved','บันทึกแบบร่างข้อมูลสหกิจ','2026-08-14 04:31:54'),(127,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-15 07:54:47'),(128,12,NULL,18,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-15 07:55:05'),(129,13,NULL,23,'revision_requested','อาจารย์ส่งกลับให้แก้ไข: แก้มาใหม่','2026-08-16 08:43:59'),(130,10,NULL,23,'revision_requested','อาจารย์ส่งกลับให้แก้ไข: แก้ข้อมูลมาใหม่','2026-08-16 08:48:53'),(131,31,NULL,18,'saved','บันทึกแบบร่างเอกสาร','2026-08-16 08:52:59'),(132,12,NULL,18,'saved','นักศึกษาเปิดเอกสารเพื่อแก้ไขและส่งใหม่','2026-08-19 02:15:28'),(133,12,NULL,18,'saved','เตรียมเอกสารสำหรับพิมพ์และลงชื่อ','2026-08-19 02:15:34'),(134,12,NULL,18,'submitted','อัปโหลด RMUTI_Pro-Ex_02 ฉบับลงชื่อแล้ว','2026-08-19 02:15:52'),(283,36,NULL,18,'created','สร้างแบบร่างเอกสาร','2026-08-21 03:59:48');
/*!40000 ALTER TABLE `document_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `document_status` enum('draft','submitted','in_review','revision_requested','teacher_reviewed','approved','cancel') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `fk_documents_student` (`student_id`),
  KEY `fk_documents_template` (`template_id`),
  CONSTRAINT `fk_documents_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_documents_template` FOREIGN KEY (`template_id`) REFERENCES `document_template` (`template_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (1,1,1,'draft','2026-07-30 08:38:07','2026-07-30 08:38:07',NULL),(2,1,1,'draft','2026-07-30 08:45:59','2026-07-30 08:45:59',NULL),(3,1,1,'draft','2026-07-30 08:47:03','2026-07-30 08:47:03',NULL),(4,1,1,'draft','2026-07-30 08:50:06','2026-07-30 08:50:06',NULL),(5,1,1,'draft','2026-07-30 09:18:35','2026-07-30 09:18:35',NULL),(6,1,1,'submitted','2026-07-30 09:29:36','2026-07-30 09:30:58','2026-07-30 11:30:58'),(7,1,1,'draft','2026-07-30 09:34:40','2026-07-30 09:34:40',NULL),(8,1,1,'draft','2026-07-30 09:41:12','2026-07-30 09:41:12',NULL),(9,1,1,'submitted','2026-07-30 10:08:24','2026-08-11 11:25:04','2026-08-11 18:25:04'),(10,1,1,'revision_requested','2026-07-30 10:15:06','2026-08-16 08:48:53','2026-07-30 12:41:13'),(12,1,5,'in_review','2026-08-01 17:29:46','2026-08-19 02:15:52','2026-08-19 09:15:52'),(13,1,2,'revision_requested','2026-08-02 10:53:18','2026-08-16 08:43:59','2026-08-11 18:41:46'),(14,1,2,'submitted','2026-08-02 11:46:56','2026-08-11 07:09:12','2026-08-11 14:09:12'),(16,1,8,'submitted','2026-08-09 09:11:01','2026-08-11 07:10:46','2026-08-11 14:10:46'),(18,2,1,'draft','2026-08-11 06:31:47','2026-08-11 06:31:47',NULL),(19,2,2,'draft','2026-08-11 06:32:03','2026-08-11 06:32:03',NULL),(21,2,8,'draft','2026-08-11 06:35:36','2026-08-11 06:35:36',NULL),(23,2,5,'submitted','2026-08-11 08:17:35','2026-08-11 15:34:21','2026-08-11 22:34:21'),(24,1,2,'draft','2026-08-11 10:50:25','2026-08-11 10:50:25',NULL),(25,3,1,'draft','2026-08-11 15:02:03','2026-08-11 15:02:03',NULL),(28,1,8,'submitted','2026-08-12 08:25:59','2026-08-12 09:07:48','2026-08-12 16:07:48'),(29,1,9,'submitted','2026-08-12 09:42:43','2026-08-12 11:25:07','2026-08-12 18:25:07'),(30,1,8,'draft','2026-08-12 12:17:46','2026-08-12 12:17:46',NULL),(31,1,9,'draft','2026-08-12 12:17:52','2026-08-12 12:17:52',NULL),(32,4,1,'submitted','2026-08-14 03:02:15','2026-08-14 03:07:21','2026-08-14 10:07:21'),(36,1,10,'draft','2026-08-21 03:59:48','2026-08-21 03:59:48',NULL);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` text NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notification_recipient` (`recipient_user_id`,`is_read`,`created_at`),
  KEY `idx_notification_document` (`document_id`),
  CONSTRAINT `fk_notification_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (7,18,13,'revision_requested','คำร้องถูกส่งกลับให้แก้ไข','คำร้อง R.12 ต้องแก้ไข: แก้มาใหม่','/Project/student/document_form.php?document=13&from=history',0,'2026-08-16 08:43:59',NULL),(8,18,10,'revision_requested','คำร้องถูกส่งกลับให้แก้ไข','คำร้อง R.01 ต้องแก้ไข: แก้ข้อมูลมาใหม่','/Project/student/document_form.php?document=10&from=history',0,'2026-08-16 08:48:53',NULL),(44,5,12,'workflow_step_assigned','มีคำร้องรอการพิจารณา','คำร้อง INTERNSHIP_INFO เลขที่ #12 รอพิจารณาในขั้น “อาจารย์ที่ปรึกษา”','/Project/teacher/request_review.php?document=12',0,'2026-08-19 02:15:52',NULL);
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-21 13:10:31
