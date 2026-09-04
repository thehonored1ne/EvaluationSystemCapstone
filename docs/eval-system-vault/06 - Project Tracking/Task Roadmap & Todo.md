---
title: "Task Roadmap, Backlog & Completed Milestones"
category: "Project Tracking"
tags: [tracking, todo, roadmap, milestones, tasks]
created: 2026-08-28
last_updated: 2026-08-28
---
> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Suggestions & Backlog]] • [[Changelog]]

- [ ] In Manage Subjects Page

- fix and redesign the 3 cards, it needs to tell useful information to admin.

- [ ] in Manage Classes & Enrollment Page

- make the table default sort to alphabetical a-z of the assigned professor last name.
- remove the all professors filter and replace it with a sort by subject name, a-z and z-a option
- regarding the all subject filter. in the dropdown i noticed that you combine subject code and name, make it only name so it doesn't look crowded.
- remove the schedule column because it don't see it useful in this system and to the admin. remove also the schedule picker when adding a new class.

- [ ] in  Manage Departments Page

- fix and redesign the 4 cards, it needs to tell useful information to admin.
- in the table "head" column, the column name just said head but the rows in it shows the head name, the id, and the assigned dean. remove the last 2 and just show the head name for cleaner
- in the members column, the row in it have pills right? add a function that when that pill click it will show a modal, the modal will show the list of that members.
- fix the table column spacing

- [ ] in  Manage Academic Programs Page

- fix and redesign the 4 cards, it needs to tell useful information to admin.
- fix the all department filter. right now it shows even the administrative dept. since academic programs is only for academic, so the filter should only show the academic dept

- [ ] in  Completion Tracking page

- fix and redesign the 4 cards, it needs to tell useful information to admin.
- make the send reminders button have the same function that in the admin dashboard.
- analyze if there's a bug in calculations.
- there's a problem with the all departments filter. I'm now in student tab, and when i click other depts it shows nothing which is good but the filter needs some work.

- [ ] in Results page

- the table and the layout doesn't take space like other space that's why there's a large gap in left and right.
- the total submissions column need some rework because its vague and doesn't show useful info.
- in breakdown modal, the Overall Mean Score is ok, but the next 2 cards is not specific and the information its trying to tell is vague. in  Evaluation Breakdown by Source, the cards need some rework and redesign the information its trying to tell is unclear. instead of showing Feedback Comments & Notes which shows a lot of comments. we have pipeline right we can replace it with that and it will tell the admin about the analysis of ai about the comments he/she received. below it add a summary on what's the information trying to tell. include our new tfidf

- [ ] in rankings page

- in all department filter. the option. instead of showing code and dept name, just the code is enough.
- analyze the 4 cards, if it already telling useful information, or it need some redesign and rework. tell me first.
- the evaluations column, first the column name and the row of it is vague. tell me what you think

- [ ] my concern need to verify

- if the student or an evaluator already evaluated and somehow hes assigned to wrong person, when updating, will the system prevents me because he has evaluation record in current sem? or theyre are way to scrap his evaluation so he can assign to correct person and just redo the evaluation? confirm it for me.

- [ ] align the skeleton loaders to the content of the page 1:1, the skeleton loaders should show the simplicity the shape of the component or the parent container for simplicity. the admin dashboard is done btw.
- [X] **Rankings Bug Fix, Evaluation Type Unification & Custom Error Pages (2026-08-30)**:

  - Removed demo fallback / synthetic score generator in `rankings.blade.php` that fabricated scores (3.50–5.00) and evaluation counts on terms with zero evaluations.
  - Implemented proper `No Evaluations` status badge (`variant="zinc"`), `0 evals`, unranked display (`—`), and `N/A` for summary KPI cards when no evaluations exist.
  - Decoupled Top Performing KPI cards (Top Faculty & Top Department) from table sort order so they always display the true highest-rated performers.
  - Preserved true performance rank across all table sorting modes ("Lowest", "Most Evaluations") without incorrectly awarding 🥇🥈🥉 medals to lower-rated faculty.
  - Resolved evaluation type mismatches across `User.php` notifications / pending counters and `manage-evaluations.blade.php` completion tracking queries for `dean`, `program_head`, `department_head`, and `upward_employee`.
  - Added Faculty Program Head supervisor evaluation tracking in `User.php` and `manage-evaluations.blade.php`.
  - Added multi-department Supervising Dean assignment in `manage-departments` and role-aware supervised department badges in `manage-employees`.
  - Remediated all silent error suppression / empty catch blocks across `manage-classes`, `manage-subjects`, and `Evaluation.php`.
  - Designed and deployed custom branded error pages (`resources/views/errors/`) for 404, 403, 419, 500, and 503 in pure light mode with custom illustrations and automated tests.
  - Added Pest feature tests in `RankingsEmptyAndActiveSemesterTest.php`, `RoleEvaluationTypesCompletionAndNotificationTest.php`, `AdminManagementTest.php`, and `CustomErrorPagesTest.php`.
- [X] **Codebase N+1 Query Resolution, Obsidian Vault Migration & Agent Governance (2026-08-28)**:

  - Eliminated N+1 queries in `app/Models/User.php` (`getNotifications()` & `countPendingEvaluations()`) by pre-fetching class IDs and completed evaluations via a single batch query.
  - Optimized `Evaluation::getStatus()` with in-memory memoization cache (`self::$statusCache`) and batch queue inspection.
  - Added eager-loading for `teacher.department` in `resources/views/livewire/admin/manage-classes.blade.php`.
  - Refactored `SendEvaluationDeadlineReminders.php` to delegate count checks to the optimized `User` model.
  - Regenerated PHPStan baseline (0 errors) and passed all 136 Pest tests and Pint linting.
  - Migrated project documentation into an organized Obsidian Vault (`docs/eval-system-vault/`) with Map of Content and visual canvas.
  - Modularized agent rules in `.agents/rules/` and added mandatory post-task documentation update rule.
- [X] **Cloud Production Deployment, Seeder Scalability & Evaluator Bug Fixes (2026-08-26)**:

  - Containerized full stack on **Render Web Service** (`Dockerfile`, `supervisord.conf`, `nginx.conf`, `entrypoint.sh`): Nginx, PHP 8.3 FPM, Python Flask AI (`127.0.0.1:5001`), and Laravel queue workers running concurrently in a single multi-service container.
  - Linked to a high-availability **TiDB Cloud Serverless MySQL** database in AWS Singapore (`ap-southeast-1`) with mandatory TLS/SSL certificate verification (`MYSQL_ATTR_SSL_CA`).
  - Configured reverse-proxy trusted proxies (`$middleware->trustProxies(at: '*')`) and forced HTTPS URL asset scheme.
  - Converted `employees.role` column to `string('role', 50)` to support `department head` on MySQL strict mode without truncation.
  - Streamed `EvaluationPhase2Seeder.php` with `AcademicClass::chunk(25)` and periodic `$flushAll()` batch database insertions every 200 evaluations, reducing RAM consumption from **> 500 MB down to < 35 MB** on 512 MB cloud tiers.
  - Fixed Issue #24: Resolved serialized answer matching bug in `Evaluation::getStatus()` by enforcing exact serialized property delimiters (`\"evaluateeId\";i:X;` and `\"classId\";i:X;`).
  - Fixed 76-question bug across Dean (30), Program Head (30), and Dept Head (16) forms by splitting generic `'downward'` into explicit instruments.
  - Enabled College Deans with `department_id = null` to view faculty across all academic colleges in `reports.blade.php`.
  - Restricted `/manage-evaluations` strictly to `role:admin`.
  - Enforced `isReadyToSubmit` in `evaluation-form.blade.php`: submit button stays disabled until all questions are rated AND a comment of $\ge 3$ characters is provided.
  - Removed redundant print button from student completion proof card, maintaining clean 15-digit reference ID copy button.
  - Fixed sidebar sublist text overflow, container boundary clipping, and label hierarchy.
- [X] **Evaluation UX, Mobile Responsiveness & N+1 Database Optimizations (2026-08-25)**:

  - Added *"Jump to Missing Question →"* button and smooth auto-centering scroll for rating number pills in `evaluation-form.blade.php`.
  - Guarded evaluation form submit button against false-positive loading animations when incomplete using client-side Alpine checks and `pointer-events-none`.
  - Replaced single-line review matrix text truncation with `line-clamp-2 sm:truncate leading-snug` to prevent clipping question names on mobile.
  - Reduced outer viewport padding (`px-2 sm:px-4 md:px-6 py-3 sm:py-6`) across `evaluation-form.blade.php` and all 6 evaluator portals (`student`, `faculty`, `staff`, `dean`, `department-head`, `program-head`).
  - Pre-loaded `EvaluationCriterion` in `reports.blade.php` to eliminate 4 redundant database queries and added responsive horizontal table scroll constraints (`min-w-[720px]`, `min-w-[650px]`).
  - Eliminated N+1 queries in `evaluation-results.blade.php` table render loop with grouped `whereIn` submission count aggregations, and optimized modal details query from 10 queries down to 1.
  - Converted role tabs in `manage-evaluations.blade.php` to a full-width responsive grid (`grid-cols-2 sm:grid-cols-3 lg:grid-cols-6`), eliminating dead whitespace.
  - Added `min-w-[700px]` constraints to Faculty and Department leaderboards in `rankings.blade.php`.
  - De-cluttered UI by removing redundant page-level and card-level filler subheadings across all admin management views while preserving dynamic counters, and synced all 11 skeleton loader placeholders to match live page headers 1:1.
- [X] **System Performance, Database Composite Indexes & Caching Layer (2026-08-23)**:

  - Re-architected **Manage Evaluations & Completion Tracking** (`manage-evaluations.blade.php`) to use direct SQL joins and pre-aggregations (`DB::table(...)` with `groupBy`), dropping models loaded in memory from **11,867 down to 0 models** and query statements from **4,859 queries down to ~10 queries**.
  - Added static memoization in `EvaluationReferenceService.php` to prevent repeated `Semester` lookups during reference ID generation.
  - Added application-level caching via `Cache::remember` for active semester (`Semester::getActive()`), departments (`Department::getCachedList()`), evaluation criteria (`EvaluationCriterion::getForTypes()`), and tracking summary statistics.
  - Created and ran composite database index migration `2026_08_23_000001_add_performance_composite_indexes.php` on `employees`, `evaluations`, `classes`, and `semesters`.
  - Deleted orphaned `analytics.blade.php` and its skeleton placeholder and removed `/analytics` route from `routes/web.php`.
- [X] **Terms of Service & Privacy Modal UI/UX Redesign (2026-08-23)**:

  - Redesigned `terms-modal.blade.php` to eliminate duplicate headings, doc IDs, and bureaucratic jargon.
  - Formatted policies into clean card components with Lucide/Flux icons across Terms of Service and Privacy & AI Disclosure tabs.
- [X] **Sidebar UI/UX, Tooltip & Zero-Flicker Navigation (2026-08-23)**:

  - Removed native browser `title` tooltips and isolated `<flux:tooltip>` to collapsed icon-only mode.
  - Configured collapsed accordion dropdowns (`Manage Users`, `My Evaluations`) to auto-expand the sidebar on click.
  - Fixed `pointer-events` blocker on expanded sidebar navigation items.
  - Implemented zero-flicker CSS architecture (`body.sidebar-animating`, `html.sidebar-is-collapsed`) to prevent layout flashes on `wire:navigate`.
- [X] **Accessibility & Google Lighthouse Compliance (2026-08-23)**:

  - Added descriptive `aria-label` tags across navigation links, accordion triggers, submenus, theme switcher, user profile, and notification dropdown.
  - Added `fetchpriority="high"` and `decoding="async"` to logo image in `app-logo.blade.php` to eliminate Cumulative Layout Shift (CLS).
- [X] **Dependency Security Audit Vulnerability Fixes (2026-08-23)**:

  - Fixed 100% of vulnerabilities reported by `composer audit` and `npm audit` (Guzzle, PSR-7, CommonMark, Axios, PostCSS, Nanoid).
  - Validated with full Pest feature test suite (130 passing tests, 621 assertions) and PHPStan (0 errors).
- [X] **System-Wide Database Performance Optimization & Query Auditing (2026-08-21)**:

  - Installed and configured `barryvdh/laravel-debugbar` for deep query profiling, statement count verification, and memory tracking.
  - Optimized **Admin Dashboard** (`admin/dashboard.blade.php`): dropped models in RAM from 139,039 to 171, query statements from 128 to 34, and eliminated 102 duplicate queries using direct SQL `GROUP BY` aggregations and request memoization.
  - Optimized **Manage Employees** (`manage-employees.blade.php`): replaced 7 `whereHas` count subqueries with 1 grouped role aggregation query (38 queries $\rightarrow$ 18 queries in 13.4 ms).
  - Optimized **Manage Students** (`manage-students.blade.php`): added eager loading `with(['student.program', 'roles'])` to eliminate row-level N+1 queries.
  - Optimized **Completion Tracking** (`manage-evaluations.blade.php`): replaced 946-class correlated subqueries with 2 indexed hash maps and replaced tab counts with single SQL query `getCategoryCountsProperty()`, dropping load time from 4.14s down to 470ms (9x faster).
  - Optimized **Rankings** (`rankings.blade.php`): eliminated per-faculty N+1 evaluation loop with a single grouped query (113 queries $\rightarrow$ 10 queries in 37 ms).
  - Optimized **Reports & Summary Analytics** (`reports.blade.php`): removed 11,000 unneeded models from Individual Reports and refactored Summary Reports across 23,000 evaluations using direct SQL `GROUP BY` and `HAVING` filters (10.04s $\rightarrow$ 0.9s, memory 107 MB $\rightarrow$ 10 MB).
  - Optimized **All Evaluator Dashboards** (`Evaluation.php`): added per-request evaluator memoization in `Evaluation::getStatus()`, batch-fetching completed submissions and queue jobs in 1 query (Student Dashboard: 65 queries $\rightarrow$ 9 queries in 6 ms).
- [X] **Google Lighthouse Full Optimization & Standards Compliance (2026-08-21)**:

  - Achieved **100/100 Best Practices, 100/100 SEO, 95/100 Accessibility, 95/100 Performance**.
  - Added font display swap (`&display=swap`), script deferral (`defer`), explicit SVG dimensions (`width="220" height="72"`), semantic `<main id="main-content">` landmarks, accessible `aria-label` select controls, and boosted text color contrast.
- [X] **Security Hardening & SecurityHeadersMiddleware (2026-08-21)**:

  - Implemented `SecurityHeadersMiddleware` attaching `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Cross-Origin-Opener-Policy`, `Permissions-Policy`, and `HSTS`.
  - Verified 8-point institutional checklist: XSS sanitization, MIME-type file upload validation, rate limiting on routes, role-based row-level scoping, private database access, and production error trace masking.
- [X] **System-Wide 1:1 Skeleton Loader & Shimmer Animation Overhaul (2026-08-21)**:

  - Enhanced CSS shimmer sweep animation with GPU hardware acceleration (`will-change: transform`, `transform: translateZ(0)`), increased dark-mode contrast (`rgba(255, 255, 255, 0.14)`), and `@media (prefers-reduced-motion: reduce)` accessibility fallback.
  - Upgraded `<x-skeleton>` component primitives with dedicated presets: `stat-card` (with 5px left-border `#9b0000` / `#f89696`), `chart`, `table` with proportional columns, `badge`, `button`, and `wizard`.
  - Rebuilt all admin placeholders to match their real pages 1:1, eliminating Cumulative Layout Shift (CLS): Admin Dashboard, Evaluation Settings (4-section layout), Reports & Analytics, Manage AI, Manage Departments, Manage Programs, Manage Classes, Manage Subjects, Manage Employees, Manage Students, Manage Questions, Completion Tracking, Evaluation Results, and Rankings.
  - Added Livewire `#[Lazy]` and dedicated 1:1 skeleton placeholders across all evaluator role portals: Student Dashboard (enrolled classes cards grid) and Faculty, Dean, Program Head, Department Head, Staff dashboards (progress badges, tabs, and tables).
  - Validated 100% pass across all 125 feature tests (601 assertions) and Pint styling.
- [X] **Questionnaire Parts Edit & Action Dropdown (2026-08-20)**:

  - Replaced the standalone trash icon on all Questionnaire Parts rows in `evaluation-settings.blade.php` with an action dropdown (`flux:dropdown`) containing **`Edit Part`** and **`Delete Part`**.
  - Added an interactive **Edit Questionnaire Part Modal** allowing admins to rename criteria parts and update max point allocations without deleting existing questions.
  - Added automated test coverage in `EvaluationSettingsAndEnhancementsTest.php`.
- [X] **Admin Dashboard Full-Coverage Audit Log & Activity Stream (2026-08-20)**:

  - Added a dedicated **Audit Log** card sitting side-by-side with the **Recent Submissions Log** in a responsive 2-column grid with fixed card heights (`h-[480px]`) and smooth scrollable content (`overflow-y-auto pr-2`).
  - Integrated Spatie `LogsActivity` across all system models (`User`, `Employee`, `Student`, `Subject`, `AcademicClass`, `Department`, `Program`, `Semester`, `AcademicYear`, `EvaluationQuestion`, `EvaluationCriterion`).
  - Added explicit activity tracking for bulk CSV imports (Employees, Students, Classes/Rosters), AI model retraining, and deadline reminder broadcasts.
  - Implemented natural language activity formatters (e.g. *"Created user account for Maria Clara (maria@grc.edu.ph)"*, *"Added new department: College of Computer Studies (CCS)"*, *"Bulk imported 45 new students via CSV"*).
  - Filtered out internal background timestamp noise (`notifications_last_viewed_at`, `dismissed_notifications`, `password_changed_at`, `remember_token`, `updated_at`).
  - Added comprehensive automated test coverage in `ActivityLogTest.php`.
- [X] **Recent Submissions Log Role Mapping Polish (2026-08-20)**:

  - Fixed evaluator relationship mapping in `admin/dashboard.blade.php` so Dean evaluating Professor or Program Head properly displays the badge title **`Dean Evaluation`** instead of falling back to generic `Evaluation`.
  - Added support for Department Head evaluations (`"Department Head Evaluation"`).
- [X] **Training & AI Controls Admin Role Protection (2026-08-20)**:

  - Restricted Account Settings Training navigation link (`layout.blade.php`), route middleware (`routes/web.php` with `role:admin`), and component mount guard to admin users only.
  - Added automated test cases in `EvaluationSettingsAndEnhancementsTest.php`.
- [X] **Bulk Import Modal UI Polish (2026-08-20)**:

  - Repositioned Download Template button next to spreadsheet upload input across Employee, Student, and Class management modals to eliminate overlap with modal close button.
- [X] **Evaluator Dashboard Progress Counter Badges (2026-08-20)**:

  - Positioned `X/Y evaluated` progress badges on the far right of card headings across all 6 evaluator portals (Student, Faculty, Dean, Program Head, Department Head, Staff).
- [X] **Default Password Security Warning Modal (2026-08-20)**:

  - Added database column `password_changed_at` to `users` table and `User::isUsingDefaultPassword()` detection method.
  - Built Livewire security advisory modal (`default-password-modal.blade.php`) notifying users using default credentials (`password`) with quick navigation to `/settings/password`.
  - Added "Later" snooze and "Don't show again during this session" options with session-based dismissal tracking.
  - Ensured the modal re-appears upon future logins and permanently resolves once the user updates their password.
  - Added full feature test suite in `DefaultPasswordModalTest.php`.
- [X] **Mobile Responsive UI Overhaul & Sidebar Polish (2026-08-19)**:

  - Fixed mobile sidebar toggle button (`flux-sidebar-toggle`) and forced full GRC institutional logo on mobile devices.
  - Optimized Account Settings (Profile, Password, Appearance, Training) for fluid 1-column mobile layouts.
  - Standardized filter dropdown widths across Rankings, Completion Tracking, and Reports (`w-full sm:w-auto`).
  - Polished Notification dropdown on mobile with `whitespace-nowrap` on action buttons and responsive width.
- [X] **Completion Tracking Pagination across All 7 Categories (2026-08-19)**:

  - Added Livewire pagination (`WithPagination`) across all 7 tracking tabs in `manage-evaluations.blade.php`.
- [X] **PDF & Print Clean Export (2026-08-19)**:

  - Hid Admin Navbar, Sidebar, Footer, and UI chrome when saving reports as PDF (`print:hidden` and `@media print` rules).
- [X] **Evaluator Dashboard Tables Scrollability (2026-08-19)**:

  - Enabled smooth vertical and horizontal scrolling (`overflow-auto max-h-[500px]`, `sticky top-0 z-10` header) across Student, Faculty, Staff, Dean, Department Head, and Program Head dashboards.
- [X] **Evaluation Form Mobile UI & Submit Button (2026-08-19)**:

  - Fixed clipped Submit button (`Su...`) and review controls in `evaluation-form.blade.php` (`flex-col-reverse sm:flex-row`).
  - Replaced rating buttons layout with responsive 5-column grid (`grid grid-cols-5`) to eliminate clipping on mobile view.
- [X] **Phase 1 Master Dataset Architecture & Seeding (2026-08-19)**:

  - Populated 124 institutional employees (1 Dean, 11 Department Heads, 4 Program Heads, 50 Faculty Professors, 57 Administrative Staff) across 15 departments (4 Academic, 11 Administrative).
  - Populated 3,200 active students (800 CCS, 800 COA, 800 COE, 800 CBAE) across 13 academic programs.
  - Cataloged 176 unique academic subjects and generated 946 section classes with 29,291 student enrollments.
  - Standardized 130 evaluation questions across 21 parts across all 7 evaluation categories (`upward_student`, `self`, `dean`, `program_head`, `peer`, `department_head`, `upward_employee`).
  - Calibrated the Teaching Effectiveness formula allocation in evaluation settings (Student 40%/80pts, Dean 20%/40pts, Program Head 20%/40pts, Peer 15%/30pts, Self 5%/10pts $\rightarrow$ 200 Max Scale).
- [X] **Phase 2 Authentic Evaluation Population & Demonstration Dataset (2026-08-19)**:

  - Created high-speed batch seeder `EvaluationPhase2Seeder.php` generating 23,056 evaluations, 499,222 question ratings, and 23,056 VADER/Decision Tree sentiment records.
  - Pre-computed overall scorecards for all 124 employees in `evaluation_summaries`.
  - Reserved $\approx 20\% - 25\%$ pending evaluation queues across Students, Deans, Program Heads, Faculty, Department Heads, and Staff for live presentation testing.
  - Authored a multilingual bank of realistic reflections in English, Taglish, and Filipino across 70% positive, 20% neutral/constructive, and 10% negative sentiments.
- [X] **Authentication UI Overhaul & High-Contrast Design System (2026-08-19)**:

  - Enforced solid white card background, visible input borders, and high-contrast dark text on login and password reset forms, completely decoupled from dark mode tint conflicts.
  - Standardized form action buttons and labels to official GRC deep red branding (`#7a0000` / `#9b0000`).
  - Added interactive password visibility toggle eye icons and case-insensitive login support for email, student number, employee number, and admin aliases.
- [X] **Evaluation Review Banner & Dark Mode Contrast Fix (2026-08-19)**:

  - Corrected dark mode color contrast in `evaluation-form.blade.php` summary review completion banners and unanswered question badges.

- need to update the summary result more, lets make a report generation that can be exported to pdf with all the data in the evaluation. create a separate table to store the evaluation results that can be used for reporting purposes. should show individual result and summary result.
- weights for calculation of overall rating should be customizable per evaluation type by admin. create a section in evaluation settings to configure this. still thinking how this applies

- [X] Evaluation type check configurations update (Peer, Upward, Downward, Self), still need through planning:

  | **Type**      | **Who Evaluates** | **Who Gets Evaluated** |
  | ------------------- | ----------------------- | ---------------------------- |
  | Peer Evaluation     | Same level/rank         | Same level/rank              |
  | Upward Evaluation   | Subordinate             | Superior                     |
  | Downward Evaluation | Superior                | Subordinate                  |
  | Self Evaluation     | Yourself                | Yourself                     |

  Dean - can evaluate all program heads(downward), can evaluate self(self)
  Program Head - can evaluate their subordinate(downward - faculty), can evaluate self(self), can evaluate superior(upward - dean)
  Faculty - can evaluate their peers(faculty - faculty), can evaluate superior(upward - program head on their department), can evaluate self(self)
  Student - can evaluate their superior(upward - faculty)
  Staff - can evaluate their superior(upward - program head), can evaluate self(self)
- [X] **GRC Summary of Faculty Performance Evaluation on Teaching Effectiveness (2026-08-15)**:

  - Replicated exact 2-page print-ready Global Reciprocal Colleges (GRC) evaluation document format in `reports.blade.php`.
  - Page 1: Official GRC scorecard with big system logo asset `GRC-o-Evaluation-LOGO.png`, Roman numeral criteria parts, 360° Peer Evaluation integration (40% Student, 20% Dean, 20% Program Head, 15% Peer, 5% Self), 200-point GRC Legend table, Overall Rating Box, and official signatures (*Prepared by*, *Noted by*, *Approved by*).
  - Page 2: AI Student Comments Analysis featuring big system logo, sentiment distribution gauge, bilingual Top Student Commendations & Key Opportunities for Growth, representative student feedback extracts (signatures removed for full NLP focus).
  - Reworked Evaluation Settings Section 3 with dynamic report tab switching for Individual Teaching Effectiveness, Administrative Staff, and Global Targets.
  - Enabled College Deans to evaluate academic faculty/professors on `dean/dashboard.blade.php` and sidebar.
- [X] **Subjects Bulk Data Import, Export & Template (2026-08-19)**:

  - Added Download Template (CSV), Export Subjects (CSV), and Import Subjects supporting CSV and Excel files with validation and toast notifications.
- [X] **Admin Dashboard Visual Analytics (2026-08-19)**:

  - Converted metrics cards to Chart.js visual charts (Ratings Distribution & Department Average Comparison) with modular Alpine.js integration.
- [X] **Evaluation Results Directory & Relational Role Query Fix (2026-08-19)**:

  - Built paginated 10/page directory with multi-role filters (Dean, PH, DH, Professor, Staff, Student) and detailed breakdown modal.
- [X] **Global Table Responsive Standardization (2026-08-19)**:

  - Standardized all 8 system tables for 100% full-width desktop view with min-width horizontal scrolling safety on mobile.
- [X] **Evaluation Settings Quick Navigation & Period Table (2026-08-19)**:

  - Added static quick jump navigation bar and paginated academic periods table.
- [X] **Scheduled Automated Deadline Reminders & Notification Engine (2026-08-19)**:

  - Built hourly Artisan command (`evaluations:send-reminders`) supporting milestone tiers (7d, 3d, 24h, 6h) and `--force` execution.
  - Added dynamic deadline urgency alerts in `User::getNotifications()` and centralized pending count calculations in `User::countPendingEvaluations()`.
  - Wired **Send Reminders** action in Completion Tracking (`/manage-evaluations`) to trigger broadcast execution with activity logging.
- [X] **Advanced Leetspeak Profanity Normalizer & Taglish Code-Switching Context Router (2026-08-19)**:

  - Created `ProfanityFilterService` in Laravel with character substitutions (`@` $\rightarrow$ `a`, `1`/`!` $\rightarrow$ `i`, `0` $\rightarrow$ `o`, `3` $\rightarrow$ `e`, `$` $\rightarrow$ `s`), repetition collapsing, and inter-character space/punctuation stripping (`t a n g a`, `g_a_g_o`).
  - Added `detect_language_mode` (English vs Taglish) and Taglish idiom & negation normalizer in Python Flask NLP microservice (`python/app.py`).
  - Added Unit test suite in `tests/Unit/ProfanityFilterTest.php`.
- [X] **Admin Bulk Operations, Enrollment Roster Management & Student Status Lifecycle (2026-08-19)**:

  - **Manage Students (`/admin/students`)**: Added Bulk CSV Import with validation & automated User account provisioning, Download CSV Template, Export CSV, and enrollment status filtering (`Regular`, `Irregular`, `LOA`, `Dropped`, `Graduated`, `Inactive`).
  - **Manage Employees (`/admin/employees`)**: Added Bulk CSV Import supporting all 6 institutional roles (`faculty`, `dean`, `department head`, `program head`, `staff`, `admin`), department code mapping, role sync, and Export CSV.
  - **Manage Classes (`/admin/classes`)**: Added Bulk Class Schedule & Student Roster Import (CSV), Download Template, Export Class Masterlist CSV with enrolled counts and IDs, and automatic `class_student` pivot linking.
- [X] **Admin Dashboard Quick System Actions Hub (2026-08-19)**:

  - Upgraded dashboard quick actions into a responsive 12-action grid covering Completion Tracking, Evaluation Results, Official Reports, Faculty Rankings, Evaluation Settings, Questionnaire Setup, Classes & Rosters, Manage Students, Manage Employees, Subject Catalog, Departments & Programs, and AI Model Training.
- [X] **Completion Tracking Modernization across All 7 Standardized Categories (2026-08-15)**:

  - Aligned all terminologies and tabs across 7 standardized categories: **Student**, **Dean**, **Program Head**, **Department Head**, **Peer**, **Supervisor**, and **Self**.
  - Added dynamic Category Context descriptions matching Question Setup and Settings rubrics.
  - Standardized terminology across headers, KPI cards, tables, and search filters (replacing legacy terms like "Professor", "Subordinate", and "Self Appraisals").
  - Implemented real-time progress calculations for faculty & staff peer evaluations and supervisor ratings.
  - Added multi-criteria filters (Search, Department, Role, and Status) on each tracking tab.
  - Added brand `#9b0000` / `#f89696` 5px left-border accents and odometers on KPI cards.
  - Implemented **Send Reminders** broadcast logging and toast notification.
- [X] **Actionable Evaluation Summary Report Redesign (2026-08-15)**:

  - Added **Faculty Requiring Attention** table surfacing faculty with ratings < 3.50 or negative sentiment ≥ 30% along with department, submissions, sentiment split, and a one-line AI generated reason/theme.
  - Implemented **Turnout / Participation & Data Confidence Rates** (expected vs actual submissions per department and institution-wide with low-confidence flags for <60%).
  - Added **Rating Distribution & Spread** (Min-Max range and standard deviation `σ`) in executive KPIs and department rankings.
  - Added **Per-Department Sentiment Splits** and **Period-over-Period Deltas** (▲/▼ +X.XX vs last semester) to the Academic Department Rankings leaderboard.
  - Built **Prescriptive AI Executive Insights** with 4 actionable recommendation cards (Priority Intervention, Benchmark Target, Data Confidence Alert, and Best Practice Commendation).
  - Extracted bilingual Taglish/English positive drivers and constructive opportunities.
  - Displayed **Institutional Target Benchmark Context** (`Target Benchmark: 4.00 / 5.00`).
- [X] **Evaluation Questions Setup Modernization (2026-08-15)**:

  - Standardized all 7 evaluation categories across tabs and modal dropdowns in `manage-questions.blade.php`: **Student**, **Dean**, **Program Head**, **Department Head**, **Peer**, **Supervisor**, and **Self**.
  - Added live debounce search bar for filtering questions across criteria parts.
  - Aligned styling with primary brand `#9b0000` / `#f89696`, `border-l-[5px]` cards, Lexend font, dynamic point badges from the active semester, and instant `Flux::toast` notifications.
- [X] **Reports Monochrome Redesign, Semester-over-Semester Growth, Leaderboards & Staff Peer Evaluations (2026-08-15)**:

  - **Faculty & Department Rankings**: Filtered faculty rankings to teachers only (`role === 'faculty'`), department rankings to academic departments only (`type === 'academic'`), and removed Rankings navigation access for faculty users.
  - **Questionnaire Setup & Toast Feedback**: Split Program Head and Department Head questionnaire part management cards in Evaluation Settings, maintaining shared weight while permitting distinct parts. Added instant toast confirmation feedback upon saving criteria points and score weights.
  - **Monochrome & Simplified Reports UI**: Converted Individual and Summary reports to a clean monochrome (black/zinc) design. Added individual & institutional **Semester-over-Semester Growth** calculations vs previous semesters. Summary report now highlights an academic department leaderboard and 1-glance AI insights while removing the cluttered faculty performance overview grid.
  - **Admin Dashboard Cleanup**: Removed Department Participation Rates card, allowing Recent Submissions Log to take full container width.
  - **Staff Evaluations**: Staff now evaluates their **Department Head** (supervisor) and **Peer Staff** within the same administrative department, with tabbed dashboard navigation and updated dynamic notifications.
- [X] **Design System, Typography & Interactive Notifications (2026-08-15)**:

  - Created `docs/color audit.md` and standardized primary brand `#9b0000` / `#f89696`, card left borders `border-l-[5px]`, and CTA buttons across light and dark modes.
  - Migrated system-wide font family to **Lexend**.
  - Updated welcome landing page: white background / black text Log In button and luminous white logo glow.
  - Resized sidebar logo for proper fit, resolved collapsed favicon visibility, and removed redundant profile dropdown and notifications page link from sidebar.
  - Implemented interactive navbar notification hub (`livewire:notification-dropdown`) with **Read all**, **Individual Dismiss** (`✕`), and **Clear all** features backed by `users.dismissed_notifications` persistence.
- [X] **Evaluation Settings Overhaul & Department Leadership Sync (2026-08-14)**:

  - Added Dean Evaluation Parts (`dean`) and Superior Evaluation Parts (`superior`) to Section 4 in `evaluation-settings.blade.php`.
  - Upgraded contrast and legibility across Section 3 & Section 4 for both Light Mode and Dark Mode.
  - Standardized the 6 relationship categories across the system (Student, Dean, Program/Dept Head, Peer, Self, Superior) and updated labels to explicitly list `PH/DH → Dean`.
  - Implemented bidirectional department leadership synchronization (`syncDepartmentHeadship()`) between Employees and Departments pages, fixing duplicate Program Head display bugs and unassign persistence issues.

[X] [Relationships check and new label]

Student Evaluation: student evaluates faculty professor

Dean Evaluation: dean evaluates program head, dean evaluates department head

Program / Department Head Evaluation: program head evaluates faculty professor, department head evaluates department staff

Peer Evaluation: faculty prof evaluates peer faculty prof, department staff evaluates peer department staff

Self Evaluation: program head  evaluates self, department head evaluates self, dean evaluates self, faculty professor evaluates self, department staff evaluates self.

Superior Evaluation: program head evaluates dean, department head evaluates dean, faculty professor evaluates program head, department staff evaluates department head.

[Example Computation for each part in student evaluation]

Part 1:
given example: part max pts: 36

  Rating | max rating      Points

1. 5           5             ?
2. 4           5             ?
3. 3           5             ?
4. 4           5             ?
5. 5           5             ?
6. 5           5             ?

total question: 6

formula:
Rating / Max Rating * Total Question = Points

example:

1. 5 / 5 * 6 = 6 pts
2. 4 / 5 * 6 = 4.8 pts
3. 3 / 5 * 6 = 3.6 pts
4. 4 / 5 * 6 = 4.8 pts
5. 5 / 5 * 6 = 6 pts
6. 5 / 5 * 6 = 6 pts

Formula for total points:

31.2 = 6 + 4.8 + 3.6 + 4.8 + 6 + 6

part 1 points: 31.2

[Example Computation for combining all part in student evaluation with weights applied]

given example:
total max points: 90
weighted rating: 45%
for example student evaluation have 3 parts

formula:

part 1 + part 2 + part 3 = total part points

ex. 31.2 + 31.2 + 16.2 = 78.6

formula:
total part points / max points * 100 * weighted rating = weighted score

ex. 78.6 / 90 * 100 * .45 = 39.3

weighted score: 39.3%

[Example formula for max pts]
Note: total max pts set and weights is needed to by dynamic. it means the admin can change it anytime.
the total max pts should be rounded

given:
total max pts set: 200 pts
total weights: 100%

distribution:

student evaluation:(30% weight)
dean evaluation:(15% weight)
ph/dh evaluation:(15% weight)
peer evaluation:(15% weight)
self evaluation: (5% weight)
superior evaluation: (20% weight)

example:

student evaluation: 30% of 200 is 60 pts, so student total max point now is 60 pts.
dean evaluation: 15% of 200 is 30 pts.
ph/dh evaluation: 15% of 200 is 30 pts.
peer evaluation: 15% of 200 is 30 pts.
self evaluation: 5% of 200 is 10 pts.
superior evaluation: 20% of 200 is 40 pts.

[when does formula apply]

1.Per-form computation (happens when an evaluation is submitted)
2.Weighted conversion
3.Final score computation
