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

### 2. Academic Structure & Catalog Management
Manages institutional hierarchy, curriculum catalogs, academic periods, and class allocations.
* **Key Features**:
  * **Dedicated Departments Management Page (`/admin/departments`)**: Features 4 metric cards (5px `#9b0000` dark red border, `<x-odometer>`), Head assignment filter (`Assigned Head`, `Unassigned Head`), search, sorting modes, and clean column layout (`Department Name`, `Department Type`, `Head`, `Members`, `Actions`). Supports both Academic Departments and Administrative Departments with automatic bidirectional sync with `/admin/employees`.
  * **Dedicated Academic Programs Management Page (`/admin/programs`)**: Features 4 metric cards, Department filter, Program Head filter, search, sorting modes, and `<flux:dropdown>` actions.
  * **Subject Catalog Management (`/admin/subjects`)**: Includes **Download Template** (CSV), **Export Subjects** (CSV), and **Import Subjects** supporting CSV and Excel spreadsheets with header format validation and duplicate checking.
  * **Class Section Management (`/admin/classes`)**: Links subjects, assigned faculty, sections, schedules, rooms, and programs.
  * Responsive table styling (`w-full min-w-[700px]` to `min-w-[850px]`) with smooth horizontal scrolling across mobile and full width on desktop.

---

## 3. Questionnaire & Evaluation Settings
Allows administrators to configure evaluation instruments, rating criteria, question banks, and evaluation schedules.
* **Key Features**:
  * **Evaluation Weight Score Card & Dynamic Target Points**: Configures dynamic max score point targets with **report-specific tab switching** (`Individual Teaching Effectiveness` 40% Student / 20% Dean / 20% Program Head / 15% Peer / 5% Self, `Administrative Staff 360°`, and `All Categories Global Targets`), real-time percentage distribution weights, color-coded progress bars, and balance indicators.
  * **Quick Navigation & Modern Stacked Architecture**: Features a static quick jump anchor navigation bar linking directly to `#schedule-section`, `#weights-section`, and `#academic-periods-section` across a vertically stacked card hierarchy (`max-w-7xl mx-auto space-y-8`).
  * **Academic Periods Management Table**: Dedicated paginated table (10 per page) with unified **Add Academic Period** modal for creating and configuring academic years, semester terms, and evaluation windows.
  * **Evaluation Criteria**: Categorizes evaluation areas (Teaching Effectiveness, Classroom Management, Professionalism, Communication).
  * **Question Bank**: Rating-scale questions assigned to specific criteria with plain-English parts categorization for Department Head, Peer, and Superior evaluations.

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
  * **Completion Tracking Monitoring Dashboard (`/manage-evaluations`)**: Multi-perspective monitoring dashboard featuring 4 metric cards, dedicated tracking tabs across **all 7 standardized categories** (`Student`, `Dean`, `Program Head`, `Department Head`, `Peer`, `Supervisor`, `Self`), search, department filter, role filter, completion status filter, and reminder broadcast logging.
  * Real-time metrics: Total Employees, Total Students, Current Evaluation Progress (expected sum formula), and Pending Submissions odometer counter.
  * Evaluation Period Status panel with clear status banners, explicit date-time windows, real-time schedule indicator badges, and direct action triggers.
  * Sentiment feedback overview aggregated across all evaluators (Students, Faculty, Deans, Program Heads, Staff).
  * Strict role-relationship anonymized submission log stream (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`).
  * Multi-level filtering by Department, Program, Class Section, and Evaluation Type.

---

## 7. Analytics, Results & Reports
Translates quantitative ratings and qualitative sentiment insights into visual dashboards, institutional leaderboards, and official print-ready summaries.
* **Key Features**:
  * **Evaluation Results Directory (`/evaluation-results`)**: Paginated directory (10 per page) displaying `Full Name`, `Role`, `Department`, `Total Submissions`, and `Status` (Complete / Incomplete). Features multi-criteria filtering by Role (Dean, Program Head, Department Head, Professor, Staff, Student), Department, Semester, and Search, with a detailed modal breaking down received ratings, submitted evaluations, and evaluator comments.
  * **Actionable Evaluation Summary Report (`/reports` - Summary Tab)**: Executive dashboard with KPI cards (Institutional Average, Student Average, Total Submissions, Faculty Evaluated), **Faculty Requiring Attention** (<3.50 rating / $\ge 30\%$ negative sentiment flags with AI-identified root cause), **Turnout & Data Confidence Rates** (<60% low turnout warnings), **Prescriptive Recommendations**, **Rating Distribution Spread**, and ranked **Academic Department Leaderboard** with **Save as PDF** (`window.print()`) export.
  * **Official GRC Summary of Faculty Performance Evaluation on Teaching Effectiveness (`/reports` - Individual Tab)**: Exact 2-page print-ready replica of the official Global Reciprocal Colleges (GRC) evaluation document with 360-degree Peer Evaluation integration (40% Student, 20% Dean, 20% Program Head, 15% Peer, 5% Self $\rightarrow$ 200 Max Scale), GRC Legend, Overall Rating Box, tripartite signatures, and AI qualitative insights page.
  * **Institutional Performance Rankings Page (`/rankings`)**: Leaderboard page with Top Performing Faculty, Highest Rated Department, and ranked tables.

---

## 8. Role-Specific Dashboards
Tailored executive and operational dashboards customized per user role:
* **Admin Dashboard**: System-wide statistics, user metrics, **Chart.js visual analytics** (Ratings Distribution & Academic Department Average Comparison), recent submissions feed, and quick action panels.
* **Dean Dashboard**: College/Department evaluation progress and faculty performance overviews
* **Program Head Dashboard**: Program-level section tracking and faculty feedback
* **Faculty Dashboard**: Personal evaluation scores, student feedback summaries, and sentiment metrics
* **Student Dashboard**: Active evaluations list, completion status, and evaluation forms
* **Staff Dashboard**: Staff-specific portal tools and active schedules

---

## 9. Notification System
Alerts users regarding active evaluation periods, pending evaluation tasks, and system announcements.
* **Key Features**:
  * **Interactive Navbar Notification Hub (`livewire:notification-dropdown`)**: Livewire component embedded directly in the top header navbar with real-time unread counter badge (`9+` / `new`), smooth scrollable notification feed, and unread accent indicators.
  * **Read All Action**: One-click action to mark all notifications as read and clear unread badges without leaving the current screen.
  * **Individual Removal & Clear All**: Hover dismiss button (`✕`) to remove specific notifications and a "Clear all" button to dismiss all current notices with database persistence (`users.dismissed_notifications`).
  * Dynamic role-based notification calculation for students, faculty, deans, program heads, and department heads.

---

## 10. Audit Logging & Activity Tracking
Tracks system activities, batch operations, and record modifications for compliance and governance.
* **Key Features**:
  * Detailed audit logs of system actions
  * Event tracking and security history
