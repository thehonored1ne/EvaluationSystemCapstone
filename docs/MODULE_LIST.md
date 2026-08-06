# Academic Evaluation System - Module List

A high-level functional overview of all core modules in the Academic Evaluation System.

---

## 1. Role-Based Authentication & User Management
Handles user authentication, role-based access control, account administration, and profile settings.
* **Supported Roles**: Admin, Dean, Program Head, Faculty, Student, Staff
* **Key Features**:
  * Role-based route protection & dynamic post-login dashboard routing
  * Consolidated user administration panels: `/admin/employees` (Deans, Program Heads, Faculty, Staff, Admin) and `/admin/students` (Students)
  * Formatted full names (`Last Name, First Name Middle Name Suffix`) with `suffix` database column support
  * Self-account protection preventing active logged-in admin (`auth()->id()`) from self-disabling or self-deletion (`YOU` badge)
  * System safeguard preventing the deactivation or deletion of the last remaining active Administrator account
  * User profile, security password updates, and interface theme settings

---

## 2. Academic Structure & Catalog Management
Manages institutional hierarchy, curriculum catalogs, academic periods, and class allocations.
* **Key Features**:
  * Department & Academic Program administration
  * Academic Year & Semester scheduling with active-term toggles
  * Subject catalog management
  * Class section creation (linking subjects, assigned faculty, sections, and programs)

---

## 3. Questionnaire & Evaluation Settings
Allows administrators to configure evaluation instruments, rating criteria, question banks, and evaluation schedules.
* **Key Features**:
  * **Evaluation Criteria**: Categorizes evaluation areas (Teaching Effectiveness, Classroom Management, Professionalism, Communication)
  * **Question Bank**: Rating-scale questions assigned to specific criteria
  * **Evaluation Parameters**: Configures active periods, target evaluator roles, and completion rules

---

## 4. Evaluation Execution & Asynchronous Processing Engine
Facilitates evaluation forms for students, peers, deans, and program heads with automated background processing.
* **Key Features**:
  * Interactive rating forms supporting Likert-scale questions and open-ended feedback comments
  * Asynchronous queue processing to calculate numerical scores and trigger AI sentiment analysis

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
  * Real-time metrics: Total Employees, Total Students, Current Evaluation Progress (expected sum formula), and Pending Submissions odometer counter
  * Evaluation Period Status panel with clear status banners, explicit date-time windows, real-time schedule indicator badges, and direct action triggers
  * Sentiment feedback overview aggregated across all evaluators (Students, Faculty, Deans, Program Heads, Staff)
  * Strict role-relationship anonymized submission log stream (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`)
  * Multi-level filtering by Department, Program, Class Section, and Evaluation Type

---

## 7. Analytics, Results & Report Generation
Translates quantitative ratings and qualitative sentiment insights into visual dashboards and exportable summaries.
* **Key Features**:
  * **Individual Results**: Score breakdowns per faculty member, criteria metrics, and sentiment distribution
  * **Executive Analytics**: Visual performance trends, rating distribution charts, and department comparisons
  * **Report Generator**: Printable and exportable performance evaluation summaries

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
