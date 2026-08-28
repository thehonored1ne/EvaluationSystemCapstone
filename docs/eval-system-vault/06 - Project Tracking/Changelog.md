---
title: "Project Changelog & Version History"
category: "Project Tracking"
tags: [changelog, versions, history, commits]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Task Roadmap & Todo]]

# Changelog

All notable changes to the **Evaluation System** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [2026-08-26]

### Added, Optimized & Cloud Deployment
- **Cloud Production Deployment (Render + TiDB Cloud Serverless MySQL)**:
  - Containerized full application stack ([`Dockerfile`](file:///c:/Users/USER/Herd/evaluationsystem/Dockerfile), [`docker/supervisord.conf`](file:///c:/Users/USER/Herd/evaluationsystem/docker/supervisord.conf), [`docker/nginx.conf`](file:///c:/Users/USER/Herd/evaluationsystem/docker/nginx.conf), [`docker/entrypoint.sh`](file:///c:/Users/USER/Herd/evaluationsystem/docker/entrypoint.sh)): Nginx, PHP 8.3 FPM, Python Flask AI (`127.0.0.1:5001`), and Laravel queue workers running concurrently in a single lightweight container.
  - Connected to a high-availability **TiDB Cloud Serverless MySQL** database in AWS Singapore (`ap-southeast-1`) with mandatory TLS/SSL certificate verification.
  - Configured reverse-proxy trusted proxies (`$middleware->trustProxies(at: '*')` in [`bootstrap/app.php`](file:///c:/Users/USER/Herd/evaluationsystem/bootstrap/app.php)) and forced HTTPS URL asset scheme ([`AppServiceProvider.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Providers/AppServiceProvider.php)).
- **Database & Seeder Performance Optimization**:
  - Fixed MySQL strict mode enum truncation on `employees` table: Updated migration [`0000_01_01_000001_create_employees_table.php`](file:///c:/Users/USER/Herd/evaluationsystem/database/migrations/0000_01_01_000001_create_employees_table.php) to `$table->string('role', 50)` to accommodate `department head`.
  - Added safe index existence check (`Schema::hasIndex`) to [`2026_08_08_000003_drop_unique_constraint_on_subjects_code.php`](file:///c:/Users/USER/Herd/evaluationsystem/database/migrations/2026_08_08_000003_drop_unique_constraint_on_subjects_code.php).
  - Streamed [`EvaluationPhase2Seeder.php`](file:///c:/Users/USER/Herd/evaluationsystem/database/seeders/EvaluationPhase2Seeder.php) with `AcademicClass::chunk(25)` and periodic `$flushAll()` batch database insertions every 200 evaluations, reducing RAM consumption from **> 500 MB down to < 35 MB** on 512 MB cloud tiers.
- **Evaluator Bug Fixes & Queue Isolation**:
  - **Issue #24 Fixed**: Fixed serialized answers matching bug in [`Evaluation.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Evaluation.php) `getStatus()` by enforcing exact serialized property delimiters (`\"evaluateeId\";i:X;` and `\"classId\";i:X;`).
  - **Question Count Disentanglement**: Fixed Dean (30), Program Head (30), and Dept Head (16) evaluation instruments in [`evaluation-form.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php) and role dashboard buttons.
  - **Dean Cross-Department Visibility**: Enabled College Deans with `department_id = null` to view faculty across all academic colleges in [`reports.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/reports.blade.php).
  - **Completion Tracking Scoping**: Restricted `/manage-evaluations` strictly to `role:admin`.
  - **Default Password Modal**: Added `sessionStorage` snooze persistence in [`default-password-modal.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/default-password-modal.blade.php).
- **UI/UX Refinements**:
  - **Reactive Submit Button Locking** ([`evaluation-form.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php)): Enforced `isReadyToSubmit` so the submit button stays disabled and greyed out until all questions are rated AND a constructive comment of at least 3 characters is entered.
  - **Sidebar Text Overflow & Container Boundaries** ([`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php)): Added `min-w-0 max-w-full overflow-hidden`, concise labels, and compact indentation to eliminate sub-item overflow and clipping.
  - **Student Proof Card** ([`student/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/student/dashboard.blade.php)): Removed redundant print button, maintaining a clean 15-digit reference ID copy button.

## [2026-08-25]

### Added, Optimized & UI/UX Polish
- **Evaluation Form UX & Submission Guard Optimization** ([`evaluation-form.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php)):
  - Added *"Jump to Missing Question →"* quick-action button navigating directly to the first unanswered rating.
  - Added auto-centering smooth scroll (`scrollPillIntoView`) for question number pills on touch/mobile viewports.
  - Fixed false-positive submit button loading state: Prevented premature AJAX roundtrips and loading animation when ratings are missing using client-side Alpine evaluation with `pointer-events-none` on incomplete states.
  - Improved review step layout: Replaced single-line truncate with `line-clamp-2 sm:truncate leading-snug` to prevent clipping question text on mobile screens.
  - Reduced outer & inner horizontal padding (`px-2 sm:px-4 md:px-6`, `p-3.5`) across mobile viewports, freeing up over 30px of readable screen width.
- **Evaluator Role Dashboards Mobile Viewport Optimization**:
  - Reduced excessive left/right container gutters from `px-4 py-6` to `px-2 sm:px-4 md:px-6 py-3 sm:py-6` across all 6 evaluator dashboards:
    - [`student/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/student/dashboard.blade.php)
    - [`faculty/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/faculty/dashboard.blade.php)
    - [`staff/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/staff/dashboard.blade.php)
    - [`dean/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/dean/dashboard.blade.php)
    - [`department-head/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/department-head/dashboard.blade.php)
    - [`program-head/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/program-head/dashboard.blade.php)
- **Reports & Analytics Performance & Responsiveness** ([`reports.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/reports.blade.php)):
  - Pre-loaded all `EvaluationCriterion` in a single query collection (`$allCriteria = EvaluationCriterion::orderBy('order')->get()`), eliminating 4 duplicate database queries per render.
  - Added responsive horizontal scrolling constraints (`min-w-[720px]` and `min-w-[650px]`) on Department Rankings and Faculty Attention tables.
  - Made Signatories block responsive on mobile (`grid-cols-1 sm:grid-cols-3`) while retaining 3-column format on print (`print:grid-cols-3`).
- **Evaluation Results N+1 Query Elimination & Grouped Aggregations** ([`evaluation-results.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-results.blade.php)):
  - Eliminated row-level N+1 database queries in the paginated table loop by batch-aggregating evaluator submission counts and evaluatee received counts in two single `whereIn` grouped SQL queries in `with()`.
  - Consolidated modal details categorical breakdown from 10 separate queries down to 1 single grouped query (`selectRaw('evaluation_type, count(*) as total_count, avg(rating_average) as avg_rating')`).
- **Completion Tracking & Rankings Layout Optimization**:
  - **Completion Tracking** ([`manage-evaluations.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/manage-evaluations.blade.php)): Converted role tabs into a responsive grid (`grid-cols-2 sm:grid-cols-3 lg:grid-cols-6`) distributing all 6 tabs evenly across the container without empty dead space on desktop.
  - **Rankings** ([`rankings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/rankings.blade.php)): Added `min-w-[700px]` constraints to both the Faculty Leaderboard and Department Leaderboard tables inside responsive scroll wrappers.
- **UI De-Cluttering Across Administrative Views & Cards**:
  - Removed redundant, static page-level subheadings across all admin views: Completion Tracking, Evaluation Results, Institutional Rankings, Evaluation Reports, Admin Dashboard, Manage Departments, Manage Programs, Manage Subjects, Manage Classes, Manage Questions, Evaluation Settings, and Manage AI.
  - Removed static filler captions from cards in Admin Dashboard, Manage Departments, Manage Programs, and Manage Subjects, creating a cleaner, higher-density UI while preserving all critical dynamic numbers, progress counters (`X of Y submitted`), and active filters.
  - Synchronized all 1:1 skeleton placeholders across `resources/views/livewire/placeholders/` to match live page headings and layouts, eliminating layout shift.

## [2026-08-23]

### Added, Optimized & Security
- **Completion Tracking Zero-Overhead Direct SQL Joins & N+1 Query Elimination** ([`manage-evaluations.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/manage-evaluations.blade.php)):
  - Rewrote tracking getters (`getStudentTrackingProperty`, `getDeanTrackingProperty`, `getProgramHeadTrackingProperty`, `getDepartmentHeadTrackingProperty`, `getProfessorTrackingProperty`, `getStaffTrackingProperty`) to use direct SQL joins and pre-aggregations (`DB::table(...)` with `groupBy`).
  - Reduced model hydration on Manage Evaluations from **11,867 Eloquent models down to 0 models**.
  - Consolidated KPI metric counters in `getSummaryStatsProperty()` into a single aggregate SQL query (`COUNT(CASE WHEN ... THEN 1 END)`).
  - Reduced total page query statements from **4,859 queries down to ~10 queries**.
- **Evaluation Reference ID Memoization** ([`EvaluationReferenceService.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Services/EvaluationReferenceService.php)):
  - Added static memoization (`static $semCache = []`) inside `EvaluationReferenceService::generate()` to eliminate repeated semester database queries for each completed evaluator row.
- **Application-Level In-Memory Caching Layer (`Cache::remember`)**:
  - `Semester::getActive()`: Caches active academic semester and year in memory with automated cache busting on model `saved` and `deleted` events in `Semester::booted()`.
  - `Department::getCachedList()`: Caches department list for 300s with automated cache invalidation.
  - `EvaluationCriterion::getForTypes(array $types)`: Caches active evaluation criteria and questions for 600s with automated cache invalidation hooks on criteria and question model events.
  - Manage Evaluations Summary Stats & Tab Counts: Wrapped in 30-second memory cache for instant tab transitions.
  - Updated [`evaluation-form.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php), [`rankings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/rankings.blade.php), [`reports.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/reports.blade.php), [`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php), and [`dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/dashboard.blade.php) to use cached helpers.
- **Database Composite Performance Indexes**:
  - Created migration `2026_08_23_000001_add_performance_composite_indexes.php`:
    - `employees`: `(role, status, department_id)`
    - `evaluations`: `(semester_id, evaluation_type)` and `(semester_id, evaluator_id)`
    - `classes`: `(semester_id, teacher_id)`
    - `semesters`: `(is_active, is_evaluation_open)`
- **Cleaned Up Orphaned Analytics Component**:
  - Deleted unused `resources/views/livewire/analytics.blade.php` and its skeleton placeholder `resources/views/livewire/placeholders/analytics-skeleton.blade.php`.
  - Removed obsolete `/analytics` route from `routes/web.php`.
- **Terms of Service & Privacy Modal UI Redesign** ([`terms-modal.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/terms-modal.blade.php)):
  - Removed duplicated headers, institutional doc ID noise, and heavy legal jargons.
  - Restructured both tabs into clean, human-readable card-based sections with dedicated Lucide/Flux icons:
    - *Terms*: Constructive & Honest Feedback, Account Security, Submission Finality, Strict Non-Retaliation.
    - *Privacy & AI*: Guaranteed Anonymity, Data Protection (RA 10173), AI Sentiment Analysis Transparency.
- **Sidebar UI/UX & Tooltip Refactor** ([`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php)):
  - Removed native browser `title="..."` attributes on navigation items to eliminate duplicate overlapping OS popups.
  - Isolated `<flux:tooltip>` popups to collapsed icon-only mode so expanded browsing remains distraction-free.
  - Implemented auto-expansion for collapsed accordion dropdowns (`Manage Users`, `My Evaluations`) on click.
  - Solved `pointer-events` blocker on expanded sidebar navigation links.
  - Built zero-flicker CSS transition architecture using `body.sidebar-animating` and `html.sidebar-is-collapsed` to prevent layout flashes during `wire:navigate` page transitions.
- **Accessibility & Google Lighthouse Compliance**:
  - Added descriptive `aria-label` attributes across all navigation links, accordion triggers, submenus, theme toggle, user profile menu, and notification dropdown.
  - Added `fetchpriority="high"` and `decoding="async"` to the institutional logo in [`app-logo.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/app-logo.blade.php) to minimize Cumulative Layout Shift (CLS).
- **Security Audit Vulnerabilities Resolution**:
  - `composer audit`: Resolved all package security advisories (`guzzlehttp/guzzle`, `guzzlehttp/psr7`, `guzzlehttp/promises`, `league/commonmark`, `symfony/deprecation-contracts`) with 0 advisories remaining.
  - `npm audit fix`: Resolved all NPM vulnerabilities (`axios`, `nanoid`, `postcss`, `shell-quote`) with 0 vulnerabilities remaining.
  - 100% test suite passing (130 feature tests, 621 assertions) and clean PHPStan analysis (0 errors).

## [2026-08-21]

### Added, Optimized & Security
- **System-Wide Database Performance Optimization & Query Profiling**:
  - **Admin Dashboard Optimization** ([`admin/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/dashboard.blade.php)):
    - Eliminated in-loop sub-queries and in-memory model loading across AI Sentiment Statistics, Department Completion Rates, Rating Distribution, and Department Averages.
    - Dropped models loaded in memory from **139,039 models down to 171 models** (99.8% reduction).
    - Reduced total database query statements from **128 queries down to 34 queries** (eliminating 102 duplicate statements).
    - Added request-level memoization (`$this->cachedData`) to prevent duplicate view calculations.
  - **Manage Employees Query Refactor** ([`manage-employees.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-employees.blade.php)):
    - Replaced 7 individual `whereHas('employee')` count subqueries for role badge tabs (`All`, `Admin`, `Dean`, `Department Head`, `Program Head`, `Faculty`, `Staff`) with 1 grouped SQL query (`Employee::selectRaw('role, count(*)')->groupBy('role')`).
    - Reduced query count from **38 queries down to 18 queries** executing in **13.4 ms**.
  - **Manage Students Eager Loading** ([`manage-students.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-students.blade.php)):
    - Added eager loading `with(['student.program', 'roles'])` to eliminate N+1 queries during row rendering, running in **32 ms** total database time.
  - **Manage Classes & Completion Tracking Performance** ([`manage-evaluations.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/manage-evaluations.blade.php)):
    - Replaced correlated subqueries on 946 classes with 2 indexed `groupBy('class_id')` lookup maps, reducing class evaluation calculation from **3,147 ms down to 100 ms**.
    - Replaced tab button badge counts with a dedicated `getCategoryCountsProperty()` SQL query, preventing the 6 inactive tabs from computing their collections on page load.
    - Reduced Completion Tracking page response time from **4.14 seconds down to 470 ms** (9x speedup).
  - **Faculty & Department Rankings N+1 Elimination** ([`rankings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/rankings.blade.php)):
    - Replaced per-faculty evaluation counts and averages loop with a single grouped SQL query (`groupBy('evaluatee_id')`), dropping queries from **113 statements down to 10 statements** (91% reduction) in **37 ms**.
  - **Evaluation Reports & Summary Analytics Optimization** ([`reports.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/reports.blade.php)):
    - Removed heavy unused `answers.question.criterion` eager loading graph tree on Individual Reports, reducing memory from **11,041 models down to ~400 models**.
    - Refactored Summary Reports (`getSummaryReportDataProperty()`) to use direct SQL `GROUP BY` aggregations and `HAVING` filters across all 23,000 evaluations.
    - Reduced Summary Reports render duration from **10.04 seconds down to 0.9 seconds** and memory from **107 MB down to 10 MB**.
  - **Universal Evaluator Dashboard Memoization** ([`Evaluation.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Evaluation.php)):
    - Added static per-request status memoization (`Evaluation::$statusCache`) in `Evaluation::getStatus()`.
    - Batch-fetches all completed evaluations and queue jobs for an evaluator in 1 query, converting all subsequent loop status checks to instant `O(1)` in-memory dictionary lookups.
    - Optimized all 6 evaluator portals (**Student Dashboard**: 65 queries $\rightarrow$ 9 queries in 6 ms; **Faculty Dashboard**: 6 queries in 2 ms; **Dean Dashboard**: 4 queries in 1.9 ms).

- **Google Lighthouse Full Compliance (100 Best Practices, 100 SEO, 95 Accessibility, 95 Performance)**:
  - **Font Optimization**: Added `&display=swap` to Google Fonts Inter and JetBrains Mono in [`head.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/partials/head.blade.php).
  - **Script Deferral**: Added `defer` attribute to Chart.js CDN script tag.
  - **Cumulative Layout Shift (CLS) Elimination**: Added explicit `width="220" height="72"` attributes to the SVG institutional logo in [`app-logo.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/app-logo.blade.php).
  - **Semantic HTML & Landmark Navigation**: Wrapped main application slot in `<main id="main-content">` landmark inside [`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php).
  - **Accessible Form Controls**: Added descriptive `aria-label` attributes to all filter select dropdowns in [`manage-evaluations.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/manage-evaluations.blade.php).
  - **Color Contrast & Heading Order**: Upgraded heading tags (`h3` $\rightarrow$ `h2`) and boosted text color contrast across dashboard stat cards and footer text.
  - **HTTPS & SSL**: Secured domain with SSL certificates via `herd secure evaluationsystem`.

- **Security Hardening & Headers Middleware**:
  - Created [`SecurityHeadersMiddleware.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Http/Middleware/SecurityHeadersMiddleware.php) attaching `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Cross-Origin-Opener-Policy: same-origin`, `Permissions-Policy`, and `HSTS` (Strict-Transport-Security).
  - Registered middleware globally in the `web` pipeline in [`bootstrap/app.php`](file:///c:/Users/USER/Herd/evaluationsystem/bootstrap/app.php).
  - Audited and verified full 8-point institutional security checklist: XSS escaping via Blade, MIME-type file upload validation and private storage, rate-limiting on authentication and Livewire actions, Role/Department scoping, private database access, and production stack trace masking.

## [2026-08-20]

### Added & Security
- **Admin Dashboard Audit Log Card & Full System Activity Tracking**:
  - **Side-by-Side Fixed Height Layout**: Replaced stacked cards with a responsive 2-column grid (`grid grid-cols-1 lg:grid-cols-2 gap-8`) setting both the **Audit Log** and **Recent Submissions Log** to matching fixed heights (`h-[480px]`) with smooth vertical scrolling (`overflow-y-auto pr-2`).
  - **Noise-Free Activity Logging**: Configured [`User.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/User.php) to ignore internal background timestamp touches (`notifications_last_viewed_at`, `dismissed_notifications`, `password_changed_at`, `remember_token`, `updated_at`), eliminating raw column clutter like `Modified fields: updated_at`.
  - **Comprehensive Model Coverage**: Integrated `LogsActivity` across all system models: [`User`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/User.php), [`Employee`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Employee.php), [`Student`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Student.php), [`Subject`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Subject.php), [`AcademicClass`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/AcademicClass.php), [`Department`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Department.php), [`Program`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Program.php), [`Semester`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/Semester.php), [`AcademicYear`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/AcademicYear.php), [`EvaluationQuestion`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/EvaluationQuestion.php), and [`EvaluationCriterion`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/EvaluationCriterion.php).
  - **Custom Administrative Operation Logging**: Added activity tracking for bulk CSV imports (employees, students, classes/rosters), AI model retraining, sentiment label corrections, and deadline reminder broadcasts.
  - **Natural English Action Formatter**: Formatted every event into clear, plain language (e.g. *"Created user account for Maria Clara (maria@grc.edu.ph)"*, *"Added new department: College of Computer Studies (CCS)"*, *"Bulk imported 45 new students via CSV"*).
  - **Recent Submissions Log Dean Evaluation Label**: Fixed role evaluation mapping in [`admin/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/dashboard.blade.php) so Dean evaluating Professor or Program Head properly displays the badge title **`Dean Evaluation`** instead of falling back to generic `Evaluation`.
  - **Questionnaire Parts Edit & Action Dropdown**: Replaced the standalone trash icon on all Questionnaire Parts rows in [`evaluation-settings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php) with an ellipsis action dropdown (`flux:dropdown`) containing **`Edit Part`** and **`Delete Part`**. Added an interactive **Edit Questionnaire Part Modal** allowing admins to rename parts and adjust max points without having to delete and recreate questions.
  - **Feature Test Suite**: Added test cases in [`ActivityLogTest.php`](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/ActivityLogTest.php) and [`EvaluationSettingsAndEnhancementsTest.php`](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/EvaluationSettingsAndEnhancementsTest.php) validating the new edit functionality.
- **Training & AI Controls Restricted Exclusively to Administrators**:
  - **Settings Navigation Link**: Restricted `<flux:navlist.item>` for `Training` in [`layout.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/settings/layout.blade.php) with `@if(auth()->check() && auth()->user()->hasRole('admin'))`, preventing students, faculty, and other non-admin accounts from seeing the Training tab in Account Settings.
  - **Route Protection Middleware**: Bound `->middleware('role:admin')` to the `settings/training` route in [`routes/web.php`](file:///c:/Users/USER/Herd/evaluationsystem/routes/web.php) ensuring unauthorized HTTP requests return `403 Forbidden`.
  - **Component Mount Guard**: Added `abort_unless(auth()->user()?->hasRole('admin'), 403)` in [`training.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/settings/training.blade.php) as defense-in-depth.
  - **Automated Pest Test**: Added test coverage in [`EvaluationSettingsAndEnhancementsTest.php`](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/EvaluationSettingsAndEnhancementsTest.php) validating that non-admin accounts (e.g. students) cannot see or visit the Training settings.
- **Evaluation Progress Counter Badges on All Evaluator Dashboards**:
  - Added real-time evaluation progress badges (e.g. `X/Y evaluated` with bold `#9b0000` / `#f89696` accent highlight) beside card headings across all 6 evaluator portals:
    - **Student Dashboard** ([`student/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/student/dashboard.blade.php)): Displays `X/Y evaluated` beside `"My Enrolled Classes & Professors"`.
    - **Faculty Dashboard** ([`faculty/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/faculty/dashboard.blade.php)): Displays progress badges beside `"Self Evaluation"` (`0/1` or `1/1 evaluated`), `"Peer Evaluations (Faculty within my department)"` (`X/Y evaluated`), and `"Supervisor Evaluation (Program Heads)"` (`X/Y evaluated`).
    - **Dean Dashboard** ([`dean/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/dean/dashboard.blade.php)): Displays progress badges beside `"Self Evaluation"`, `"Academic Faculty Evaluations"`, and `"Program Head Evaluations"`.
    - **Program Head Dashboard** ([`program-head/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/program-head/dashboard.blade.php)): Displays progress badges beside `"Self Evaluation"`, `"Supervisor Evaluation (College Dean)"`, and `"Faculty Evaluations (Subordinate Professors)"`.
    - **Department Head Dashboard** ([`department-head/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/department-head/dashboard.blade.php)): Displays progress badges beside `"Self Evaluation"`, `"Staff Evaluation"`, and `"Dean Evaluation"`.
    - **Staff Dashboard** ([`staff/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/staff/dashboard.blade.php)): Displays progress badges beside `"Self Evaluation"`, `"Peer Evaluation"`, and `"Supervisor Evaluation (Department Head)"`.
- **Default Password Security Advisory Modal**:
  - **Schema Migration**: Added [`password_changed_at`](file:///c:/Users/USER/Herd/evaluationsystem/database/migrations/2026_08_20_000001_add_password_changed_at_to_users_table.php) nullable timestamp to the `users` table to track when accounts perform explicit credential updates.
  - **User Model Detection**: Added `isUsingDefaultPassword(): bool` helper method on [`User.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/User.php) that identifies accounts using the system initial password (`password`) with uninitialized `password_changed_at`.
  - **Livewire Advisory Modal Component**: Created [`default-password-modal.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/default-password-modal.blade.php) featuring official GRC branding, security key icon, and intuitive dual-action controls:
    - **"Change Password Now"**: Directly routes to [`/settings/password`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/settings/password.blade.php) with `wire:navigate`.
    - **"Remind Me Later"**: Snoozes the advisory for the active session without repeatedly interrupting the user on subsequent page navigation.
  - **Master Layout Integration**: Embedded `<livewire:default-password-modal />` in [`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php) protecting all user portals (Student, Faculty, Dean, Program Head, Department Head, Staff, and Admin).
  - **Password Settings Synchronization**: Updated [`password.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/settings/password.blade.php) to automatically update `password_changed_at` upon saving new passwords, permanently resolving the prompt.
  - **Feature Test Suite**: Added comprehensive Pest test coverage in [`DefaultPasswordModalTest.php`](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/DefaultPasswordModalTest.php) validating default detection, modal display, session dismissal, route navigation, and permanent resolution.

## [2026-08-19]

### Added & Optimized
- **Mobile Responsive UI Overhaul & Navigation Polish**:
  - **Sidebar Toggle & Branding**: Fixed the mobile sidebar toggle button (`$dispatch('flux-sidebar-toggle')` on mobile view vs `$dispatch('toggle-sidebar')` on desktop). Enforced the full GRC institutional logo on mobile sidebars while preserving the icon-only view exclusively for desktop mini collapsed state (`lg:flex` with `sidebar-is-collapsed`).
  - **Account Settings Mobile UI**: Updated Profile, Password, Appearance, and Training / AI Model Settings (`/settings/*`) to fluid 1-column layouts on mobile viewports, preventing overflowing form cards and misaligned submit buttons.
  - **Evaluation Settings Mobile Optimization**: Optimized the Evaluation Weights & Questionnaire Parts Allocation cards in [`evaluation-settings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php), correcting horizontal overflow and desktop margin alignment for criteria points.
  - **Mobile Filter Dropdowns**: Standardized filter dropdown widths across Rankings (`/rankings`), Completion Tracking (`/manage-evaluations`), and Reports (`/reports`) to full width on mobile devices (`w-full sm:w-auto` / `w-full sm:w-56`).
  - **Notification Dropdown Mobile Polish**: Refined [`notification-dropdown.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/notification-dropdown.blade.php) with `whitespace-nowrap` on "Read all" / "Clear all" actions, responsive dropdown widths (`w-[calc(100vw-1.5rem)] sm:w-96 max-w-[24rem]`), non-truncating notification title wraps, and visible touch dismiss buttons.

- **Completion Tracking Pagination Across All 7 Categories**:
  - Connected Livewire `WithPagination` across all 7 evaluation tracking tabs on [`manage-evaluations.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/manage-evaluations.blade.php) (`Student`, `Dean`, `Program Head`, `Department Head`, `Peer`, `Supervisor`, and `Self`).
  - Appended dedicated pagination links (`{{ $paginated->links() }}`) below each category table for seamless navigation across large faculty and class cohorts.

- **Clean PDF & Print Export (Navbar & UI Chrome Exclusion)**:
  - Applied `print:hidden` to `<x-admin.navbar />` in [`navbar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/admin/navbar.blade.php), `<flux:sidebar>` in [`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php), and `<footer>` in [`footer.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/admin/footer.blade.php).
  - Added global `@media print` rules in [`app.css`](file:///c:/Users/USER/Herd/evaluationsystem/resources/css/app.css) ensuring no navigation headers, dark-mode badges, user avatars, or UI buttons leak onto printed or exported PDF evaluation reports.

- **Evaluator Dashboard Tables Scrollability**:
  - Updated tables across all evaluator dashboards ([`student/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/student/dashboard.blade.php), [`faculty/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/faculty/dashboard.blade.php), [`staff/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/staff/dashboard.blade.php), [`dean/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/dean/dashboard.blade.php), [`department-head/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/department-head/dashboard.blade.php), [`program-head/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/program-head/dashboard.blade.php)) with `overflow-auto max-h-[500px]`, sticky headers (`sticky top-0 z-10`), and minimum column widths to enable smooth horizontal and vertical scrolling on mobile devices.

- **Evaluation Form Mobile UI & Submit Button Optimization**:
  - Redesigned the review & submission action controls in [`evaluation-form.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php) to responsive `flex-col-reverse sm:flex-row`, eliminating button clipping (`Su...`).
  - Switched the 1–5 rating buttons container to a responsive 5-column grid (`grid grid-cols-5 w-full max-w-xs sm:max-w-md`), ensuring buttons 1 and 5 never overflow or clip outside mobile screens.
  - Added horizontal scrollability (`overflow-x-auto`) to the question number pill navigator bar.

### Added
- **Phase 1 Master Dataset Architecture & Seeding**:
  - Populated 124 institutional employees (1 Dean, 11 Department Heads, 4 Program Heads, 50 Faculty Professors, and 57 Administrative Staff) across 15 departments (4 Academic: CCS, COA, COE, CBAE; 11 Administrative: Accounting, Admission, Clinic, General Service, Guidance, IT Office, Library, OSA, Registrar, RCE, Scholarship).
  - Populated 3,200 active student records distributed evenly (800 CCS, 800 COA, 800 COE, 800 CBAE) across 13 academic degree programs.
  - Cataloged 176 unique academic subjects and generated 946 section classes with 29,291 student enrollments.
  - Standardized 130 evaluation questions across 21 rubric parts for all 7 evaluation types (`upward_student`, `self`, `dean`, `program_head`, `peer`, `department_head`, `upward_employee`).
  - Calibrated the Teaching Effectiveness formula allocation in evaluation settings (Student 40%/80pts, Dean 20%/40pts, Program Head 20%/40pts, Peer 15%/30pts, Self 5%/10pts $\rightarrow$ 200 Max Scale).
- **Phase 2 Authentic Evaluation Population & Demonstration Dataset**:
  - Implemented high-performance batch seeder [`EvaluationPhase2Seeder.php`](file:///c:/Users/USER/Herd/evaluationsystem/database/seeders/EvaluationPhase2Seeder.php) generating 23,056 evaluations, 499,222 individual question ratings, and 23,056 VADER/Decision Tree sentiment records in under 30 seconds.
  - Aggregated overall performance scorecards for all 124 employees in the `evaluation_summaries` table.
  - Reserved $\approx 20\% - 25\%$ pending evaluation queues across Students, Deans, Program Heads, Faculty, Department Heads, and Staff to enable live, end-to-end presentation demonstrations.
  - Authored a rich multilingual bank of authentic student and employee reflections in English, Taglish, and Filipino across 70% positive, 20% neutral/constructive, and 10% negative sentiments.

- **Admin Bulk Operations, Enrollment Roster Management & Student Status Lifecycle**:
  - **Manage Students (`/admin/students`)**: Added Download CSV Template, Bulk CSV Import with automatic `User` account generation and program resolution, Export Filtered Students (CSV), and enrollment status filters (`Regular`, `Irregular`, `LOA`, `Dropped`, `Graduated`, `Inactive`).
  - **Manage Employees (`/admin/employees`)**: Added Download CSV Template, Bulk CSV Import across all 6 roles (`faculty`, `dean`, `department head`, `program head`, `staff`, `admin`), automatic role assignment and department sync, and Export Filtered Employees (CSV).
  - **Manage Classes & Rosters (`/admin/classes`)**: Added Download CSV Template, Bulk Class Schedule and Student Roster Import (CSV) with automatic section creation and `class_student` pivot linking, and Export Class Masterlist (CSV).
  - **Automated Feature Verification**: Added test suite [`AdminBulkOperationsTest.php`](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/AdminBulkOperationsTest.php) verifying CSV download templates, exports, imports, and multi-student roster allocations.

- **Admin Dashboard Quick System Actions Hub**:
  - Converted quick action cards to fully functional `<a href="..." wire:navigate>` single-page navigation links with smooth hover animations and GRC red accents (`border-l-[#9b0000]`).
  - Removed the AI Model & Training button in favor of straightforward, direct operational actions:
    1. **Track Evaluation Turnout** (`/manage-evaluations`)
    2. **View Completed Evaluations** (`/evaluation-results`)
    3. **Generate GRC Reports** (`/reports`)
    4. **Department & Faculty Rankings** (`/rankings`)
    5. **Evaluation Schedule & Settings** (`/admin/evaluation-settings`)
    6. **Edit Evaluation Questions** (`/admin/questions`)
    7. **Manage Classes & Rosters** (`/admin/classes`)
    8. **Manage Student Accounts** (`/admin/students`)
    9. **Manage Employee Accounts** (`/admin/employees`)
    10. **Manage Subject Catalog** (`/admin/subjects`)
    11. **Manage Departments** (`/admin/departments`)
    12. **Manage Academic Programs** (`/admin/programs`)

### Changed & Fixed
- **Authentication & Login Form Design System Overhaul**:
  - Completely isolated the auth login container card from dark-mode override conflicts.
  - Enforced a solid white card background, visible input borders (`border border-zinc-300 focus:border-[#9b0000]`), and dark high-contrast readable text (`text-zinc-900 font-semibold`).
  - Standardized form action buttons and labels to official GRC deep red branding (`bg-[#7a0000] hover:bg-[#9b0000]` and `text-[#7a0000] font-bold`).
  - Added integrated interactive password visibility toggle eye icons.
  - Case-insensitive credential lookup supporting emails, student IDs, employee IDs, and standard administrator aliases (`admin`, `ADMIN-001`, `dion.areglo1234@gmail.com`).
- **Evaluation Form Review & Banner Dark Mode Contrast**:
  - Fixed low-contrast text on completion banners and status badges in [`evaluation-form.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php), ensuring vibrant emerald tones and clear badge outlines across dark mode themes.
- **Evaluation Questions Setup Navigation**:
  - Removed points annotation (`(XX pts)`) from tab headers in [`manage-questions.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-questions.blade.php) to accommodate context-dependent reporting rubrics.

### Changed & Fixed
- **Mobile & Desktop Table Responsiveness Standardization**:
  - Standardized all system tables to full width (`w-full`) on desktop while enforcing minimum width barriers (`min-w-[700px]` to `min-w-[850px]`) and smooth `overflow-x-auto` horizontal scrolling on mobile:
    - [`manage-departments.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-departments.blade.php): `w-full min-w-[700px]` with custom column layout (`Department Name`, `Department Type`, `Head`, `Members`, `Actions`).
    - [`manage-subjects.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-subjects.blade.php): `w-full min-w-[750px]`.
    - [`manage-students.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-students.blade.php): `w-full min-w-[850px]`.
    - [`manage-programs.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-programs.blade.php): `w-full min-w-[750px]`.
    - [`manage-classes.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-classes.blade.php): `w-full min-w-[850px]`.
    - [`evaluation-settings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php): `w-full min-w-[750px]`.
    - [`evaluation-results.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-results.blade.php): `w-full min-w-[800px]`.
- **Evaluation Results Page Overhaul & Multi-Role Query Resolution**:
  - Fixed query execution on `/evaluation-results` by replacing raw column queries with proper Eloquent relational constraints (`whereHas('employee')` and `whereHas('student')`).
  - Added multi-role filtering across Deans, Program Heads, Department Heads, Professors, Staff, and Students.
  - Implemented detailed user submission and received evaluation modal breakdown.
- **Reports Action Button & Scoping**:
  - Renamed "Print GRC Report" button to **"Save as PDF"** (`window.print()`).
  - Scoped department and faculty evaluation selections strictly to academic departments.

## [2026-08-15]

### Added
- **Official GRC Summary of Faculty Performance Evaluation on Teaching Effectiveness**:
  - **Page 1 (Official GRC Scorecard)**: Exact replica of the Global Reciprocal Colleges (GRC) evaluation document using the official system logo asset `GRC-o-Evaluation-LOGO.png`, boxed title, Roman numeral criteria breakdown (Mastery of Subject Matter, Teaching Skills & Class Management, Personal Traits, Other Factors), 360-degree Peer Evaluation inclusion (40% Student / 20% Dean / 20% Program Head / 15% Peer / 5% Self), 200-point GRC Legend table, Overall Rating Box, and official 3-tier signatures (*Prepared by*, *Noted by*, *Approved by*).
  - **Page 2 (AI Student Comments Analysis)**: Embedded official GRC big logo, replaced raw comment text dumps with structured AI qualitative analysis (sentiment distribution gauge, Top Student Commendations, Key Opportunities for Growth, representative bilingual feedback quotes), with signatures removed so the page is focused exclusively on NLP insights.
- **Reworked Evaluation Weight Score Card & Dynamic Max Points Target**:
  - Added report-specific tab switching in [`evaluation-settings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php):
    - **Individual Teaching Effectiveness (Faculty 360°)**: Dynamic % weights and point targets for Student (40% / 80 pts), Dean (20% / 40 pts), Program Head (20% / 40 pts), Peer (15% / 30 pts), and Self (5% / 10 pts) against configurable overall max points (200 pts).
    - **Administrative Staff 360° Report**: Dedicated targets for Department Head, Staff Peer, Subordinate/Client, and Self.
    - **All Categories (Global Master Targets)**: Global master targets across all 7 evaluation relationship terms.
- **Dean Evaluation of Academic Faculty (Professors)**:
  - Enabled College Deans to evaluate academic professors directly through the **Academic Faculty Evaluations** tab on [`dean/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/dean/dashboard.blade.php) and sidebar submenu.
- **Actionable Evaluation Summary Report Redesign**:
  - **Faculty Requiring Attention**: Surfaced flagged instructors with rating averages < 3.50 or negative sentiment ≥ 30%, complete with department, submissions count, rating severity (Critical vs Moderate), sentiment split, and a one-line AI generated reason/driver based on student feedback themes.
  - **Turnout & Data Confidence Rates**: Computed expected vs submitted evaluations per department (using student enrollments, peer matrix, and administrative supervisor evaluations), displaying completion % and low turnout / data confidence warnings (<60%).
  - **Rating Distribution & Spread**: Displayed Min - Max ranges and standard deviations (`σ`) alongside mean scores in executive KPIs and department leaderboards.
  - **Per-Department Sentiment Splits & Period Deltas**: Added positive/neutral/constructive sentiment bars and period-over-period delta comparisons (▲/▼ +X.XX vs previous semester) per academic department.
  - **Prescriptive AI Recommendations**: Added structured actionable takeaway cards covering priority interventions, target benchmark attainment, participation remediation, and best-practice commendations.
  - **Bilingual Sentiment Theme Extraction**: Tokenized bilingual Taglish/English comments to surface top positive drivers and top constructive opportunities.
  - **Target Benchmark Context**: Institutional target comparison (`Target Benchmark: 4.00 / 5.00` • `+0.31 Above Target`).
- **Evaluation Questions Setup Modernization**:
  - Standardized all 7 evaluation categories across tabs and modal dropdowns in [`manage-questions.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-questions.blade.php): **Student** (Student → Faculty), **Dean** (Dean → Program Head), **Program Head** (Program Head → Faculty), **Department Head** (Department Head → Staff), **Peer** (Faculty → Faculty / Staff → Staff), **Supervisor** (Faculty → PH / Staff → DH / PH/DH → Dean), and **Self** (Self → Self).
  - Added live search filter to quickly filter questions within any criteria part.
  - Applied primary brand accent tokens (`#9b0000` / `#f89696`), `border-l-[5px]` card accents, and instant `Flux::toast` notification feedback for question create, update, toggle status, and delete operations.
  - Automatically loads and displays dynamic criteria point targets per category directly from the active academic semester.
- **Monochrome Reports Redesign & Semester-over-Semester Growth**:
  - Rewrote [`reports.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/reports.blade.php) UI to use clean, high-contrast monochrome (black/zinc) aesthetics without colorful backgrounds.
  - Implemented **Semester-over-Semester Growth** indicators in both Individual and Summary reports comparing against the immediately preceding chronological semester.
  - Added Institutional Academic Department Rankings leaderboard table in the Summary Report showing rank, department name, department head/dean, faculty count, submission count, and average score.
  - Streamlined AI Qualitative Insights to 1-glance distribution bars with positive/neutral/constructive breakdown and executive summary narrative.
- **Evaluation Settings Questionnaire Parts Split**:
  - Split Section 4 Program Head and Department Head questionnaire parts into separate cards in [`evaluation-settings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php) so each role can configure distinct parts while sharing the configured target points.
  - Added instant toast notification feedback upon saving criteria points and score weights.
- **Staff Peer Evaluations & Supervisor Routing**:
  - Added Staff Peer Evaluations (`peer`) for staff evaluating colleagues within the same administrative department.
  - Corrected staff supervisor routing to evaluate their **Department Head** instead of Program Head.
  - Updated staff dashboard tabs (`self`, `peer`, `supervisor`) and dynamic notifications in [`User.php`](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/User.php).

### Changed & Fixed
- **Rankings Scope & Access Restrictions**:
  - Filtered Faculty Rankings strictly for teachers (`role === 'faculty'`) in [`rankings.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/rankings.blade.php).
  - Filtered Department Rankings strictly for academic departments (`type === 'academic'`).
  - Removed Rankings navigation access from faculty role in [`sidebar.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php) and [`routes/web.php`](file:///c:/Users/USER/Herd/evaluationsystem/routes/web.php).
- **Admin Dashboard Cleanup**:
  - Removed Department Participation Rates card from [`admin/dashboard.blade.php`](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/dashboard.blade.php) and expanded Recent Submissions Log to full width.
- **Color System Audit & Semantic Token Standardization**:
  - Created [`docs/color audit.md`](file:///c:/Users/USER/Herd/evaluationsystem/docs/color%20audit.md) providing a complete tokenized audit of all primary brand, background, surface, text, border, and status colors across Light Mode and Dark Mode.
  - Unified primary brand accent tokens across [app.css](file:///c:/Users/USER/Herd/evaluationsystem/resources/css/app.css) and all Livewire views: `#9b0000` (Light) / `#f89696` (Dark) with hover `#7a0000` / `#f57575`.
  - Standardized all card left accent borders to `border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]`.
  - Unified all Call-to-Action (CTA) primary buttons:
    - **Light Mode**: Background `#9b0000`, Text `#ffffff`, Hover `#7a0000`.
    - **Dark Mode**: Background `#f89696`, Text `#171717`, Hover `#f57575`.
- **Global Typography Migration (Lexend)**:
  - Migrated system-wide font family from Inter to Google Font **Lexend** across [app.css](file:///c:/Users/USER/Herd/evaluationsystem/resources/css/app.css), [head.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/partials/head.blade.php), and [welcome.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/welcome.blade.php).
- **Interactive Navbar Notification Hub & Dismiss/Clear-All Engine**:
  - Built dedicated Livewire component [notification-dropdown.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/notification-dropdown.blade.php) embedded directly inside [navbar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/admin/navbar.blade.php).
  - Added **"Read all"** action (`wire:click="markAllAsRead"`) that updates `notifications_last_viewed_at` and clears unread badges with instant toast feedback.
  - Added **Individual Dismiss** (`✕` button per notification) and **"Clear all"** action (`wire:click="clearAll"`).
  - Created migration `2026_08_15_152600_add_dismissed_notifications_to_users_table.php` and updated [User.php](file:///c:/Users/USER/Herd/evaluationsystem/app/Models/User.php) with `dismissNotification()` and `clearAllNotifications()` methods.
  - Added full test coverage in [NotificationDropdownTest.php](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/NotificationDropdownTest.php).

### Changed & Fixed
- **Welcome Page Aesthetic Enhancements**:
  - Changed the Log In / Dashboard button to high-contrast white background with black text (`bg-white text-black hover:bg-zinc-100`).
  - Updated floating brand logo drop shadow to a luminous white halo (`drop-shadow-[0_4px_25px_rgba(255,255,255,0.55)]`).
- **Sidebar Sizing & Streamlined Navigation**:
  - Resized expanded full logo in [app-logo.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/app-logo.blade.php) from fixed 48px (`h-12`) to `h-16 md:h-18 w-full max-w-[220px] object-contain` for a natural fit.
  - Fixed collapsed sidebar favicon icon visibility in [sidebar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php).
  - Removed redundant user profile dropdown from sidebar footer in favor of the navbar quick profile menu.
  - Removed redundant sidebar "Notifications" link in favor of the comprehensive navbar notification hub.

---

## [2026-08-14]

### Added
- **Overhaul Evaluation Settings UI & 6 Standardized Relationship Categories**:
  - Rendered all 6 relationship categories in Section 4 of `/admin/evaluation-settings` ([evaluation-settings.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php)) including **Dean Evaluation Parts** (`dean`) and **Superior Evaluation Parts** (`superior`).
  - Standardized terminology across the system (Student, Dean, Program/Dept Head, Peer, Self, Superior), replacing legacy terms (`upward_student`, `downward`, `upward_employee`).
  - Configured each `+ Add Part` button to auto-preselect its exact category in the Create Part modal (`wire:click="openCriterionModal('student')"`, `'dean'`, `'ph_dh'`, `'peer'`, `'self'`, `'superior'`).
  - Removed indicator dots to eliminate visual clutter and updated Superior Evaluation labels to explicitly state `PH/DH → Dean` (`Faculty → PH, Staff → DH, PH/DH → Dean`).
- **High-Contrast Theme Legibility**:
  - Upgraded Section 3 Top Control Banner and Category Weight Cards in `evaluation-settings.blade.php` with theme-aware classes (`bg-zinc-50 dark:bg-zinc-800/80` and `bg-white dark:bg-zinc-800/40`), ensuring 100% legibility in both Light Mode and Dark Mode.
- **Department Head System Role & Administrative Department Leadership**:
  - Added first-class support for the **Department Head** user role (`department head`) across Spatie role management, user administration (`/admin/employees`), administrative department leadership pointers (`departments.department_head_id`), and relationship evaluation matrices.
- **Bidirectional Department Leadership Synchronization**:
  - Added `syncDepartmentHeadship()` in [manage-employees.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-employees.blade.php). Assigning or updating an employee's department on the Employees Page automatically updates the department leadership pointers (`program_head_id` / `department_head_id`) on the department.
  - Enforced single authoritative leader rendering in [manage-departments.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-departments.blade.php), resolving duplicate Program Head listings.
  - Synchronized `headFilter` (assigned/unassigned) query logic on the Departments Page to check both leadership pointers and linked employee relationships.

### Fixed
- **Duplicate User Accounts & Uniqueness Constraints**:
  - Created migration `2026_08_13_000002_cleanup_duplicate_users_and_add_unique_constraint.php` merging duplicate user accounts sharing `employee_id` and added `UNIQUE` constraints on `users.employee_id` and `users.student_id`.
- **Department Unassign Bug**:
  - Fixed issue where setting a department leader to `Unassigned` on the Departments Page failed to persist due to unlinked employee `department_id` fallbacks.

---

## [2026-08-11]

### Added
- **Redesigned Admin Reports Page (No Tables + Integrated AI Analysis)**:
  - Redesigned both the **Summary Report** and **Individual Report** views on `/admin/reports` ([reports.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/reports.blade.php)), replacing data tables with visual scorecards, criteria progress bars, faculty performance grid cards, and AI sentiment analysis blocks.
  - **Summary Report**: Features Executive Metric Scorecards (institutional average, category averages, submission counts), AI Institutional Analysis Block (overall sentiment progress bar + executive summary text), and Faculty Performance Grid Cards (responsive professor cards with department code, overall score badge, category score pills, and AI sentiment badge).
  - **Individual Report**: Features Profile & Score Scorecards (overall score badge + performance descriptor pill), Criteria Performance Progress Cards (with fill bars and `4.50 / 5.0` badges), AI Sentiment & Feedback Analysis Block (positive/neutral/constructive percentages + key strengths & focus area cards), and Submitted Comments Cards Stream with individual AI sentiment tags.
  - Preserved full single-page document export formatting via `window.print()` with signature blocks.
- **Evaluation Form Draft Persistence (`localStorage`)**:
  - Implemented reactive `localStorage` draft persistence in Alpine (`x-data`) in `evaluation-form.blade.php` so selected 1-5 rating answers, comments, and current question step persist seamlessly across browser reloads, page navigation, and dashboard visits.
  - Stored under unique session keys (`draft_eval_{userID}_{type}_{evaluateeID}_{classID}`).
  - Configured automatic draft clearing upon successful submission (`@evaluation-submitted.window`) or when clicking **Reset All**.
  - Updated progress bar line fill color to `bg-amber-400 dark:bg-amber-400` so it stands out distinctly from the maroon `#800000` header in light mode.
- **Required Comments Validation in Evaluation Form**:
  - Added `required|string|min:3` validation rule for comments before evaluation submission in `evaluation-form.blade.php`.
  - Updated UI label to `Comments & Suggestions *` with red asterisk, updated placeholder to `Share constructive feedback here (required)...`, and added `@error('comments')` message display below the textarea.
- **Global Evaluator Layout & Header Auto-Hide**:
  - Wrapped top dashboard header card in `@if(!$showForm)` across all 5 evaluator dashboards (`student`, `faculty`, `dean`, `program-head`, `staff`) so the dashboard header banner hides automatically when taking an evaluation.
  - Rendered `<x-admin.navbar />` and `<x-admin.footer />` across all logged-in evaluator roles (`@if(auth()->check())`) in [sidebar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php).
  - Formatted navbar role badges using `ucwords(str_replace(['_', '-'], ' ', $roleRaw))` (e.g. `Logged as Student`, `Logged as Faculty`, `Logged as Dean`, `Logged as Program Head`, `Logged as Staff`).
  - Fixed notification badge alignment on bell icon in [navbar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/admin/navbar.blade.php).
- **Evaluator Dashboard Table Cell Cleanliness**:
  - Simplified table columns across Student, Faculty, Dean, Program Head, and Staff dashboards so `Name` and `Subject` columns contain strictly single-line strings without auxiliary employee IDs, departments, or schedule sub-texts.
- **Single-Question Interactive Evaluation Wizard**:
  - Re-architected `resources/views/livewire/evaluation-form.blade.php` across all 6 evaluator portals (Students, Faculty, Staff, Program Heads, Deans, Self) from a single long-scrolling form into a focused single-question evaluation wizard.
  - Features a real-time evaluator progress header (`X/11 Answered • X%`), an interactive question navigator grid with number pills (`1`, `2`, `3`...) and completion checkmarks, 300ms smooth auto-advance upon selecting a rating, enlarged high-contrast horizontal rating buttons (`1`–`5`) with theme-tuned hover states, a stylized rating scale legend, and a final **Summary & Review** screen with criterion breakdown matrix, skipped question alerts, live profanity-filtered comments textarea, and background job queue submission (`ProcessEvaluationSubmission`).
- **Collapsed Mini Sidebar & Navigation Enhancements**:
  - Added collapsible icon-only mini sidebar mode with dark red (`#800000`) GRC logo asset, centered profile avatar in mini mode, right-aligned hover tooltips across all sidebar links, dark red active page indicator background (`#800000`), and automatic hiding of group headings (`x-show="!sidebarCollapsed"`) in [group.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/flux/navlist/group.blade.php).
- **Admin Navbar & Theme Mode Persistence**:
  - Styled Admin Header with dark red background (`#800000`) and replaced `Admin Portal` text with dynamic `Logged as Admin` (or user role) badge in [navbar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/admin/navbar.blade.php).
  - Integrated native Livewire Flux `$flux.appearance` Light/Dark mode switcher with Half Moon (Light Mode) and Sun (Dark Mode) icons, backed by `<head>` inline initialization script in [head.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/partials/head.blade.php) and `livewire:navigated` listener for persistent dark mode across full page reloads and Livewire SPA transitions.
  - Added dynamic unread notification count badge on notification bell icon with dropdown preview menu.

### Fixed
- **Alpine Template Single Root Wrapper Fix**:
  - Wrapped multi-question iteration inside a single root `<div>` container under Alpine `<template x-if="!isReviewStep">` in `evaluation-form.blade.php`, resolving DOM element detachment bugs when stepping to question 2+.
- **Blade Constant Evaluation & Button Styling**:
  - Replaced single-colon `:disabled` with double-colon `::disabled` on `<flux:button>` to prevent Blade from attempting to evaluate Alpine JS expressions as PHP constants.
  - Fixed Next Question button styling with explicit dark red background (`bg-[#800000] text-white hover:bg-[#990000]`), eliminating plain white button background artifacts.

---

### Added
- **Subject Catalog Curriculum Metadata**:
  - Added `year_level` (*1st Year* to *4th Year*) and `semester_offered` (*1st Semester*, *2nd Semester*, *Summer*) to the `subjects` table via migration `2026_08_08_000002_add_year_level_and_semester_offered_to_subjects_table.php`.
  - Added curriculum metadata fields, table badges, search/filter controls, and sorting in [manage-subjects.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-subjects.blade.php).
- **800 BSIT Student Roster Seeder**:
  - Created `database/seeders/EightHundredStudentsSeeder.php` generating 800 BSIT students split equally (200 each) across Year Levels 1–4 with random full names, suffixes (`Jr.`, `Sr.`, `III`, etc.), sections (`BSIT-1A` through `BSIT-4D`), and user accounts.
- **Fast 1-Click & Multi-Select Class Enrollment**:
  - Added 1-Click Smart Section Enrollment (`enrollMatchingSectionStudents()`) enrolling all unenrolled section students in 1 click.
  - Added multi-select candidate checkboxes with **Select All Candidates** toggle and **Enroll Selected (X)** batch action.
  - Added Program & Year Level modal filter dropdowns and an **Unenroll All** quick reset option.
- **Sidebar Active Term Badge**:
  - Added dynamic **Active Term** badge in [sidebar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php) displaying active academic year and shortened semester (e.g. `2025-2026 • 1st Sem`).
- **Subtle Custom Scrollbars**:
  - Added custom subtle 5px translucent rounded scrollbar styling in `resources/css/app.css` across light and dark modes for WebKit and Firefox (`scrollbar-width: thin`).

### Fixed
- **Duplicate Subject Code Support**:
  - Removed `unique:subjects,code` validation rule from Livewire subject management actions and dropped DB unique index on `subjects.code` via SQLite table reconstruction migration `2026_08_08_000003_drop_unique_constraint_on_subjects_code.php`.
- **Responsive Table Filter Bar Alignment**:
  - Re-aligned main table filter controls on `/admin/subjects` and `/admin/classes` from 1-column stacked list into an inline flex bar with a `grid-cols-2 md:grid-cols-4` responsive layout.
- **Student Enrollment Modal Viewport & Flexbox Scrolling**:
  - Constrained student management modals on Classes and Programs pages to compact max widths (`580px` / `540px`) and `max-height: calc(100vh - 3.5rem)`.
  - Added sticky headers and footers (`sticky z-10`), CSS flexbox `min-h-0` on `flex-1` modal body container, and `overflow-y-auto min-h-full` on backdrop wrapper to prevent off-screen clipping and ensure smooth internal scrolling.

---

## [2026-08-07]

### Added
- **Institutional Rankings Page (`/rankings`)**:
  - Added a dedicated **Rankings** page positioned directly below **Results** in the main sidebar ([sidebar.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/components/layouts/app/sidebar.blade.php)).
  - Features 4 top summary metric cards styled with 5px dark red (`#800000`) left borders and live `<x-odometer>` counters (Top Performing Faculty, Highest Rated Department, Faculty Monitored, Institutional Mean Rating).
  - Two primary leaderboards: **Faculty Leaderboard** (ranked with 🥇, 🥈, 🥉 badges, department badges, evaluations count, composite rating, and performance descriptor badges) and **Department Leaderboard** (ranked department averages, dean assignment, and faculty count).
  - Search input bar, Department Filter dropdown, and Sort selector (*Highest Rating First*, *Lowest Rating First*, *Most Evaluations*).
- **Completion Tracking Monitoring Dashboard Redesign**:
  - Redesigned the **Completion Tracking** page ([manage-evaluations.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/manage-evaluations.blade.php)) into a comprehensive multi-perspective evaluation monitoring dashboard.
  - Added 4 top summary metric cards styled with 5px dark red (`#800000`) left borders and live `<x-odometer>` counters (Total Submissions Received, Student Progress %, Supervisor Ratings %, Self Appraisals Done).
  - Multi-perspective navigation tabs: **Student Upward Progress**, **Supervisor & Executive Ratings**, and **Self Appraisals**.
  - Built-in search input bar, Department Filter dropdown, Status Filter (`All`, `100% Completed`, `In Progress`, `Pending`), and "Send Reminders" action button.
- **Evaluation Weight Score Card**:
  - Added a dedicated **Evaluation Weight Score Card** to the Evaluation Settings page ([evaluation-settings.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php)).
  - Features a 5px thick dark red (`#800000`) left border, live percentage distribution calculation across all 5 evaluation targets (Student Upward, Employee Upward, Supervisor Downward, Peer, and Self), color-coded progress bars, real-time balance status indicator, and grand total target score summary.
- **Admin Programs Management Page**:
  - Added a dedicated `/admin/programs` page positioned directly below **Departments** in the main sidebar.
  - Features 4 top summary metric cards styled with 5px dark red (`#800000`) left borders and live `<x-odometer>` counters (Total Academic Programs, Assigned Program Heads, Program Students, Active Departments).
  - Search input bar, Department Filter dropdown, Program Head assignment filter, and multiple sort modes.
  - Table displaying Program Code, Name, Department, Program Head, Enrolled Students count badge, and `<flux:dropdown>` Action menu.
  - Removed duplicate/redundant Department and Program cards from the Evaluation Settings view.
- **Admin Departments Management Page**:
  - Added a dedicated `/admin/departments` page positioned directly below **Classes** in the main sidebar.
  - Features 4 top summary metric cards styled with 5px dark red (`#800000`) left borders and live `<x-odometer>` counters (Total Departments, Assigned Deans, Academic Programs, Department Faculty).
  - Built-in search input bar and Dean Assignment filter dropdown (`Assigned Dean`, `Unassigned Dean`) with multiple sorting modes.
  - Table displaying Department Code, Name, Assigned Dean, Academic Programs count badge, Department Faculty count badge, and `<flux:dropdown>` Action menu for editing and deletion.
- **Admin Classes Page Enhancements**:
  - Replaced text schedule field with a schedule days selector (`MW`, `TTH`, `FS`, `MWF`, etc.) and start/end time pickers (`type="time"`).
  - Removed the `room` column from the table and form views for a cleaner layout focused strictly on section schedules.
  - Grouped table action items into a clean `<flux:dropdown>` Action menu (Manage Enrollment, Edit Class, Delete Class) matching user management pages.
  - Removed subject code and professor employee ID labels in table columns, displaying clean Subject Name and Professor Name.
  - Added Subject and Professor filter dropdowns alongside Semester and Department search controls.
- **Admin Subjects Page Enhancements**:
  - Top 4 summary statistics cards (Total Subjects, Active Classes Assigned, Total Units Offered, Unassigned Subjects) styled with 5px dark red (`#800000`) left borders and live `<x-odometer>` digit animation counters.
  - Advanced search and filtering bar supporting Units filtering (1 Unit Lab, 2 Units, 3 Units Lecture, 4+ Units Major), Usage Status (Assigned vs. Unassigned), and multiple sorting modes (Code A-Z/Z-A, Name, Most Units, Most Classes).
  - Interactive **Assigned Classes Modal** displaying all section classes linked to a subject, including semester, assigned faculty member, schedule/room, and enrolled student counts.
- **Admin Dashboard Visual Styling**: Standardized all cards on the Admin Dashboard with a 5px thick dark red (`#800000`) left border.

---

## [2026-08-06]

### Added
- **Unified Employee & Student Portals**: Consolidated user management under `/admin/employees` and `/admin/students` with formatted full names (`Last Name, First Name Middle Name Suffix`).
- **Suffix Database Column**: Added `suffix` column to `employees` and `students` tables via migration `2026_08_06_000000_add_suffix_to_employees_and_students_tables.php`.
- **Admin Role Option**: Added `'admin'` role option in Employee management tabs, badges, forms, and validation.
- **Self-Account & System Safeguards**: Protected active logged-in administrators (`auth()->id()`) from self-disabling or self-deleting with visual `YOU` badges, and added a safeguard preventing the deactivation or deletion of the last remaining active Administrator account.
- **Pending Submissions Tracker**: Added live odometer counter for outstanding expected evaluations on the Admin Dashboard top statistics grid.
- **Role-Based Anonymized Submission Feed**: Transformed the recent submissions activity stream into a clean, role-relationship timeline (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`) protecting evaluator and evaluatee identity.

### Changed
- **Branding & Sidebar Logo**: Replaced default SVG logo with official PNG asset `public/GRC-o-Evaluation-LOGO.png` across header components and sidebar navigation.
- **Admin Dashboard Layout & Simplification**: Redesigned dashboard cards for senior administrative readability:
  - Card 1: Total Employees (`Employee::count()`).
  - Card 2: Total Students (`Student::count()`).
  - Card 3: Current Evaluation Progress (accurate expected formula: student + self + peer evaluations).
  - Card 4: Pending Submissions counter (`max(0, expected - submitted)`).
  - Evaluation Period Status Card: Displays clear status banner (`EVALUATION IS OPEN`/`CLOSED`), explicit date-time labels (`Evaluation Opens (Start)` & `Evaluation Closes (End)`), real-time schedule status indicator (`🟢 Closes in 5 hours`), and specific action button (`⚙️ Change Evaluation Schedule Dates`).
  - Overall Evaluation Feedback & Sentiment Card: Labeled across all evaluator groups (Students, Faculty, Deans, Program Heads, Staff).
- **Dashboard Visual Design**: Standardized all cards on the Admin Dashboard with a full 4-side 2px dark red (`#800000`) border (`style="border: 2px solid #800000 !important;"`).

---

## [2026-06-18]

### Added
- **Larastan Static Analysis**: Integrated PHPStan for Laravel (Larastan) at analysis level 5. Created a baseline configuration file `phpstan-baseline.neon` to capture existing issues, enabling clean runs of `./vendor/bin/phpstan analyse`.
- **Spatie Laravel Activitylog Integration**: Configured database auditing for core models. Automatically records actions (creating, updating, deleting) on `User`, `Evaluation`, `EvaluationQuestion`, `AcademicClass`, `Department`, and `Program` models.
- **Sensitive Field Filtering**: Excluded `password` and `remember_token` from `User` model logs for security, and configured `dontLogIfAttributesChangedOnly()` to prevent empty activity log submissions during password updates.
- **Activity Log Verification Tests**: Created the feature test suite `tests/Feature/ActivityLogTest.php` to verify model action logging, dirty attributes logging, and password exclusion.
- **Welcome Page Issue Reporting Button**: Added a fixed glassmorphic "Report an Issue" button with a warning triangle icon to `welcome.blade.php`, linking to the external reporting site `https://grc-reporting.vercel.app` for quick bug submission.

---

## [2026-06-17]

### Fixed
- **Questionnaire Management Tab Alignment**: Updated the admin `manage-questions` Livewire component to support the five current evaluation types (`upward_student`, `upward_employee`, `downward`, `peer`, `self`) rather than the outdated `student`, `peer`, `self` types.
- **Dynamic Question Rubrics Target Points**: Refactored the questionnaire tabs to load active semester target points configuration dynamically.
- **Helper Defaults & Test Alignments**: Updated the default evaluation type in `Evaluation::getStatus()` to `upward_student` and updated corresponding feature test cases.
- **Test Suite Lazy Loading Support**: Added `\Livewire\Livewire::withoutLazyLoading()` to `beforeEach` hooks in feature tests (`AdminManagementTest`, `ReportsSummaryTest`, `AISentimentCorrectionTest`, `EvaluationStatusDashboardTest`, and `SidebarFeaturesTest`) to disable lazy loading during automated assertions.

### Added
- **Odometer Count-Up Effect**: Implemented a reusable `<x-odometer>` Blade component powered by Alpine.js and vertical CSS translates with a `cubic-bezier(0.34, 1.56, 0.64, 1)` elastic transition (similar to YouTube's live subscriber counter). Integrated the odometer into the Admin Dashboard stats cards (Faculty, Students, Progress, Sentiment score) and the positive, neutral, and negative comment counts in the AI Sentiment analysis panel.
- **Lazy Loading & Shimmer Skeletons**: Integrated lazy loading (`#[Lazy]`) and hardware-accelerated shimmer skeleton loader views across all remaining admin-facing pages (Deans, Program Heads, Staff, Subjects, Classes, AI Sentiment, Questions, Settings, Evaluations, Results, and Analytics) for a consistent loading transition. Added inline table skeleton loaders during search/filter operations.

### Changed
- **System Font Family**: Replaced the default font family `Instrument Sans` with `Inter` across the entire application interface to align with modern web design guidelines.
- **Welcome Page Redesign**: Overhauled the landing page (`welcome.blade.php`) to use the vibrant gradient background image with full cover constraints. The text and a clean containerless brand logo now float cleanly directly on top of a premium cinema background overlay, featuring elegant typography powered by the **Playfair Display** serif font face for the title, and a single centralized primary CTA action button ("Access Portal" / "Go to Dashboard") positioned with spacious vertical padding.

---

## [2026-06-16]

### Added
- **Admin Dashboard Upgrades**: Implemented comprehensive administrative metrics and components:
  - Top row statistics cards detailing total faculty profiles, regular student counts, active evaluation progress, and VADER average sentiment ratings.
  - Active window tracking card with live timers and status badges.
  - Live AI Sentiment Analysis card featuring VADER lexicon comment breakdowns (positive, neutral, and negative ratings).
  - Department completion rates table mapping actual submissions against expected student evaluations.
  - Anonymized timeline feed of the latest 5 evaluation submissions.
  - Quick action panel shortcuts to settings, reports, questions, and accounts.
- **AI Sentiment Correction & Retraining Pipeline**:
  - Built a "Human-in-the-Loop" administration control panel under Volt component `admin.manage-ai` to manually override comment classification predictions (fully anonymized) and display validation metrics (Accuracy and Confusion Matrix).
  - Refactored Flask API model training and predicting to combine text TF-IDF vector features with numerical rating averages for a multimodal Decision Tree classifier.
  - Implemented an Agreement Gate (confidence filter) in Python Flask service to discard conflicting ratings during training when no manual override exists.
  - Expanded and balanced the Excel training seed data in `python/ai_data.xlsx` (sheet `SeedData` to **410 unique comments** with a balanced ~33% split per class; sheet `Lexicon` to **424 words** including 60+ neutral terms and negative Taglish phrases).
  - Achieved a **93.8% validation accuracy** with **0% polarity-swap errors** on the balanced dataset.

### Fixed
- **Evaluation Settings Picker Layout**: Configured the start and end date-time input pickers under "Configure Evaluation Window" to stack vertically and expand on all screen sizes.
- **Admin Dashboard Header Alignment**: Left-aligned the dashboard title and subheading description, and wrapped the Active Period badge in a full-width container to float to the far-right on both mobile and desktop screens.
- **Admin Dashboard Evaluation Counts**: Switched completion statistics and department progress queries from checking the obsolete `'student'` evaluation type to `'upward_student'`.
- **Recent Submissions Activity Feed Mappings**: Updated the anonymized activity feed evaluator labels to support the new evaluation types (`upward_student`, `upward_employee`).
- **Recent Submissions Activity Feed Subject Fallback**: Fixed an issue where non-class-based evaluations (self, peer, downward, upward_employee) fell back to the hardcoded string `'Self Evaluation'` in the feed. They now display their correct evaluation type label dynamically.
- **Evaluation Rate Limiting Adjustments**: Increased the submission rate limit threshold from 5 to 50 attempts per session to accommodate deans, program heads, and faculty completing multiple assignments, with an adjusted decay time of 300 seconds.
- **AI Pipeline Layout Squishing**: Refactored the dashboard grid container to use a robust Flexbox column layout with `min-w-0` to protect the feedback table from width collapse and cell overlapping.
- **AI Pipeline Cell Semantics**: Wrapped the first table cell's contents inside a generic flex `div` wrapper instead of applying `flex` classes directly onto the `<td>` tag.
- **AI Pipeline Comment Anonymity**: Removed the "Participants" column and cleaned up the controller queries to hide the identities of evaluators and targets.

---

## [2026-06-14]

### Added
- **Route & Component Rate Limiting**: Implemented a comprehensive rate-limiting and lockout protection system.
  - Added a `global` rate limiter (100 requests/min) and an `auth` rate limiter (5 attempts/3 mins) inside [AppServiceProvider.php](file:///c:/Users/USER/Herd/evaluationsystem/app/Providers/AppServiceProvider.php).
  - Applied the `throttle:global` middleware to general web routes and `throttle:auth` to public authentication routes (login, forgot password, reset password).
  - Added server-side evaluation submission rate-limiting (5 submissions/3 mins) with client-side reactive countdown alerts (Alpine.js `@entangle`) in [evaluation-form.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php).
  - Created a feature test suite [RateLimitingTest.php](file:///c:/Users/USER/Herd/evaluationsystem/tests/Feature/Security/RateLimitingTest.php).
- **Summary Evaluation Reports**: Implemented a Summary Evaluation Report view in the reports portal.
  - Added toggleable navigation tabs ("Individual Report" and "Summary Report").
  - Enforced role-based accessibility scopes (Admin gets institutional-wide results; Deans and Program Heads see results scoped only to their own department's faculty).
  - Added tabular overview showing Employee ID, Professor Name, Department, Evaluation Type Rating Averages (Student, Peer, Self), Total Submissions, and Overall Score.
  - Designed print-optimized CSS stylesheet layouts and integrated a "Print Summary Report" / "Download PDF" trigger using the browser's native `window.print()` engine.
  - Developed a Pest feature test suite covering authorization, tab switching, department scopes, and average rating calculations.

---

## [2026-06-13]

### Added
- **Evaluation Schedule Removal Protection**: Enforced backend and UI safeguards preventing administrators from clearing or removing evaluation schedules while evaluations are active/open.
- **AI Sentiment Analysis Pipeline**: Implemented a Python Flask API serving VADER sentiment analysis (with Tagalog/Taglish custom lexicon) and a Decision Tree Classifier using TF-IDF feature extraction. Integrated with Laravel queue jobs and database storage via Eloquent models, and added a CLI command `php artisan ai:train` for classification training and historical data backfilling.
- **Automated AI Retraining Schedule**: Configured the `ai:train` command to run automatically daily at midnight via Laravel's console scheduler to continually retrain the classification model on newly submitted reviews.

### Changed
- **AI Port Migration**: Migrated the Python Flask API port from `5000` to `5001` across the Flask app, Laravel CLI training command, queue jobs, and integration tests to resolve port binding conflicts with Windows System services (PID 4).

### Fixed
- **IDE Python Path Resolution**: Configured `.vscode/settings.json`, `pyrightconfig.json`, and `pyproject.toml` settings at both the workspace root and the `python/` directory level to map the Python interpreter and extra paths to the project virtual environment, resolving IDE "Cannot find module" errors.
- **Python Type Warning**: Removed an unnecessary `float()` cast around the `vader_score` variable inside `python/app.py`.

### Security
- **Dependency Vulnerability Fixes**: Added an `overrides` configuration block in `package.json` to lock `esbuild` to `^0.28.1` (resolving `GHSA-gv7w-rqvm-qjhr` RCE vulnerability), eliminating all 4 remaining high-severity audit vulnerabilities without forcing a breaking upgrade of `vite` to version 8.
- **Vulnerable Package Updates**: Upgraded 9 Composer dependencies to resolve 14 active security advisories (including high-severity CRLF SMTP injection in `symfony/mime` and medium-severity validation bypass in `symfony/http-kernel`).
- **Auth Endpoint Rate Limiting**: Added `throttle:5,1` rate limiting to the public guest authentication routes (`forgot-password` and `reset-password`) to prevent SMTP resource abuse.
- **AI Service Key Authorization**: Implemented header key verification on Python Flask server and configured Laravel client headers to transmit the `X-API-KEY` token on all AI requests.
- **AI Configuration Extraction**: Moved the Flask API base URL from hardcoded string to `.env` variables (`AI_API_URL` and `AI_API_KEY`).

---

## [2026-06-10]

### Added
- **Transactional Account Deletion**: Enabled secure database-transactional user account deletions for Deans, Program Heads, Faculty, Staff, and Students via the Admin portal.
- **Advanced User Filters**: Added a "None" option to Department filters (for fast sorting) and added Program and Year Level filters on the Students page.
- **Searchable Select Element**: Built a custom, Alpine.js-powered `<x-searchable-select>` component to replace standard HTML select dropdowns in large-list views (e.g., reports, student management, and classes).
- **Destructive Action Confirmation Modals**: Deployed a reusable `<x-confirmation-modal>` component displaying deletion targets and cascading warnings across all destructive admin actions.

### Fixed
- **Persistent Sidebar Badge Count**: Fixed an issue where the notifications badge count remained active in the sidebar by capping dynamic notification timestamps to clear instantly upon landing on the notifications page.

---

## [2026-06-04]

### Added
- **Subject & Class CRUD**: Built single-file Livewire Volt pages for CRUD operations on Subjects and Classes (with scrollable student enrollments).
- **Dashboard Tab Routing**: Added URL-bound tab routing for Dean and Program Head dashboards to persist active state across page reloads.
- **Schedule Window Modals**: Added overlap confirmation and removal protection modals for scheduling active evaluation windows.

### Changed
- **Sidebar Reorganization**: Replaced flat "Manage Evaluations" with a collapsible "My Evaluations" dropdown menu for evaluator roles.
- **Timezone Alignment**: Configured default application timezone to `Asia/Manila` to ensure evaluation windows sync precisely with local server time.

---

## [2026-06-01]

### Added
- **Profanity toast animations**: Built custom CSS shake animations for the constructive profanity warning filter.

### Fixed
- **Livewire DOM Collision**: Fixed rating button collisions (1–5 scale buttons) by implementing Alpine.js rating button state handler.
