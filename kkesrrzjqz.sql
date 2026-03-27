-- Adminer 4.7.8 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(32) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_roles` (`id`, `slug`, `name`, `description`, `created_at`) VALUES
(1,	'superadmin',	'Super Admin',	'Full system access including user management',	'2026-03-27 08:34:28'),
(2,	'admin',	'Admin',	'Manage organisation, users and all data',	'2026-03-27 08:34:28'),
(3,	'manager',	'Manager',	'Create and manage projects and participants',	'2026-03-27 08:34:28'),
(4,	'viewer',	'Viewer',	'Read-only access to all data',	'2026-03-27 08:34:28')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `slug` = VALUES(`slug`), `name` = VALUES(`name`), `description` = VALUES(`description`), `created_at` = VALUES(`created_at`);

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `role` enum('owner','admin','viewer') NOT NULL DEFAULT 'admin',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `email`, `role`, `status`, `last_login`, `created_at`) VALUES
(1,	'admin',	'$2y$12$PaFwrxO6ogwu7B3Vdg781OfY.Y4lQDGQKlJ/458oPXGpq.gW1PX6e',	'admin@yourdomain.com',	'owner',	'active',	'2026-03-27 08:38:59',	'2026-03-18 06:10:50')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `username` = VALUES(`username`), `password_hash` = VALUES(`password_hash`), `email` = VALUES(`email`), `role` = VALUES(`role`), `status` = VALUES(`status`), `last_login` = VALUES(`last_login`), `created_at` = VALUES(`created_at`);

DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `api_key` varchar(100) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `last_used` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_key` (`api_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `api_keys` (`id`, `api_key`, `label`, `status`, `last_used`, `created_at`) VALUES
(1,	'swp_live_d5fe4719dba48b14ca87680ffe4c5fb0',	'Default API Key',	'revoked',	NULL,	'2026-03-18 06:10:50'),
(2,	'swp_live_afa06120ac6596e23d44293ed09ac77c',	'Regenerated',	'revoked',	NULL,	'2026-03-18 06:22:50'),
(3,	'swp_live_dfb0d57895f153225999e76b69c788db',	'Regenerated',	'active',	NULL,	'2026-03-18 06:24:02')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `api_key` = VALUES(`api_key`), `label` = VALUES(`label`), `status` = VALUES(`status`), `last_used` = VALUES(`last_used`), `created_at` = VALUES(`created_at`);

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `form_config`;
CREATE TABLE `form_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `form_name` varchar(200) NOT NULL DEFAULT 'Entry Form',
  `description` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_id` (`project_id`),
  CONSTRAINT `form_config_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `form_config` (`id`, `project_id`, `form_name`, `description`, `updated_at`) VALUES
(1,	1,	'Demo Entry Form',	'Fill in your details to spin the wheel and win!',	'2026-03-18 06:10:50'),
(3,	3,	'Certification',	'Fill in your details to spin the wheel!',	'2026-03-20 06:36:00'),
(5,	4,	'TESTTEST Entry Form',	'Fill in your details to spin the wheel!',	'2026-03-19 08:02:19')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `project_id` = VALUES(`project_id`), `form_name` = VALUES(`form_name`), `description` = VALUES(`description`), `updated_at` = VALUES(`updated_at`);

DROP TABLE IF EXISTS `form_options`;
CREATE TABLE `form_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int(10) unsigned NOT NULL,
  `option_text` varchar(300) NOT NULL,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_question` (`question_id`),
  CONSTRAINT `form_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `form_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `form_questions`;
CREATE TABLE `form_questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `question_text` varchar(500) NOT NULL,
  `field_type` enum('short','paragraph','email','radio','checkbox','dropdown','file','rating','date','ranking') NOT NULL DEFAULT 'short',
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `form_questions_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `form_questions` (`id`, `project_id`, `question_text`, `field_type`, `is_required`, `sort_order`, `created_at`) VALUES
(1,	1,	'Full Name',	'short',	1,	1,	'2026-03-18 06:10:50'),
(2,	1,	'Corporate Email',	'email',	1,	2,	'2026-03-18 06:10:50'),
(3,	1,	'Upload Document',	'file',	0,	3,	'2026-03-18 06:10:50'),
(7,	3,	'Customer Name',	'short',	1,	0,	'2026-03-27 07:25:09'),
(8,	3,	'Customer Email',	'email',	1,	0,	'2026-03-27 07:25:20')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `project_id` = VALUES(`project_id`), `question_text` = VALUES(`question_text`), `field_type` = VALUES(`field_type`), `is_required` = VALUES(`is_required`), `sort_order` = VALUES(`sort_order`), `created_at` = VALUES(`created_at`);

DROP TABLE IF EXISTS `org_groups`;
CREATE TABLE `org_groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `level_label` varchar(100) DEFAULT NULL COMMENT 'Admin-defined label e.g. Theatre, Cluster, Region',
  `parent_id` int(10) unsigned DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6366f1',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `org_groups_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `org_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `org_groups` (`id`, `name`, `slug`, `level_label`, `parent_id`, `color`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1,	'EMEA Theatre',	'emea-theatre',	'EMEA',	NULL,	'#6366f1',	0,	'active',	'2026-03-20 05:37:41',	'2026-03-20 05:37:41'),
(2,	'EMERGING',	'emerging',	'EMERGING',	1,	'#6366f1',	0,	'active',	'2026-03-20 05:37:59',	'2026-03-20 05:37:59'),
(3,	'Middle East Region',	'middle-east-region',	'MIddle East',	2,	'#6366f1',	0,	'active',	'2026-03-20 05:38:16',	'2026-03-20 05:38:16'),
(4,	'Africa Region',	'africa-region',	'Africa',	2,	'#6366f1',	0,	'active',	'2026-03-20 05:38:33',	'2026-03-20 05:38:33'),
(5,	'East Africa',	'east-africa',	'East Africa',	4,	'#f2ed64',	0,	'active',	'2026-03-26 11:32:45',	'2026-03-26 11:32:45'),
(6,	'EMEA Central',	'emea-central',	'EMEA Central',	1,	'#e164f2',	0,	'active',	'2026-03-26 11:33:58',	'2026-03-26 11:33:58')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `name` = VALUES(`name`), `slug` = VALUES(`slug`), `level_label` = VALUES(`level_label`), `parent_id` = VALUES(`parent_id`), `color` = VALUES(`color`), `sort_order` = VALUES(`sort_order`), `status` = VALUES(`status`), `created_at` = VALUES(`created_at`), `updated_at` = VALUES(`updated_at`);

DROP TABLE IF EXISTS `participants`;
CREATE TABLE `participants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `result_id` int(10) unsigned DEFAULT NULL,
  `result_name` varchar(200) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `spun_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_spun_at` (`spun_at`),
  CONSTRAINT `participants_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `participants` (`id`, `project_id`, `result_id`, `result_name`, `ip_address`, `user_agent`, `spun_at`) VALUES
(1,	3,	7,	'50% discount',	'49.36.78.120',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-18 09:59:55'),
(2,	3,	8,	'Try Again',	'49.36.78.120',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-18 10:58:32'),
(3,	3,	7,	'50% discount',	'219.91.220.160',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-19 07:47:17'),
(4,	3,	7,	'50% discount',	'219.91.220.160',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-19 07:47:32'),
(5,	3,	7,	'50% discount',	'219.91.220.160',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-19 07:47:46'),
(6,	3,	8,	'Try Again',	'219.91.220.160',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-19 07:48:49'),
(7,	1,	4,	'Try Again',	'219.91.220.160',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-19 07:54:00'),
(8,	3,	8,	'Try Again',	'219.91.220.160',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',	'2026-03-19 09:42:35'),
(9,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 09:01:00'),
(10,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 09:38:47'),
(11,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 09:45:57'),
(12,	3,	10,	'One Day Leave',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 09:46:29'),
(13,	3,	9,	'20 USD Voucher',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 09:47:16'),
(14,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 09:48:31'),
(15,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:04:47'),
(16,	3,	12,	'Couple Dinner',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:05:48'),
(17,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:07:27'),
(18,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:23:55'),
(19,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:24:17'),
(20,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:29:01'),
(21,	3,	7,	'10 USD Voucher',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:29:18'),
(22,	3,	10,	'One Day Leave',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 10:59:57'),
(23,	3,	8,	'Try Again',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 11:01:01'),
(24,	3,	12,	'Couple Dinner',	'203.88.145.78',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 11:02:28'),
(25,	3,	10,	'One Day Leave',	'49.36.77.141',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 12:15:01'),
(26,	3,	8,	'Try Again',	'49.36.77.141',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 12:15:35'),
(27,	3,	7,	'10 USD Voucher',	'49.36.77.141',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 12:16:26'),
(28,	3,	12,	'Couple Dinner',	'122.170.51.44',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-20 17:59:23'),
(29,	3,	10,	'One Day Leave',	'122.170.51.44',	'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/146.0.7680.40 Mobile/15E148 Safari/604.1',	'2026-03-21 05:45:01'),
(30,	3,	7,	'10 USD Voucher',	'122.170.51.44',	'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/146.0.7680.40 Mobile/15E148 Safari/604.1',	'2026-03-21 05:45:16'),
(31,	3,	8,	'Try Again',	'122.170.51.44',	'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/146.0.7680.40 Mobile/15E148 Safari/604.1',	'2026-03-21 05:45:28'),
(32,	3,	8,	'Try Again',	'122.170.51.44',	'Mozilla/5.0 (iPhone; CPU iPhone OS 26_2_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/146.0.7680.151 Mobile/15E148 Safari/604.1',	'2026-03-21 09:36:28'),
(33,	3,	8,	'Try Again',	'219.91.213.201',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-25 07:57:55'),
(34,	3,	12,	'Couple Dinner',	'49.36.77.26',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-25 08:55:11'),
(35,	3,	8,	'Try Again',	'123.201.95.39',	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',	'2026-03-27 07:26:02')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `project_id` = VALUES(`project_id`), `result_id` = VALUES(`result_id`), `result_name` = VALUES(`result_name`), `ip_address` = VALUES(`ip_address`), `user_agent` = VALUES(`user_agent`), `spun_at` = VALUES(`spun_at`);

DROP TABLE IF EXISTS `participant_answers`;
CREATE TABLE `participant_answers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned NOT NULL,
  `answer_text` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_id` (`question_id`),
  KEY `idx_participant` (`participant_id`),
  CONSTRAINT `participant_answers_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participant_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `form_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `participant_answers` (`id`, `participant_id`, `question_id`, `answer_text`, `file_path`, `file_name`) VALUES
(7,	7,	1,	'Raj',	NULL,	NULL),
(8,	7,	2,	'raj@graciasit.com',	NULL,	NULL),
(9,	7,	3,	'photo-1531685250784-7569952593d2.jpg',	'88fe6a47f2f7afd4d9b34fa65ffd81ba_1773906840.jpg',	'photo-1531685250784-7569952593d2.jpg'),
(63,	35,	7,	'Manage IT',	NULL,	NULL),
(64,	35,	8,	'vinit@sophos.com',	NULL,	NULL)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `participant_id` = VALUES(`participant_id`), `question_id` = VALUES(`question_id`), `answer_text` = VALUES(`answer_text`), `file_path` = VALUES(`file_path`), `file_name` = VALUES(`file_name`);

DROP TABLE IF EXISTS `platform_settings`;
CREATE TABLE `platform_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('blocked_domains_custom',	'gmail.com,yahoo.com,hotmail.com,outlook.com,live.com,icloud.com,me.com,aol.com,mail.com,protonmail.com,proton.me,yandex.com,gmx.com,zoho.com,fastmail.com,tutanota.com,msn.com,rocketmail.com',	'2026-03-20 05:49:22'),
('max_projects',	'20',	'2026-03-18 06:10:50'),
('max_spins_month',	'10000',	'2026-03-18 06:10:50'),
('plan_expires',	'2026-12-31',	'2026-03-18 06:10:50'),
('tenant_company',	'TESTING COMPANY',	'2026-03-18 08:18:14'),
('tenant_domain',	'spinthewheel.windsit.com',	'2026-03-19 08:00:27'),
('tenant_plan',	'pro',	'2026-03-18 06:10:50'),
('timezone',	'Asia/Kolkata',	'2026-03-19 13:04:22')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`), `setting_value` = VALUES(`setting_value`), `updated_at` = VALUES(`updated_at`);

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6366f1',
  `token` varchar(60) NOT NULL,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `group_id` int(10) unsigned DEFAULT NULL,
  `spin_duration_ms` int(10) unsigned NOT NULL DEFAULT 5000 COMMENT 'Wheel spin animation duration in ms (3000-30000)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_status` (`status`),
  KEY `idx_group` (`group_id`),
  CONSTRAINT `fk_proj_group` FOREIGN KEY (`group_id`) REFERENCES `org_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `projects` (`id`, `name`, `description`, `color`, `token`, `status`, `group_id`, `spin_duration_ms`, `created_at`, `updated_at`) VALUES
(1,	'Demo Campaign',	'Checking on Description',	'#6366f1',	'demo',	'active',	4,	5000,	'2026-03-18 06:10:50',	'2026-03-20 05:39:14'),
(3,	'Let\'s Have Some Fun',	'testing',	'#5fcdfc',	'test-it',	'active',	4,	5000,	'2026-03-18 08:30:28',	'2026-03-20 06:41:54'),
(4,	'TESTTEST',	'sdfsdf',	'#f29d64',	'testtest',	'active',	NULL,	5000,	'2026-03-19 08:02:19',	'2026-03-19 13:51:42')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `name` = VALUES(`name`), `description` = VALUES(`description`), `color` = VALUES(`color`), `token` = VALUES(`token`), `status` = VALUES(`status`), `group_id` = VALUES(`group_id`), `spin_duration_ms` = VALUES(`spin_duration_ms`), `created_at` = VALUES(`created_at`), `updated_at` = VALUES(`updated_at`);

DROP TABLE IF EXISTS `wheel_options`;
CREATE TABLE `wheel_options` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `probability` decimal(6,2) NOT NULL DEFAULT 0.00,
  `color` varchar(20) NOT NULL DEFAULT '#6366f1',
  `text_color` varchar(20) NOT NULL DEFAULT '#FFFFFF',
  `success_msg` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `wheel_options_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `wheel_options` (`id`, `project_id`, `name`, `probability`, `color`, `text_color`, `success_msg`, `status`, `sort_order`, `created_at`) VALUES
(1,	1,	'Grand Prize',	15.00,	'#6366f1',	'#fff',	'🏆 You won the Grand Prize! We\'ll be in touch within 24hrs.',	'active',	1,	'2026-03-18 06:10:50'),
(2,	1,	'Silver Award',	25.00,	'#10b981',	'#fff',	'🥈 Congratulations! Silver Award is yours. Check your email.',	'active',	2,	'2026-03-18 06:10:50'),
(3,	1,	'Gift Voucher',	30.00,	'#f59e0b',	'#000',	'🎁 You\'ve won a Gift Voucher! Code sent to your email.',	'active',	3,	'2026-03-18 06:10:50'),
(4,	1,	'Try Again',	30.00,	'#475569',	'#fff',	'Better luck next time! Watch out for future campaigns.',	'active',	4,	'2026-03-18 06:10:50'),
(7,	3,	'10 USD Voucher',	10.00,	'#6366f1',	'#ffffff',	'You WON 10 USD voucher',	'active',	0,	'2026-03-18 08:31:01'),
(8,	3,	'Try Again',	60.00,	'#a5123e',	'#ffffff',	'Sorry Better luck next time',	'active',	0,	'2026-03-18 08:31:22'),
(9,	3,	'20 USD Voucher',	8.00,	'#9e9b47',	'#ffffff',	'You Won 20 USD Voucher',	'active',	0,	'2026-03-20 06:23:22'),
(10,	3,	'One Day Leave',	10.00,	'#b962cb',	'#ffffff',	'Won One Day Leave',	'active',	0,	'2026-03-20 06:23:56'),
(11,	3,	'SPA Voucher',	2.00,	'#f264b0',	'#ffffff',	'RELAX and ENJOY UR SPA',	'active',	0,	'2026-03-20 06:24:24'),
(12,	3,	'Couple Dinner',	10.00,	'#66b28f',	'#ffffff',	'ROMANTIC CANDLE LIGHT DINNER',	'active',	0,	'2026-03-20 06:24:53')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `project_id` = VALUES(`project_id`), `name` = VALUES(`name`), `probability` = VALUES(`probability`), `color` = VALUES(`color`), `text_color` = VALUES(`text_color`), `success_msg` = VALUES(`success_msg`), `status` = VALUES(`status`), `sort_order` = VALUES(`sort_order`), `created_at` = VALUES(`created_at`);

-- 2026-03-27 10:15:49
