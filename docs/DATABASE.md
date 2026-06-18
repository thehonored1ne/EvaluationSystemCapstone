# Database Documentation

This document describes the database schema and architecture for the **Evaluation System**.

## Database Type
- **Engine**: SQLite
- **Connection**: `sqlite` (configured in `.env`)

---

## 1. Core Users & Authentication

### `users`
Accounts used for system authentication.
- `id` (INT, PK, Auto Increment)
- `name` (VARCHAR)
- `email` (VARCHAR, Unique)
- `student_id` (INT, FK -> `students.id`, Nullable, Cascade Delete)
- `employee_id` (INT, FK -> `employees.id`, Nullable, Cascade Delete)
- `is_active` (TINYINT, Default: 1)
- `password` (VARCHAR)
- `notifications_last_viewed_at` (DATETIME, Nullable)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `employees`
Dean, Program Head, Faculty Professor, and Staff profiles.
- `id` (INT, PK, Auto Increment)
- `employee_number` (VARCHAR, Unique)
- `first_name` (VARCHAR)
- `last_name` (VARCHAR)
- `middle_name` (VARCHAR, Nullable)
- `role` (VARCHAR) - *e.g., Dean, Program Head, Faculty, Staff*
- `status` (VARCHAR, Default: 'active')
- `department_id` (INT, FK -> `departments.id`, Nullable, Set Null)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `students`
Student profiles enrolled in the system.
- `id` (INT, PK, Auto Increment)
- `student_number` (VARCHAR, Unique)
- `first_name` (VARCHAR)
- `last_name` (VARCHAR)
- `middle_name` (VARCHAR, Nullable)
- `program_id` (INT, FK -> `programs.id`, Nullable, Set Null)
- `year_level` (INT, Nullable)
- `section` (VARCHAR, Nullable)
- `status` (VARCHAR, Default: 'regular')
- `created_at` / `updated_at` (DATETIME, Nullable)

---

## 2. Academic & Department Structure

### `academic_years`
- `id` (INT, PK, Auto Increment)
- `name` (VARCHAR) - *e.g., "2025-2026"*
- `is_active` (TINYINT, Default: 0)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `semesters`
- `id` (INT, PK, Auto Increment)
- `academic_year_id` (INT, FK -> `academic_years.id`, Cascade Delete)
- `name` (VARCHAR) - *e.g., "1st Semester"*
- `is_active` (TINYINT, Default: 0)
- `is_evaluation_open` (TINYINT, Default: 0)
- `student_max_points` (DECIMAL, Default: 90)
- `peer_max_points` (DECIMAL, Default: 50)
- `self_max_points` (DECIMAL, Default: 10)
- `evaluation_starts_at` (DATETIME, Nullable)
- `evaluation_ends_at` (DATETIME, Nullable)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `departments`
- `id` (INT, PK, Auto Increment)
- `name` (VARCHAR)
- `code` (VARCHAR, Unique) - *e.g., "CCS"*
- `dean_id` (INT, FK -> `employees.id`, Nullable, Set Null)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `programs`
- `id` (INT, PK, Auto Increment)
- `name` (VARCHAR)
- `code` (VARCHAR, Unique) - *e.g., "BSCS"*
- `department_id` (INT, FK -> `departments.id`, Cascade Delete)
- `program_head_id` (INT, FK -> `employees.id`, Nullable, Set Null)
- `created_at` / `updated_at` (DATETIME, Nullable)

---

## 3. Subjects, Classes & Enrollments

### `subjects`
- `id` (INT, PK, Auto Increment)
- `code` (VARCHAR, Unique) - *e.g., "CS101"*
- `name` (VARCHAR)
- `description` (TEXT, Nullable)
- `units` (INT, Default: 3)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `classes`
- `id` (INT, PK, Auto Increment)
- `subject_id` (INT, FK -> `subjects.id`, Cascade Delete)
- `semester_id` (INT, FK -> `semesters.id`, Cascade Delete)
- `teacher_id` (INT, FK -> `employees.id`, Cascade Delete)
- `section` (VARCHAR)
- `schedule` (VARCHAR, Nullable)
- `room` (VARCHAR, Nullable)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `class_student` (Pivot)
Many-to-many enrollment mapping students to classes.
- `class_id` (INT, FK -> `classes.id`, Cascade Delete)
- `student_id` (INT, FK -> `students.id`, Cascade Delete)
- `created_at` / `updated_at` (DATETIME, Nullable)
- *Composite Primary Key*: `(class_id, student_id)`

---

## 4. Evaluation Engine

### `evaluation_criteria`
- `id` (INT, PK, Auto Increment)
- `evaluation_type` (VARCHAR) - *e.g., Peer, Upward, Downward, Self*
- `name` (VARCHAR) - *e.g., "Instructional Skills"*
- `order` (INT, Default: 0)
- `max_points` (DECIMAL, Default: 0)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `evaluation_questions`
- `id` (INT, PK, Auto Increment)
- `criterion_id` (INT, FK -> `evaluation_criteria.id`, Cascade Delete)
- `question_text` (TEXT)
- `order` (INT, Default: 0)
- `is_active` (TINYINT, Default: 1)
- `created_at` / `updated_at` (DATETIME, Nullable)

### `evaluations`
Main evaluation submission records.
- `id` (INT, PK, Auto Increment)
- `evaluator_id` (INT, FK -> `users.id`, Cascade Delete)
- `evaluatee_id` (INT, FK -> `users.id`, Cascade Delete)
- `semester_id` (INT, FK -> `semesters.id`, Cascade Delete)
- `class_id` (INT, FK -> `classes.id`, Nullable, Cascade Delete)
- `evaluation_type` (VARCHAR) - *e.g., Peer, Upward, Downward, Self*
- `rating_average` (DECIMAL, Default: 0)
- `comments` (TEXT, Nullable)
- `created_at` / `updated_at` (DATETIME, Nullable)
- *Unique Constraint*: `(semester_id, evaluator_id, evaluatee_id, class_id)`

### `evaluation_answers`
Individual question ratings for each submitted evaluation.
- `id` (INT, PK, Auto Increment)
- `evaluation_id` (INT, FK -> `evaluations.id`, Cascade Delete)
- `question_id` (INT, FK -> `evaluation_questions.id`, Cascade Delete)
- `rating` (INT) - *1 to 5 scale*
- `created_at` / `updated_at` (DATETIME, Nullable)
- *Unique Constraint*: `(evaluation_id, question_id)`

### `evaluation_sentiments`
AI sentiment analysis results associated with evaluation comments.
- `id` (INT, PK, Auto Increment)
- `evaluation_id` (INT, FK -> `evaluations.id`, Unique, Cascade Delete)
- `vader_score` (DECIMAL) - *Polarity score from -1.0 to 1.0*
- `vader_label` (VARCHAR) - *VADER rule-based label (positive, neutral, negative)*
- `dt_label` (VARCHAR) - *Decision Tree classifier predicted label (positive, neutral, negative)*
- `created_at` / `updated_at` (DATETIME, Nullable)

---

## 5. Security & Infrastructure (Built-in)

### Spatie Role-Based Access Control & Auditing
- `roles`: Defined roles (`admin`, `dean`, `program_head`, `faculty`, `student`, `staff`).
- `permissions`: System permissions.
- `model_has_roles` / `model_has_permissions` / `role_has_permissions`: Spatie mapping tables.
- `activity_log`: Table created by Spatie Activitylog to record audited events:
  - `id` (INT, PK, Auto Increment)
  - `log_name` (VARCHAR, Nullable)
  - `description` (TEXT)
  - `subject_type` (VARCHAR, Nullable)
  - `subject_id` (INT, Nullable)
  - `causer_type` (VARCHAR, Nullable)
  - `causer_id` (INT, Nullable)
  - `properties` (TEXT/JSON, Nullable)
  - `batch_uuid` (UUID, Nullable)
  - `event` (VARCHAR, Nullable)
  - `created_at` / `updated_at` (DATETIME, Nullable)

### Queue & Session Operations
- `jobs`: For background processing.
- `failed_jobs` / `job_batches`: Job management.
- `sessions`: Session tracking (Session Driver: `database`).
- `cache` / `cache_locks`: Application cache store (Cache Store: `database`).
- `password_reset_tokens`: Default security tokens.
