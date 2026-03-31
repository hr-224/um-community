-- Database Backup
-- Generated: 2026-02-24 10:56:45
-- Tables: 76

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `active_sessions`;
CREATE TABLE `active_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_session_user` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  CONSTRAINT `active_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `active_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES ('4', '1', '1f745dc53c59ebeb5cb0e8d8f58ac83a2cbe9522eddc563b7e7eefc171385a56', '172.71.194.120', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 10:56:11', '2026-02-24 10:56:11');

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `activity_type_id` int(11) DEFAULT NULL,
  `activity_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_type_id` (`activity_type_id`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_activity_user` (`user_id`,`activity_date`),
  KEY `idx_activity_dept` (`department_id`,`activity_date`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_ibfk_3` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `activity_requirements`;
CREATE TABLE `activity_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `rank_id` int(11) DEFAULT NULL,
  `period_type` enum('weekly','biweekly','monthly') DEFAULT 'weekly',
  `min_hours` decimal(5,2) DEFAULT 0.00,
  `min_activities` int(11) DEFAULT 0,
  `min_points` decimal(7,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `rank_id` (`rank_id`),
  CONSTRAINT `activity_requirements_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_requirements_ibfk_2` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `activity_types`;
CREATE TABLE `activity_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `color` varchar(7) DEFAULT '#6B7280',
  `points_value` decimal(5,2) DEFAULT 1.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `activity_types_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('1', 'Patrol', 'Regular patrol duty', NULL, '🚔', '#3B82F6', '1.00', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('2', 'Traffic Stop', 'Traffic enforcement', NULL, '🚦', '#F59E0B', '0.25', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('3', 'Arrest', 'Suspect arrest', NULL, '🚨', '#EF4444', '0.50', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('4', 'Investigation', 'Criminal investigation', NULL, '🔍', '#8B5CF6', '0.75', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('5', 'Training', 'Training session', NULL, '📚', '#10B981', '1.00', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('6', 'Meeting', 'Department meeting', NULL, '👥', '#6B7280', '0.50', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('7', 'Event', 'Community event', NULL, '🎉', '#EC4899', '1.00', '1', '2026-02-24 08:10:06');
INSERT INTO `activity_types` (`id`, `name`, `description`, `department_id`, `icon`, `color`, `points_value`, `is_active`, `created_at`) VALUES ('8', 'Administrative', 'Administrative duties', NULL, '📋', '#6B7280', '0.50', '1', '2026-02-24 08:10:06');

DROP TABLE IF EXISTS `activity_warnings`;
CREATE TABLE `activity_warnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `required_hours` decimal(5,2) DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  `warning_type` enum('low_activity','no_activity','missed_requirement') DEFAULT 'low_activity',
  `is_excused` tinyint(1) DEFAULT 0,
  `excused_by` int(11) DEFAULT NULL,
  `excused_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `excused_by` (`excused_by`),
  CONSTRAINT `activity_warnings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_warnings_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_warnings_ibfk_3` FOREIGN KEY (`excused_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `admin_notes`;
CREATE TABLE `admin_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `admin_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_notes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `announcement_reads`;
CREATE TABLE `announcement_reads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`announcement_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('info','warning','urgent','maintenance') DEFAULT 'info',
  `target_type` enum('all','department','admins') DEFAULT 'all',
  `target_department_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `starts_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  KEY `target_department_id` (`target_department_id`),
  KEY `idx_announcements_active` (`is_active`,`starts_at`,`expires_at`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`target_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `secret_hash` varchar(255) NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `rate_limit` int(11) DEFAULT 100,
  `is_active` tinyint(1) DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `last_used_ip` varchar(45) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_api_key` (`api_key`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `api_request_log`;
CREATE TABLE `api_request_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_id` int(11) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `request_time` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_log_key` (`api_key_id`),
  KEY `idx_api_log_time` (`request_time`),
  CONSTRAINT `api_request_log_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `application_templates`;
CREATE TABLE `application_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`questions`)),
  `requirements` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `application_templates_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `applicant_name` varchar(100) NOT NULL,
  `applicant_email` varchar(255) NOT NULL,
  `applicant_discord` varchar(100) DEFAULT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`answers`)),
  `status` enum('pending','under_review','interview','approved','denied') DEFAULT 'pending',
  `reviewer_id` int(11) DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `idx_applications_status` (`status`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `application_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('1', '1', 'user_login', 'user', '1', 'User logged in', '50.220.181.210', '2026-02-24 08:10:30');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('2', '1', 'trusted_device_added', 'user', '1', 'Trusted device ID: 1', '50.220.181.210', '2026-02-24 09:08:26');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('3', '1', 'all_alerts_cleared', 'user', '1', 'Cleared 1 security alerts', '50.220.181.210', '2026-02-24 09:08:30');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('4', '1', 'user_login', 'user', '1', 'User logged in', '50.220.181.210', '2026-02-24 09:18:26');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('5', '1', 'user_login', 'user', '1', 'User logged in', '50.220.181.210', '2026-02-24 09:23:43');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('6', '1', 'session_revoke', 'session', '3', 'Revoked user session', '50.220.181.210', '2026-02-24 10:48:00');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('7', '1', 'session_revoke', 'session', '1', 'Revoked user session', '50.220.181.210', '2026-02-24 10:48:06');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('8', '1', 'session_revoke', 'session', '2', 'Revoked user session', '50.220.181.210', '2026-02-24 10:48:08');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('9', '1', 'maintenance_toggle', 'system', '0', 'Maintenance mode: ON', '50.220.181.210', '2026-02-24 10:50:44');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('10', '1', 'user_login', 'user', '1', 'User logged in', '50.220.181.210', '2026-02-24 10:56:11');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `created_at`) VALUES ('11', '1', 'maintenance_toggle', 'system', '0', 'Maintenance mode: OFF', '50.220.181.210', '2026-02-24 10:56:17');

DROP TABLE IF EXISTS `badges`;
CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `color` varchar(20) DEFAULT '#fbbf24',
  `rarity` enum('common','uncommon','rare','epic','legendary') DEFAULT 'common',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `callsigns`;
CREATE TABLE `callsigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `callsign` varchar(20) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `callsign` (`callsign`),
  KEY `department_id` (`department_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `callsigns_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `callsigns_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `callsigns_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `certification_types`;
CREATE TABLE `certification_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `color` varchar(7) DEFAULT '#3B82F6',
  `validity_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `certification_types_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chain_of_command`;
CREATE TABLE `chain_of_command` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position_title` varchar(100) NOT NULL,
  `reports_to` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `reports_to` (`reports_to`),
  CONSTRAINT `chain_of_command_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chain_of_command_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chain_of_command_ibfk_3` FOREIGN KEY (`reports_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `conduct_records`;
CREATE TABLE `conduct_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('commendation','warning','disciplinary','note') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `severity` enum('minor','moderate','major','critical') DEFAULT 'minor',
  `department_id` int(11) DEFAULT NULL,
  `issued_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` date DEFAULT NULL,
  `acknowledged` tinyint(1) DEFAULT 0,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `issued_by` (`issued_by`),
  KEY `idx_conduct_user` (`user_id`,`type`),
  CONSTRAINT `conduct_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conduct_records_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conduct_records_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `custom_field_values`;
CREATE TABLE `custom_field_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field_id` int(11) NOT NULL,
  `entity_type` enum('user','roster') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `field_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_field_entity` (`field_id`,`entity_type`,`entity_id`),
  CONSTRAINT `custom_field_values_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `custom_fields`;
CREATE TABLE `custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field_name` varchar(100) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` enum('text','textarea','number','date','select','checkbox','url') DEFAULT 'text',
  `field_options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`field_options`)),
  `applies_to` enum('user','roster','both') DEFAULT 'both',
  `department_id` int(11) DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `custom_fields_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `department_sops`;
CREATE TABLE `department_sops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `version` varchar(20) DEFAULT '1.0',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `last_updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `created_by` (`created_by`),
  KEY `last_updated_by` (`last_updated_by`),
  CONSTRAINT `department_sops_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `department_sops_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `department_sops_ibfk_3` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `department_statistics`;
CREATE TABLE `department_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `stat_date` date NOT NULL,
  `total_members` int(11) DEFAULT 0,
  `active_members` int(11) DEFAULT 0,
  `on_loa` int(11) DEFAULT 0,
  `inactive_members` int(11) DEFAULT 0,
  `new_joins` int(11) DEFAULT 0,
  `departures` int(11) DEFAULT 0,
  `promotions` int(11) DEFAULT 0,
  `demotions` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dept_date` (`department_id`,`stat_date`),
  CONSTRAINT `department_statistics_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(10) NOT NULL,
  `color` varchar(7) DEFAULT '#3B82F6',
  `icon` varchar(50) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('1', 'Los Santos Police Department', 'LSPD', '#1E40AF', '🚔', NULL, 'Primary law enforcement for Los Santos', '2026-02-24 08:10:06');
INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('2', 'Blaine County Sheriff Office', 'BCSO', '#92400E', '⭐', NULL, 'Law enforcement for Blaine County', '2026-02-24 08:10:06');
INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('3', 'San Andreas State Police', 'SASP', '#1E3A8A', '🚨', NULL, 'State-wide law enforcement', '2026-02-24 08:10:06');
INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('4', 'Fire Department', 'SAFD', '#DC2626', '🚒', NULL, 'Fire and rescue services', '2026-02-24 08:10:06');
INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('5', 'Emergency Medical Services', 'EMS', '#059669', '🚑', NULL, 'Emergency medical response', '2026-02-24 08:10:06');
INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('6', 'Department of Justice', 'DOJ', '#7C2D12', '⚖️', NULL, 'Judicial services', '2026-02-24 08:10:06');
INSERT INTO `departments` (`id`, `name`, `abbreviation`, `color`, `icon`, `logo_path`, `description`, `created_at`) VALUES ('7', 'Communications', 'COMM', '#6B21A8', '📡', NULL, 'Dispatch and communications', '2026-02-24 08:10:06');

DROP TABLE IF EXISTS `discord_webhook_logs`;
CREATE TABLE `discord_webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'General',
  `department_id` int(11) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doc_category` (`category`),
  KEY `department_id` (`department_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `event_rsvps`;
CREATE TABLE `event_rsvps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('attending','maybe','not_attending') DEFAULT 'attending',
  `responded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rsvp` (`event_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `event_rsvps_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_rsvps_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_date` (`event_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `license_info`;
CREATE TABLE `license_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `license_key` varchar(100) NOT NULL,
  `license_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `licensed_domain` varchar(255) DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_canceled` tinyint(1) DEFAULT 0,
  `last_validated_at` timestamp NULL DEFAULT NULL,
  `validation_response` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `license_info` (`id`, `license_key`, `license_id`, `product_name`, `customer_name`, `customer_email`, `customer_id`, `licensed_domain`, `purchased_at`, `expires_at`, `is_active`, `is_canceled`, `last_validated_at`, `validation_response`, `created_at`, `updated_at`) VALUES ('1', 'KWG2-9H3W-T178-1DWA-95Z9', '27', 'FiveM Community Manager', 'HR Designs', 'hr.designs224@gmail.com', '1', 'community.ultmods.com', '2026-02-07 22:51:51', NULL, '1', '0', '2026-02-24 08:12:37', '{\"id\":27,\"name\":\"FiveM Community Manager\",\"itemApp\":\"nexus\",\"itemType\":\"package\",\"itemId\":9,\"customer\":{\"id\":1,\"name\":\"HR Designs\",\"title\":null,\"timeZone\":\"America\\/New_York\",\"formattedName\":\"<span style=\'color:#e4b62f\'>HR Designs<\\/span>\",\"primaryGroup\":{\"id\":4,\"name\":\"Executive Director of Operations\",\"formattedName\":\"<span style=\'color:#e4b62f\'>Executive Director of Operations<\\/span>\"},\"secondaryGroups\":[{\"id\":3,\"name\":\"Members\",\"formattedName\":\"<span style=\'color:#8e7cc3\'>Members<\\/span>\"}],\"email\":\"hr.designs224@gmail.com\",\"joined\":\"2025-05-25T20:43:00Z\",\"registrationIpAddress\":\"98.117.247.212\",\"warningPoints\":0,\"reputationPoints\":1,\"photoUrl\":\"https:\\/\\/ultimate-mods.com\\/uploads\\/monthly_2025_05\\/logo.png.9b11fd46aef867fee108ae91210f7c3d.png\",\"photoUrlIsDefault\":false,\"coverPhotoUrl\":\"https:\\/\\/ultimate-mods.com\\/uploads\\/monthly_2025_05\\/Screenshot_3.png.1e46d590ee4670a007d036458cbf7678.png\",\"profileUrl\":\"https:\\/\\/ultimate-mods.com\\/profile\\/1-hr-designs\\/\",\"validating\":false,\"posts\":10,\"lastActivity\":\"2026-02-24T13:12:17Z\",\"lastVisit\":\"2026-02-23T23:45:28Z\",\"lastPost\":\"2025-07-28T01:41:28Z\",\"birthday\":null,\"profileViews\":1536,\"customFields\":{\"1\":{\"name\":\"Personal Information\",\"fields\":{\"1\":{\"name\":\"About Me\",\"value\":null}}}},\"rank\":{\"id\":2,\"name\":\"Apprentice\",\"icon\":\"https:\\/\\/ultimate-mods.com\\/uploads\\/monthly_2025_05\\/3_Apprentice.svg\",\"points\":90},\"achievements_points\":136,\"allowAdminEmails\":true,\"completed\":true},\"purchased\":\"2026-02-08T03:51:51Z\",\"expires\":null,\"active\":true,\"canceled\":false,\"renewalTerm\":null,\"customFields\":{\"1\":\"community.ultmods.com\"},\"parent\":null,\"show\":true,\"licenseKey\":\"KWG2-9H3W-T178-1DWA-95Z9\",\"image\":\"https:\\/\\/ultimate-mods.com\\/uploads\\/monthly_2026_02\\/background.png.009ab2c73f306861212fe0cbea1cd5e5.png\",\"url\":\"https:\\/\\/ultimate-mods.com\\/clients\\/purchases\\/27-fivem-community-manager\\/\"}', '2026-02-24 08:12:37', '2026-02-24 08:12:37');

DROP TABLE IF EXISTS `loa_requests`;
CREATE TABLE `loa_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `auto_returned` tinyint(1) DEFAULT 0,
  `return_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_loa_dates` (`start_date`,`end_date`,`status`),
  CONSTRAINT `loa_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loa_requests_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `loa_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_la_username` (`username`),
  KEY `idx_la_ip` (`ip_address`),
  KEY `idx_la_attempted` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `failure_reason` varchar(255) DEFAULT NULL,
  `login_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`user_id`),
  KEY `idx_login_date` (`login_at`),
  CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `login_history` (`id`, `user_id`, `ip_address`, `user_agent`, `location`, `success`, `failure_reason`, `login_at`) VALUES ('1', '1', '172.70.43.211', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1', 'Ashburn, Virginia, United States', '1', NULL, '2026-02-24 08:10:30');
INSERT INTO `login_history` (`id`, `user_id`, `ip_address`, `user_agent`, `location`, `success`, `failure_reason`, `login_at`) VALUES ('2', '1', '162.158.159.34', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Newark, New Jersey, United States', '1', NULL, '2026-02-24 09:18:26');
INSERT INTO `login_history` (`id`, `user_id`, `ip_address`, `user_agent`, `location`, `success`, `failure_reason`, `login_at`) VALUES ('3', '1', '172.70.135.117', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1', 'Ashburn, Virginia, United States', '1', NULL, '2026-02-24 09:23:43');
INSERT INTO `login_history` (`id`, `user_id`, `ip_address`, `user_agent`, `location`, `success`, `failure_reason`, `login_at`) VALUES ('4', '1', '172.71.194.120', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Ashburn, Virginia, United States', '1', NULL, '2026-02-24 10:56:11');

DROP TABLE IF EXISTS `mentorship_notes`;
CREATE TABLE `mentorship_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mentorship_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mentorship_id` (`mentorship_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `mentorship_notes_ibfk_1` FOREIGN KEY (`mentorship_id`) REFERENCES `mentorships` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mentorship_notes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mentorships`;
CREATE TABLE `mentorships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainee_id` int(11) NOT NULL,
  `mentor_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trainee_id` (`trainee_id`),
  KEY `mentor_id` (`mentor_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `mentorships_ibfk_1` FOREIGN KEY (`trainee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mentorships_ibfk_2` FOREIGN KEY (`mentor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mentorships_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `message_attachments`;
CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`),
  CONSTRAINT `message_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `is_deleted_sender` tinyint(1) DEFAULT 0,
  `is_deleted_recipient` tinyint(1) DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_messages_recipient` (`recipient_id`,`is_read`),
  KEY `idx_messages_sender` (`sender_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `patrol_logs`;
CREATE TABLE `patrol_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_type` varchar(100) NOT NULL DEFAULT 'Patrol',
  `description` text DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patrol_user` (`user_id`),
  KEY `idx_patrol_date` (`started_at`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `patrol_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patrol_logs_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(100) NOT NULL,
  `permission_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('1', 'apps.view', 'View Applications', 'View submitted applications', 'applications', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('2', 'apps.review', 'Review Applications', 'Approve or deny applications', 'applications', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('3', 'apps.templates.view', 'View Application Templates', 'View application templates', 'applications', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('4', 'apps.templates.manage', 'Manage Application Templates', 'Create, edit, and delete application templates', 'applications', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('5', 'training.view', 'View Training Records', 'View training records', 'training', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('6', 'training.manage', 'Manage Training', 'Create and manage training sessions', 'training', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('7', 'training.certify', 'Issue Certifications', 'Issue and revoke certifications', 'training', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('8', 'training.programs', 'Manage Training Programs', 'Create and manage training programs', 'training', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('9', 'activity.view', 'View Activity Logs', 'View activity logs', 'activity', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('10', 'activity.log', 'Log Activity', 'Log own activity', 'activity', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('11', 'activity.manage', 'Manage Activity', 'Verify and manage activity logs', 'activity', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('12', 'activity.requirements', 'Manage Requirements', 'Set activity requirements', 'activity', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('13', 'roster.view', 'View Roster', 'View department roster', 'roster', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('14', 'roster.manage', 'Manage Roster', 'Add, edit, remove roster entries', 'roster', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('15', 'roster.promote', 'Process Promotions', 'Approve and process promotions', 'roster', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('16', 'dept.view', 'View Department', 'View department information', 'department', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('17', 'dept.manage', 'Manage Department', 'Edit department settings', 'department', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('18', 'dept.ranks', 'Manage Ranks', 'Create and edit ranks', 'department', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('19', 'dept.sops', 'Manage SOPs', 'Create and edit SOPs', 'department', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('20', 'conduct.view', 'View Conduct Records', 'View conduct records', 'conduct', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('21', 'conduct.manage', 'Manage Conduct Records', 'Create and manage conduct records', 'conduct', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('22', 'admin.users', 'Manage Users', 'Manage user accounts', 'admin', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('23', 'admin.settings', 'System Settings', 'Access system settings', 'admin', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('24', 'admin.audit', 'View Audit Log', 'View audit log', 'admin', '2026-02-24 08:10:06');
INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `description`, `category`, `created_at`) VALUES ('25', 'admin.roles', 'Manage Roles', 'Create and assign roles', 'admin', '2026-02-24 08:10:06');

DROP TABLE IF EXISTS `promotion_history`;
CREATE TABLE `promotion_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `from_rank_id` int(11) DEFAULT NULL,
  `to_rank_id` int(11) NOT NULL,
  `change_type` enum('promotion','demotion','lateral','initial') DEFAULT 'promotion',
  `reason` text DEFAULT NULL,
  `effective_date` date NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `promotion_request_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `from_rank_id` (`from_rank_id`),
  KEY `to_rank_id` (`to_rank_id`),
  KEY `processed_by` (`processed_by`),
  KEY `promotion_request_id` (`promotion_request_id`),
  KEY `idx_promo_history_user` (`user_id`),
  CONSTRAINT `promotion_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_history_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_history_ibfk_3` FOREIGN KEY (`from_rank_id`) REFERENCES `ranks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_history_ibfk_4` FOREIGN KEY (`to_rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_history_ibfk_5` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `promotion_history_ibfk_6` FOREIGN KEY (`promotion_request_id`) REFERENCES `promotion_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `promotion_requests`;
CREATE TABLE `promotion_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `current_rank_id` int(11) NOT NULL,
  `requested_rank_id` int(11) NOT NULL,
  `request_type` enum('promotion','demotion','lateral') DEFAULT 'promotion',
  `reason` text NOT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('pending','approved','denied','cancelled') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `current_rank_id` (`current_rank_id`),
  KEY `requested_rank_id` (`requested_rank_id`),
  KEY `requested_by` (`requested_by`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_promotion_user` (`user_id`),
  KEY `idx_promotion_status` (`status`),
  CONSTRAINT `promotion_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_requests_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_requests_ibfk_3` FOREIGN KEY (`current_rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_requests_ibfk_4` FOREIGN KEY (`requested_rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_requests_ibfk_5` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promotion_requests_ibfk_6` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quick_links`;
CREATE TABLE `quick_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT '?',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `quick_links` (`id`, `title`, `url`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'Request LOA', '/user/loa', '📅', '1', '1', '2026-02-24 08:10:06', '2026-02-24 08:10:06');
INSERT INTO `quick_links` (`id`, `title`, `url`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'LOA Calendar', '/user/loa_calendar', '📆', '2', '1', '2026-02-24 08:10:06', '2026-02-24 08:10:06');
INSERT INTO `quick_links` (`id`, `title`, `url`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'Messages', '/user/messages', '✉️', '3', '1', '2026-02-24 08:10:06', '2026-02-24 08:10:06');
INSERT INTO `quick_links` (`id`, `title`, `url`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'Announcements', '/user/announcements', '📢', '4', '1', '2026-02-24 08:10:06', '2026-02-24 08:10:06');

DROP TABLE IF EXISTS `quiz_answers`;
CREATE TABLE `quiz_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quiz_attempt_answers`;
CREATE TABLE `quiz_attempt_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`selected_answers`)),
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `attempt_id` (`attempt_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `quiz_attempt_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempt_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quiz_attempts`;
CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `max_score` int(11) DEFAULT 0,
  `percentage` decimal(5,2) DEFAULT 0.00,
  `passed` tinyint(1) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `time_spent_seconds` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quiz_questions`;
CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','multi_select') DEFAULT 'multiple_choice',
  `points` int(11) DEFAULT 1,
  `explanation` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`),
  CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quizzes`;
CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `certification_type_id` int(11) DEFAULT NULL,
  `training_program_id` int(11) DEFAULT NULL,
  `pass_score` int(11) DEFAULT 70,
  `time_limit_minutes` int(11) DEFAULT NULL,
  `max_attempts` int(11) DEFAULT NULL,
  `shuffle_questions` tinyint(1) DEFAULT 0,
  `show_correct_answers` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `certification_type_id` (`certification_type_id`),
  KEY `training_program_id` (`training_program_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quizzes_ibfk_2` FOREIGN KEY (`certification_type_id`) REFERENCES `certification_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quizzes_ibfk_3` FOREIGN KEY (`training_program_id`) REFERENCES `training_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quizzes_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rank_certification_requirements`;
CREATE TABLE `rank_certification_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rank_id` int(11) NOT NULL,
  `certification_type_id` int(11) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rank_cert` (`rank_id`,`certification_type_id`),
  KEY `certification_type_id` (`certification_type_id`),
  CONSTRAINT `rank_certification_requirements_ibfk_1` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rank_certification_requirements_ibfk_2` FOREIGN KEY (`certification_type_id`) REFERENCES `certification_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rank_requirements`;
CREATE TABLE `rank_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from_rank_id` int(11) NOT NULL,
  `to_rank_id` int(11) NOT NULL,
  `min_days_in_rank` int(11) DEFAULT 0,
  `min_training_hours` decimal(6,2) DEFAULT 0.00,
  `min_activity_hours` decimal(6,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rank_progression` (`from_rank_id`,`to_rank_id`),
  KEY `to_rank_id` (`to_rank_id`),
  CONSTRAINT `rank_requirements_ibfk_1` FOREIGN KEY (`from_rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rank_requirements_ibfk_2` FOREIGN KEY (`to_rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ranks`;
CREATE TABLE `ranks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `rank_name` varchar(100) NOT NULL,
  `rank_order` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `ranks_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('1', '1', 'Chief of Police', '1');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('2', '1', 'Assistant Chief', '2');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('3', '1', 'Commander', '3');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('4', '1', 'Captain', '4');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('5', '1', 'Lieutenant', '5');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('6', '1', 'Sergeant', '6');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('7', '1', 'Corporal', '7');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('8', '1', 'Senior Officer', '8');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('9', '1', 'Officer', '9');
INSERT INTO `ranks` (`id`, `department_id`, `rank_name`, `rank_order`) VALUES ('10', '1', 'Cadet', '10');

DROP TABLE IF EXISTS `read_receipts`;
CREATE TABLE `read_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_type` enum('announcement','sop','document') NOT NULL,
  `content_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`content_type`,`content_id`,`user_id`),
  KEY `idx_content` (`content_type`,`content_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `read_receipts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recognition_awards`;
CREATE TABLE `recognition_awards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `award_type` enum('motm','excellence','dedication','teamwork','custom') NOT NULL,
  `custom_award_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `awarded_by` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `awarded_by` (`awarded_by`),
  CONSTRAINT `recognition_awards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recognition_awards_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recognition_awards_ibfk_3` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `recognition_nominations`;
CREATE TABLE `recognition_nominations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nominee_id` int(11) NOT NULL,
  `nominator_id` int(11) NOT NULL,
  `award_type` enum('motm','excellence','dedication','teamwork','custom') NOT NULL,
  `reason` text NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `nominee_id` (`nominee_id`),
  KEY `nominator_id` (`nominator_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `recognition_nominations_ibfk_1` FOREIGN KEY (`nominee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recognition_nominations_ibfk_2` FOREIGN KEY (`nominator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recognition_nominations_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_perm` (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('3', '1', '1');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('1', '1', '2');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('2', '1', '3');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('7', '2', '5');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('6', '2', '6');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('5', '2', '7');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('4', '2', '13');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('20', '3', '5');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('12', '3', '9');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('11', '3', '11');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('19', '3', '13');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('17', '3', '14');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('18', '3', '15');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('16', '3', '16');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('13', '3', '17');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('14', '3', '18');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('15', '3', '19');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('26', '4', '9');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('29', '4', '13');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('28', '4', '20');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('27', '4', '21');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('35', '5', '9');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('33', '5', '11');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('34', '5', '12');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`) VALUES ('36', '5', '13');

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `role_key` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6B7280',
  `is_system` tinyint(1) DEFAULT 0,
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_key` (`role_key`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `role_name`, `role_key`, `description`, `color`, `is_system`, `department_id`, `created_at`) VALUES ('1', 'Application Reviewer', 'app_reviewer', 'Can review and process applications', '#10B981', '1', NULL, '2026-02-24 08:10:06');
INSERT INTO `roles` (`id`, `role_name`, `role_key`, `description`, `color`, `is_system`, `department_id`, `created_at`) VALUES ('2', 'Field Training Officer', 'fto', 'Can conduct training and issue certifications', '#3B82F6', '1', NULL, '2026-02-24 08:10:06');
INSERT INTO `roles` (`id`, `role_name`, `role_key`, `description`, `color`, `is_system`, `department_id`, `created_at`) VALUES ('3', 'Department Lead', 'dept_lead', 'Can manage department roster and settings', '#8B5CF6', '1', NULL, '2026-02-24 08:10:06');
INSERT INTO `roles` (`id`, `role_name`, `role_key`, `description`, `color`, `is_system`, `department_id`, `created_at`) VALUES ('4', 'Human Resources', 'hr', 'Can manage conduct records and personnel matters', '#EC4899', '1', NULL, '2026-02-24 08:10:06');
INSERT INTO `roles` (`id`, `role_name`, `role_key`, `description`, `color`, `is_system`, `department_id`, `created_at`) VALUES ('5', 'Activity Manager', 'activity_mgr', 'Can verify activity logs and manage requirements', '#F59E0B', '1', NULL, '2026-02-24 08:10:06');

DROP TABLE IF EXISTS `roster`;
CREATE TABLE `roster` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `rank_id` int(11) NOT NULL,
  `badge_number` varchar(20) DEFAULT NULL,
  `callsign` varchar(20) DEFAULT NULL,
  `status` enum('active','loa','inactive') DEFAULT 'active',
  `joined_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `rank_id` (`rank_id`),
  KEY `idx_roster_user` (`user_id`),
  KEY `idx_roster_dept` (`department_id`),
  CONSTRAINT `roster_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `roster_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `roster_ibfk_3` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `scheduled_reports`;
CREATE TABLE `scheduled_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_type` enum('weekly_activity','monthly_activity','cert_expiry') NOT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `security_alerts`;
CREATE TABLE `security_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `alert_type` enum('new_device','new_ip','new_location','suspicious_time','failed_attempts','impossible_travel') NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alert_user` (`user_id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_alert_resolved` (`is_resolved`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `security_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_alerts_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `security_alerts` (`id`, `user_id`, `alert_type`, `severity`, `ip_address`, `user_agent`, `location`, `details`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES ('1', '1', 'new_device', 'medium', '172.70.43.211', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1', 'Ashburn, Virginia, United States', '{\"device\":\"Safari on macOS\",\"ip\":\"172.70.43.211\",\"location\":\"Ashburn, Virginia, United States\"}', '1', '1', '2026-02-24 09:08:30', '2026-02-24 08:10:30');
INSERT INTO `security_alerts` (`id`, `user_id`, `alert_type`, `severity`, `ip_address`, `user_agent`, `location`, `details`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES ('2', '1', 'new_device', 'medium', '162.158.159.34', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Newark, New Jersey, United States', '{\"device\":\"Chrome on Windows 10\\/11\",\"ip\":\"162.158.159.34\",\"location\":\"Newark, New Jersey, United States\"}', '1', '1', '2026-02-24 10:47:47', '2026-02-24 09:18:26');
INSERT INTO `security_alerts` (`id`, `user_id`, `alert_type`, `severity`, `ip_address`, `user_agent`, `location`, `details`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES ('3', '1', 'new_ip', 'low', '172.70.135.117', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1', 'Ashburn, Virginia, United States', '{\"new_ip\":\"172.70.135.117\",\"previous_ip\":\"172.70.43.211\",\"device\":\"Safari on macOS\"}', '1', '1', '2026-02-24 10:47:46', '2026-02-24 09:23:43');
INSERT INTO `security_alerts` (`id`, `user_id`, `alert_type`, `severity`, `ip_address`, `user_agent`, `location`, `details`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES ('4', '1', 'new_ip', 'low', '172.71.194.120', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Ashburn, Virginia, United States', '{\"new_ip\":\"172.71.194.120\",\"previous_ip\":\"162.158.159.34\",\"device\":\"Chrome on Windows 10\\/11\"}', '0', NULL, NULL, '2026-02-24 10:56:11');
INSERT INTO `security_alerts` (`id`, `user_id`, `alert_type`, `severity`, `ip_address`, `user_agent`, `location`, `details`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES ('5', '1', 'new_location', 'medium', '172.71.194.120', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Ashburn, Virginia, United States', '{\"new_location\":\"Ashburn, Virginia, United States\",\"previous_location\":\"Newark, New Jersey, United States\"}', '0', NULL, NULL, '2026-02-24 10:56:11');

DROP TABLE IF EXISTS `shift_signups`;
CREATE TABLE `shift_signups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `signed_up_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_signup` (`shift_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `shift_signups_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_signups_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shifts`;
CREATE TABLE `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_slots` int(11) DEFAULT 0,
  `department_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shift_date` (`shift_date`),
  KEY `department_id` (`department_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shifts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `smtp_settings`;
CREATE TABLE `smtp_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` varchar(255) NOT NULL,
  `smtp_from_email` varchar(255) NOT NULL,
  `smtp_from_name` varchar(255) NOT NULL,
  `smtp_encryption` enum('tls','ssl','none') DEFAULT 'tls',
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sop_acknowledgments`;
CREATE TABLE `sop_acknowledgments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sop_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `acknowledged_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ack` (`sop_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `sop_acknowledgments_ibfk_1` FOREIGN KEY (`sop_id`) REFERENCES `department_sops` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sop_acknowledgments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','color','boolean','number','json') DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('1', 'community_name', 'Gwinnett County RP', 'text', 'Community name displayed across the site', '2026-02-24 08:10:06');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('2', 'primary_color', '#667eea', 'color', 'Primary theme color', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('3', 'secondary_color', '#764ba2', 'color', 'Secondary theme color', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('4', 'accent_color', '#f093fb', 'color', 'Accent color for highlights', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('5', 'background_color_start', '#0f0c29', 'color', 'Background gradient start color', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('6', 'background_color_mid', '#302b63', 'color', 'Background gradient middle color', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('7', 'background_color_end', '#24243e', 'color', 'Background gradient end color', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('8', 'discord_webhook_url', '', 'text', 'Discord webhook URL for admin notifications', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('9', 'discord_webhook_applications_url', '', 'text', 'Discord webhook URL for applications', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('10', 'discord_webhook_enabled', '0', 'boolean', 'Enable Discord webhook notifications', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('11', 'discord_oauth_enabled', '0', 'boolean', 'Enable Discord OAuth login', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('12', 'discord_client_id', '', 'text', 'Discord OAuth client ID', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('13', 'discord_client_secret', '', 'text', 'Discord OAuth client secret', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('14', 'discord_redirect_uri', '', 'text', 'Discord OAuth redirect URI', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('15', 'discord_allow_registration', '1', 'boolean', 'Allow registration via Discord', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('16', 'discord_allow_login', '1', 'boolean', 'Allow login via Discord', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('17', 'discord_require_discord', '0', 'boolean', 'Require Discord account to register', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('18', 'email_notifications_enabled', '1', 'boolean', 'Enable email notifications', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('19', 'auto_loa_return', '1', 'boolean', 'Automatically return users from LOA when end date passes', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('20', 'motm_enabled', '1', 'boolean', 'Enable Member of the Month feature', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('21', 'applications_enabled', '1', 'boolean', 'Enable public department applications', '2026-02-24 08:10:05');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('23', 'community_logo', '/uploads/logos/community_logo_1771938581.png', 'text', NULL, '2026-02-24 08:10:06');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('24', 'maintenance_mode', '0', 'text', NULL, '2026-02-24 10:56:17');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES ('25', 'maintenance_message', 'We are currently performing scheduled maintenance. Please check back shortly.', 'text', NULL, '2026-02-24 10:50:44');

DROP TABLE IF EXISTS `training_programs`;
CREATE TABLE `training_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `certification_type_id` int(11) DEFAULT NULL,
  `required_hours` decimal(5,2) DEFAULT 0.00,
  `max_trainees` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `certification_type_id` (`certification_type_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `training_programs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_programs_ibfk_2` FOREIGN KEY (`certification_type_id`) REFERENCES `certification_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_programs_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `training_records`;
CREATE TABLE `training_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trainee_id` int(11) NOT NULL,
  `trainer_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `certification_type_id` int(11) DEFAULT NULL,
  `session_date` date NOT NULL,
  `hours` decimal(5,2) NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `performance_rating` enum('excellent','good','satisfactory','needs_improvement','unsatisfactory') DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `program_id` (`program_id`),
  KEY `certification_type_id` (`certification_type_id`),
  KEY `idx_training_trainee` (`trainee_id`),
  KEY `idx_training_trainer` (`trainer_id`),
  CONSTRAINT `training_records_ibfk_1` FOREIGN KEY (`trainee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_records_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `training_records_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `training_records_ibfk_4` FOREIGN KEY (`certification_type_id`) REFERENCES `certification_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transfer_requests`;
CREATE TABLE `transfer_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `from_department_id` int(11) NOT NULL,
  `to_department_id` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transfer_status` (`status`),
  KEY `user_id` (`user_id`),
  KEY `from_department_id` (`from_department_id`),
  KEY `to_department_id` (`to_department_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `transfer_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_requests_ibfk_2` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_requests_ibfk_3` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transfer_requests_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `trusted_devices`;
CREATE TABLE `trusted_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_hash` varchar(64) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `first_seen` timestamp NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_trusted` tinyint(1) DEFAULT 0,
  `trust_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_device` (`user_id`,`device_hash`),
  KEY `idx_trusted_user` (`user_id`),
  KEY `idx_trusted_hash` (`device_hash`),
  CONSTRAINT `trusted_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `trusted_devices` (`id`, `user_id`, `device_hash`, `device_name`, `ip_address`, `last_ip`, `location`, `first_seen`, `last_seen`, `is_trusted`, `trust_expires`) VALUES ('1', '1', '27e989edc857bec9121402940bf3d461c039462c363a222e440eb370423a8684', 'Safari on macOS', '172.70.43.211', '172.70.135.117', 'Ashburn, Virginia, United States', '2026-02-24 08:10:30', '2026-02-24 09:23:43', '1', NULL);
INSERT INTO `trusted_devices` (`id`, `user_id`, `device_hash`, `device_name`, `ip_address`, `last_ip`, `location`, `first_seen`, `last_seen`, `is_trusted`, `trust_expires`) VALUES ('2', '1', 'bb993d2067955cee60ac6588ca23a9a5de9cdc3f602a4c1764f374964ec8f6eb', 'Chrome on Windows 10/11', '162.158.159.34', '172.71.194.120', 'Ashburn, Virginia, United States', '2026-02-24 09:18:26', '2026-02-24 10:56:11', '0', NULL);

DROP TABLE IF EXISTS `two_factor_codes`;
CREATE TABLE `two_factor_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `secret` varchar(32) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `backup_codes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `two_factor_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_badges`;
CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `awarded_by` int(11) DEFAULT NULL,
  `awarded_at` timestamp NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  KEY `badge_id` (`badge_id`),
  KEY `awarded_by` (`awarded_by`),
  CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_badges_ibfk_3` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_certifications`;
CREATE TABLE `user_certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `certification_type_id` int(11) NOT NULL,
  `status` enum('pending','in_progress','completed','expired','revoked') DEFAULT 'pending',
  `issued_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `revoked_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `certification_type_id` (`certification_type_id`),
  KEY `issued_by` (`issued_by`),
  KEY `revoked_by` (`revoked_by`),
  KEY `idx_user_certs_user` (`user_id`),
  KEY `idx_user_certs_status` (`status`),
  CONSTRAINT `user_certifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_certifications_ibfk_2` FOREIGN KEY (`certification_type_id`) REFERENCES `certification_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_certifications_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_certifications_ibfk_4` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_email_preferences`;
CREATE TABLE `user_email_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `weekly_activity_report` tinyint(1) DEFAULT 1,
  `monthly_activity_report` tinyint(1) DEFAULT 1,
  `certification_expiry_alerts` tinyint(1) DEFAULT 1,
  `shift_reminders` tinyint(1) DEFAULT 1,
  `event_reminders` tinyint(1) DEFAULT 1,
  `announcement_notifications` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_email_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role_dept` (`user_id`,`role_id`,`department_id`),
  KEY `role_id` (`role_id`),
  KEY `department_id` (`department_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `idx_user_roles_user` (`user_id`),
  CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_security_settings`;
CREATE TABLE `user_security_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_on_new_device` tinyint(1) DEFAULT 1,
  `email_on_new_ip` tinyint(1) DEFAULT 1,
  `email_on_new_location` tinyint(1) DEFAULT 1,
  `email_on_failed_attempts` tinyint(1) DEFAULT 1,
  `require_2fa_new_device` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_security_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_token` (`session_token`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_status`;
CREATE TABLE `user_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `status` enum('online','away','busy','offline') DEFAULT 'offline',
  `custom_status` varchar(100) DEFAULT NULL,
  `last_activity` datetime DEFAULT current_timestamp(),
  `last_seen` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_status_user` (`user_id`),
  CONSTRAINT `user_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_status` (`id`, `user_id`, `status`, `custom_status`, `last_activity`, `last_seen`) VALUES ('1', '1', 'away', '', '2026-02-24 10:56:45', '2026-02-24 08:10:30');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `discord_id` varchar(50) DEFAULT NULL,
  `discord_user_id` varchar(50) DEFAULT NULL,
  `discord_username` varchar(100) DEFAULT NULL,
  `discord_discriminator` varchar(10) DEFAULT NULL,
  `discord_avatar` varchar(255) DEFAULT NULL,
  `discord_email` varchar(255) DEFAULT NULL,
  `discord_access_token` varchar(500) DEFAULT NULL,
  `discord_refresh_token` varchar(500) DEFAULT NULL,
  `discord_token_expires` datetime DEFAULT NULL,
  `discord_linked_at` datetime DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `is_suspended` tinyint(1) DEFAULT 0,
  `suspended_reason` varchar(255) DEFAULT NULL,
  `suspended_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) DEFAULT 0,
  `timezone` varchar(50) DEFAULT 'UTC',
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `email`, `password`, `discord_id`, `discord_user_id`, `discord_username`, `discord_discriminator`, `discord_avatar`, `discord_email`, `discord_access_token`, `discord_refresh_token`, `discord_token_expires`, `discord_linked_at`, `is_admin`, `is_approved`, `is_suspended`, `suspended_reason`, `suspended_at`, `must_change_password`, `timezone`, `reset_token`, `reset_expires`, `created_at`) VALUES ('1', 'HR224', 'hr@ultimate-mods.com', '$2y$10$HAQ5eW3PoSyJUxBYEvIqhuXBFuOB70vXCAlIE6KdWsztXBegCX00y', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '1', '1', '0', NULL, NULL, '0', 'UTC', NULL, NULL, '2026-02-24 08:10:06');

DROP TABLE IF EXISTS `webhook_logs`;
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_type` varchar(50) NOT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `error_message` varchar(500) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_type` (`webhook_type`),
  KEY `idx_webhook_date` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
