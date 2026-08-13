# Academic Evaluation System - Module List

A high-level functional overview of all core modules in the Academic Evaluation System.

---

## 1. Role-Based Authentication & User Management
Handles user authentication, role-based access control, account administration, and profile settings.
* **Supported Roles**: Admin, Dean, Program Head, Department Head, Faculty, Student, Staff
* **Key Features**:
  * Role-based route protection & dynamic post-login dashboard routing
  * Consolidated user administration panels: `/admin/employees` (Deans, Program Heads, Department Heads, Faculty, Staff, Admin) and `/admin/students` (Students)
  * Formatted full names (`Last Name, First Name Middle Name Suffix`) with `suffix` database column support
  * Self-account protection preventing active logged-in admin (`auth()->id()`) from self-disabling or self-deletion (`YOU` badge)
  * System safeguard preventing the deactivation or deletion of the last remaining active Administrator account
  * User profile, security password updates, and interface theme settings

---

## 2. Academic Structure & Catalog Management
Manages institutional hierarchy, curriculum catalogs, academic periods, and class allocations.
* **Key Features**:
  * **Dedicated Departments Management Page (`/admin/departments`)**: Features 4 metric cards (5px `#800000` dark red border, `<x-odometer>`), Leader assignment filter (`Assigned Leader`, `Unassigned Leader`), search, sorting modes, and `<flux:dropdown>` actions for department editing and deletion. Supports both Academic Departments (managed by Program Heads) and Administrative Departments (managed by Department Heads). Automatic bidirectional sync with `/admin/employees`.
  * **Dedicated Academic Programs Management Page (`/admin/programs`)**: Features 4 metric cards, Department filter, Program Head filter, search, sorting modes, and `<flux:dropdown>` actions
  * Academic Year & Semester scheduling with active-term toggles and schedule removal safeguards
  * Subject catalog management (`/admin/subjects`) with grouped actions
  * Class section management (`/admin/classes`) linking subjects, assigned faculty, sections, and programs

---

## 3. Questionnaire & Evaluation Settings
Allows administrators to configure evaluation instruments, rating criteria, question banks, and evaluation schedules.
* **Key Features**:
  * **Evaluation Weight Score Card**: Configures dynamic max score point targets for all 7 evaluation types (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`), calculating real-time percentage distribution weights with color-coded progress bars and balance indicators
  * **4-Section Vertical Dashboard**: Structured layout separating System Access Banner, 2-Column Schedule Grid, Evaluation Weight Score Card, and Questionnaire Parts Breakdown
  * **Evaluation Criteria**: Categorizes evaluation areas (Teaching Effectiveness, Classroom Management, Professionalism, Communication)
  * **Question Bank**: Rating-scale questions assigned to specific criteria

---

## 4. Evaluation Execution & Asynchronous Processing Engine
Facilitates evaluation forms for students, peers, deans, staff, and program heads with automated background processing.
* **Key Features**:
  * **Single-Question Interactive Wizard UX**: Focused 1-question-per-step wizard (`evaluation-form.blade.php`) featuring a real-time progress header (`X/11 Answered • X%`), interactive question navigator pills grid with completion checkmarks, 300ms smooth auto-advance upon selecting a rating, enlarged high-contrast horizontal rating buttons (`1`–`5`) with theme-tuned hover states, a stylized rating scale legend, and a final **Summary & Review** step with criterion score matrix, skipped question alerts, and live profanity-filtered comments.
  * **Real-time Profanity Filtering**: Live debounced sanitation removing curse words from comments with constructive toast warnings.
  * **Asynchronous Background Processing Queue**: Dispatches `ProcessEvaluationSubmission` queue job to calculate numerical scores, calculate idempotency averages, and trigger AI sentiment analysis.

---

## 5. AI-Powered Sentiment Analysis & NLP Engine
Integrates a Python Flask microservice pipeline to analyze textual feedback comments and train machine learning models.
* **Key Features**:
  * **VADER Lexicon Engine**: Enhanced with Tagalog/Taglish negations and custom evaluation lexicons
  * **Decision Tree ML Classifier**: Combines TF-IDF text features with numerical ratings to predict sentiment (`Positive`, `Neutral`, `Negative`)
  * **Data Quality & Agreement Gate**: Automatically filters conflicting feedback samples
  * **AI Admin Operations**: Real-time training triggers, confusion matrix metrics, and manual label verification

---

## 6. Evaluation Oversight & Operations
Provides real-time administrative supervision over active and past evaluation cycles.
* **Key Features**:
  * **Completion Tracking Monitoring Dashboard (`/manage-evaluations`)**: Multi-perspective monitoring dashboard featuring 4 metric cards (Total Submissions, Student Progress %, Supervisor Ratings %, Self Appraisals Done), tabs (**Student Upward Progress**, **Supervisor & Executive Ratings**, **Self Appraisals**), search, department filter, completion status filter, and reminder broadcast
  * Real-time metrics: Total Employees, Total Students, Current Evaluation Progress (expected sum formula), and Pending Submissions odometer counter
  * Evaluation Period Status panel with clear status banners, explicit date-time windows, real-time schedule indicator badges, and direct action triggers
  * Sentiment feedback overview aggregated across all evaluators (Students, Faculty, Deans, Program Heads, Staff)
  * Strict role-relationship anonymized submission log stream (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`)
  * Multi-level filtering by Department, Program, Class Section, and Evaluation Type

---

## 7. Analytics, Results & Rankings
Translates quantitative ratings and qualitative sentiment insights into visual dashboards, institutional leaderboards, and exportable summaries.
* **Key Features**:
  * **Institutional Performance Rankings Page (`/rankings`)**: Dedicated leaderboard page featuring 4 metric cards (Top Performing Faculty, Highest Rated Department, Faculty Monitored, Institutional Mean Rating), **Faculty Leaderboard** (with 🥇, 🥈, 🥉 medals, composite rating, performance badges), **Department Leaderboard** (ranked department averages), search, department filter, and sort modes
  * **Individual Results**: Score breakdowns per faculty member, criteria metrics, and sentiment distribution
  * **Executive Analytics**: Visual performance trends, rating distribution charts, and department comparisons
* **Report Generator (`/admin/reports`)**: Printable and exportable performance evaluation summaries featuring table-less executive scorecards, visual criteria progress bars (`4.50 / 5.0`), integrated **AI Sentiment & Insights Blocks** (positive/neutral/constructive sentiment distribution + automated narrative analysis), submitted comments cards streams, and **Faculty Performance Grid Cards** (replacing traditional data tables) with full single-page `window.print()` document export formatting.

---

## 8. Role-Specific Dashboards
Tailored executive and operational dashboards customized per user role:
* **Admin Dashboard**: System-wide statistics, user metrics, and quick action panels
* **Dean Dashboard**: College/Department evaluation progress and faculty performance overviews
* **Program Head Dashboard**: Program-level section tracking and faculty feedback
* **Faculty Dashboard**: Personal evaluation scores, student feedback summaries, and sentiment metrics
* **Student Dashboard**: Active evaluations list, completion status, and evaluation forms
* **Staff Dashboard**: Staff-specific portal tools and active schedules

---

## 9. Notification System
Alerts users regarding active evaluation periods, pending tasks, and system announcements.
* **Key Features**:
  * Unread alert badges and deadline reminders
  * System notification center

---

## 10. Audit Logging & Activity Tracking
Tracks system activities, batch operations, and record modifications for compliance and governance.
* **Key Features**:
  * Detailed audit logs of system actions
  * Event tracking and security history
