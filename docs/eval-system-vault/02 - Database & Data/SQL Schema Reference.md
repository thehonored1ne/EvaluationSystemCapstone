---
title: "SQL Schema Reference DDL"
category: "Database & Data"
tags: [database, sql, ddl, schema, tables]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Database Schema]]

# SQL Schema Reference DDL

```sql
-- ========================================================
-- Academic Evaluation System - Complete Database Schema DDL
-- Suitable for DrawSQL (MySQL / SQLite / MariaDB import)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `evaluation_sentiments`;
DROP TABLE IF EXISTS `evaluation_answers`;
DROP TABLE IF EXISTS `evaluations`;
DROP TABLE IF EXISTS `evaluation_questions`;
DROP TABLE IF EXISTS `evaluation_criteria`;
DROP TABLE IF EXISTS `class_student`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `academic_years`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `programs`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `role_has_permissions`;
DROP TABLE IF EXISTS `model_has_permissions`;
DROP TABLE IF EXISTS `model_has_roles`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `jobs`;

-- --------------------------------------------------------
-- Table: employees
-- --------------------------------------------------------
CREATE TABLE `employees` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_number` VARCHAR(255) NOT NULL UNIQUE,
    `first_name` VARCHAR(255) NOT NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `middle_name` VARCHAR(255) NULL,
    `suffix` VARCHAR(255) NULL,
    `role` ENUM('admin', 'dean', 'program head', 'faculty', 'staff') NOT NULL,
    `status` VARCHAR(255) NOT NULL DEFAULT 'active',
    `department_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table: departments
-- --------------------------------------------------------
CREATE TABLE `departments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL UNIQUE,
    `dean_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_departments_dean` FOREIGN KEY (`dean_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
);

-- Add Foreign Key for employees.department_id
ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Table: programs
-- --------------------------------------------------------
CREATE TABLE `programs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(255) NOT NULL UNIQUE,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `program_head_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_programs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_programs_head` FOREIGN KEY (`program_head_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: students
-- --------------------------------------------------------
CREATE TABLE `students` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_number` VARCHAR(255) NOT NULL UNIQUE,
    `first_name` VARCHAR(255) NOT NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `middle_name` VARCHAR(255) NULL,
    `suffix` VARCHAR(255) NULL,
    `program_id` BIGINT UNSIGNED NULL,
    `year_level` INT NULL,
    `section` VARCHAR(255) NULL,
    `status` VARCHAR(255) NOT NULL DEFAULT 'regular',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_students_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `student_id` BIGINT UNSIGNED NULL,
    `employee_id` BIGINT UNSIGNED NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notifications_last_viewed_at` TIMESTAMP NULL,
    `email_verified_at` TIMESTAMP NULL,
    `password` VARCHAR(255) NOT NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_users_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
);

-- --------------------------------------------------------
-- Table: academic_years
-- --------------------------------------------------------
CREATE TABLE `academic_years` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table: semesters
-- --------------------------------------------------------
CREATE TABLE `semesters` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `academic_year_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `is_evaluation_open` TINYINT(1) NOT NULL DEFAULT 0,
    `student_max_points` DECIMAL(5, 2) NOT NULL DEFAULT 90.00,
    `peer_max_points` DECIMAL(5, 2) NOT NULL DEFAULT 50.00,
    `self_max_points` DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
    `evaluation_starts_at` TIMESTAMP NULL,
    `evaluation_ends_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_semesters_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: subjects
-- --------------------------------------------------------
CREATE TABLE `subjects` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(255) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `units` INT NOT NULL DEFAULT 3,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table: classes
-- --------------------------------------------------------
CREATE TABLE `classes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `semester_id` BIGINT UNSIGNED NOT NULL,
    `teacher_id` BIGINT UNSIGNED NOT NULL,
    `section` VARCHAR(255) NOT NULL,
    `schedule` VARCHAR(255) NULL,
    `room` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_classes_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_classes_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: class_student (Pivot)
-- --------------------------------------------------------
CREATE TABLE `class_student` (
    `class_id` BIGINT UNSIGNED NOT NULL,
    `student_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`class_id`, `student_id`),
    CONSTRAINT `fk_class_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_class_student_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: evaluation_criteria
-- --------------------------------------------------------
CREATE TABLE `evaluation_criteria` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `evaluation_type` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `order` INT NOT NULL DEFAULT 0,
    `max_points` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table: evaluation_questions
-- --------------------------------------------------------
CREATE TABLE `evaluation_questions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `criterion_id` BIGINT UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_questions_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `evaluation_criteria` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: evaluations
-- --------------------------------------------------------
CREATE TABLE `evaluations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `evaluator_id` BIGINT UNSIGNED NOT NULL,
    `evaluatee_id` BIGINT UNSIGNED NOT NULL,
    `semester_id` BIGINT UNSIGNED NOT NULL,
    `class_id` BIGINT UNSIGNED NULL,
    `evaluation_type` VARCHAR(255) NOT NULL,
    `rating_average` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `comments` TEXT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `evaluations_unique_submission` UNIQUE (`semester_id`, `evaluator_id`, `evaluatee_id`, `class_id`),
    CONSTRAINT `fk_evaluations_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_evaluations_evaluatee` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_evaluations_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_evaluations_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: evaluation_answers
-- --------------------------------------------------------
CREATE TABLE `evaluation_answers` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `evaluation_id` BIGINT UNSIGNED NOT NULL,
    `question_id` BIGINT UNSIGNED NOT NULL,
    `rating` INT NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `answers_unique_evaluation_question` UNIQUE (`evaluation_id`, `question_id`),
    CONSTRAINT `fk_answers_evaluation` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `evaluation_questions` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: evaluation_sentiments
-- --------------------------------------------------------
CREATE TABLE `evaluation_sentiments` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `evaluation_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `vader_score` DECIMAL(5, 4) NOT NULL,
    `vader_label` VARCHAR(255) NOT NULL,
    `dt_label` VARCHAR(255) NOT NULL,
    `manual_label` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `fk_sentiments_evaluation` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: roles (Spatie)
-- --------------------------------------------------------
CREATE TABLE `roles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `guard_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `roles_name_guard_name_unique` UNIQUE (`name`, `guard_name`)
);

-- --------------------------------------------------------
-- Table: permissions (Spatie)
-- --------------------------------------------------------
CREATE TABLE `permissions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `guard_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    CONSTRAINT `permissions_name_guard_name_unique` UNIQUE (`name`, `guard_name`)
);

-- --------------------------------------------------------
-- Table: model_has_permissions (Spatie)
-- --------------------------------------------------------
CREATE TABLE `model_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
    CONSTRAINT `fk_model_has_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: model_has_roles (Spatie)
-- --------------------------------------------------------
CREATE TABLE `model_has_roles` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `model_id`, `model_type`),
    CONSTRAINT `fk_model_has_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: role_has_permissions (Spatie)
-- --------------------------------------------------------
CREATE TABLE `role_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `role_id`),
    CONSTRAINT `fk_role_has_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_has_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- Table: activity_log
-- --------------------------------------------------------
CREATE TABLE `activity_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `log_name` VARCHAR(255) NULL,
    `description` TEXT NOT NULL,
    `subject_type` VARCHAR(255) NULL,
    `subject_id` BIGINT UNSIGNED NULL,
    `causer_type` VARCHAR(255) NULL,
    `causer_id` BIGINT UNSIGNED NULL,
    `properties` TEXT NULL,
    `batch_uuid` CHAR(36) NULL,
    `event` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table: sessions
-- --------------------------------------------------------
CREATE TABLE `sessions` (
    `id` VARCHAR(255) PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL
);

-- --------------------------------------------------------
-- Table: password_reset_tokens
-- --------------------------------------------------------
CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) PRIMARY KEY,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL
);

-- --------------------------------------------------------
-- Table: jobs
-- --------------------------------------------------------
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL
);

SET FOREIGN_KEY_CHECKS = 1;

```