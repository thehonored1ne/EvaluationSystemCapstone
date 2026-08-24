# Evaluation System - Project Context

> [!IMPORTANT]
> **RULE FOR UPDATING THIS FILE:** Keep all summaries and descriptions extremely concise, high-level, and scannable. Avoid detailing low-level database column names, config keys, code files/folders, variable structures, or tests. Keep the focus strictly on key portals, roles, core system features, and major milestones.


## Overview & Roles
A role-based evaluation system with the following active portals:
- **Admin**: System management (users, evaluations, questions, settings, results, analytics, reports).
- **Dean**: Monitor evaluations, self-evaluation, evaluate subordinates (program heads), and generate reports.
- **Program Head**: Monitor department evaluations, self-evaluation, and evaluate faculty.
- **Faculty Professor**: Self-evaluation, peer evaluation, evaluate program head.
- **Student**: Evaluate professors enrolled in current semester classes.
- **Staff**: Self-evaluation, evaluate program head.

---

## Core System Features
- **Evaluation Submission**: Idempotent queue-based submissions via an interactive **Single-Question Wizard UX** (real-time progress header, question number grid navigator, 300ms auto-advance, enlarged horizontal rating buttons, and a final Summary & Review step). Submissions run on the background processing queue (`ProcessEvaluationSubmission`), locking dashboard buttons with "Processing" / "Completed" status badges.
- **Profanity Filter**: Real-time removal of curse words from evaluation comments with constructive toast warnings.
- **Evaluation Windows**: Configurable schedules with conflict protection modals ("Overwrite" / "Remove"), schedule removal protection when evaluations are open, and Asia/Manila timezone alignment.
- **AI Sentiment Pipeline**: Integrates Flask-based VADER sentiment analysis (with Tagalog/Taglish custom lexicon) and a multimodal Decision Tree Classifier (combining text TF-IDF + rating values) to analyze evaluation comments, featuring a confidence Agreement Gate, daily scheduled retraining, and a Human-in-the-Loop sentiment override management dashboard (with validation accuracy, a Confusion Matrix, and fully anonymized data views).
- **Custom Searchable Selects**: Custom Alpine-based `<x-searchable-select>` replacing native listbox dropdowns.
- **Reusable Confirmation Modals**: Standardized modal `<x-confirmation-modal>` with custom details and relationship cascade warnings before deletions.
- **Notifications**: Automated sidebar badges that clear instantly upon visiting the notifications page.
- **Summary Reports**: Printable and exportable performance evaluation summaries (`/admin/reports`) featuring table-less executive scorecards, visual criteria progress bars (`4.50 / 5.0`), integrated **AI Sentiment & Insights Blocks** (positive/neutral/constructive sentiment distribution + automated narrative analysis), submitted comments cards streams, and **Faculty Performance Grid Cards** (replacing traditional data tables) with full single-page `window.print()` document export formatting.
- **Skeleton Shimmer Loading**: Hardware-accelerated shimmer skeleton loader page shells display instantly upon navigating to any admin-facing dashboard or management portal, loading actual data asynchronously in the background.

---

## Milestones & Summary of Work Done

### August 25, 2026
- **Evaluation Form UX, Submission Guards & Mobile Optimization**:
  - Added *"Jump to Missing Question →"* quick-action button and auto-centering smooth scroll for rating number navigator pills in `evaluation-form.blade.php`.
  - Blocked false-positive submit button loading states when incomplete with client-side Alpine checks and `pointer-events-none`.
  - Replaced single-line review matrix text truncation with `line-clamp-2 sm:truncate leading-snug` to prevent clipping question names on mobile.
  - Reduced outer viewport gutters (`px-2 sm:px-4 md:px-6 py-3 sm:py-6`) across `evaluation-form.blade.php` and all 6 evaluator dashboards (`student`, `faculty`, `staff`, `dean`, `department-head`, `program-head`).
- **Reports & Evaluation Results N+1 Database Optimizations**:
  - Pre-loaded criteria collection in `reports.blade.php`, eliminating 4 duplicate SQL queries, and added responsive horizontal scrolling min-widths.
  - Batch-aggregated evaluator submission and evaluatee received counts in `evaluation-results.blade.php`, eliminating row-level N+1 queries in the paginated table and reducing modal queries from 10 to 1.
- **UI De-Cluttering & Component Layout Balancing**:
  - Converted role tabs in `manage-evaluations.blade.php` into a responsive 6-column grid (`grid-cols-2 sm:grid-cols-3 lg:grid-cols-6`), eliminating dead whitespace.
  - Added `min-w-[700px]` constraints to Faculty and Department leaderboards in `rankings.blade.php`.
  - Removed redundant page-level subheadings and card-level filler captions across all administrative views while preserving dynamic counters.
  - Synchronized all 11 placeholder skeletons in `resources/views/livewire/placeholders/` to match live headers and layouts 1:1.

### August 23, 2026
- **System Performance, Database Composite Indexes & Caching Layer**:
  - Re-architected **Manage Evaluations & Completion Tracking** (`manage-evaluations.blade.php`) to use direct SQL joins and pre-aggregations, dropping models in RAM from **11,867 down to 0** and query statements from **4,859 down to ~10**.
  - Added application-level caching via `Cache::remember` for active semester (`Semester::getActive()`), departments (`Department::getCachedList()`), and evaluation criteria (`EvaluationCriterion::getForTypes()`).
  - Added composite performance database indexes on `employees`, `evaluations`, `classes`, and `semesters`.
  - Redesigned `terms-modal.blade.php` with card-based policy sections.
  - Refactored sidebar with zero-flicker CSS transition architecture.

### August 21, 2026
- **System-Wide 1:1 Skeleton Loader & Shimmer Animation Overhaul**:
  - Upgraded shimmer sweep animation in `app.css` with GPU hardware acceleration (`will-change: transform`, `transform: translateZ(0)`), enhanced dark-mode contrast, and reduced-motion accessibility.
  - Expanded `<x-skeleton>` component primitives with dedicated presets: `stat-card` (with 5px left-border `#9b0000` / `#f89696`), `chart`, `table` with proportional columns, `badge`, `button`, and `wizard`.
  - Rebuilt all admin placeholders to match their real pages 1:1, eliminating Cumulative Layout Shift (CLS): Admin Dashboard, Evaluation Settings (4-section layout), Reports & Analytics, Manage AI, Manage Departments, Manage Programs, Manage Classes, Manage Subjects, Manage Employees, Manage Students, Manage Questions, Completion Tracking, Evaluation Results, and Rankings.
  - Added Livewire `#[Lazy]` and dedicated 1:1 skeleton placeholders across all evaluator role portals: Student Dashboard (enrolled classes cards grid) and Faculty, Dean, Program Head, Department Head, Staff dashboards (progress badges, tabs, and tables).

### August 20, 2026
- **Default Password Security Advisory Modal**:
  - Implemented automatic detection of users operating with default credentials (`Hash::check('password', $this->password)`) and tracked explicit updates via `password_changed_at`.
  - Created Livewire security advisory modal (`default-password-modal.blade.php`) integrated into the global authenticated layout with direct "Change Password Now" navigation to `/settings/password`.
  - Added session-based dismissal controls ("Later" snooze, "Don't show again this session") ensuring users are reminded upon each login until their password is changed.

### August 19, 2026
- **Mobile Responsive UI, Navigation & Component Polish**:
  - Fixed mobile sidebar toggle button (`flux-sidebar-toggle`), displaying the full GRC institutional logo on mobile views while maintaining mini icon mode exclusively for desktop collapsed sidebars.
  - Updated Account Settings (Profile, Password, Appearance, Training) and Evaluation Settings to responsive 1-column layouts with aligned action buttons.
  - Standardized filter dropdown widths across Rankings, Completion Tracking, and Reports (`w-full sm:w-auto`).
  - Polished Notification dropdown on mobile with `whitespace-nowrap` on action buttons and fluid responsive width.
- **Completion Tracking Pagination Across All 7 Categories**: Connected Livewire `WithPagination` across all 7 evaluation tracking tabs on `/manage-evaluations` with dedicated pagination links.
- **Clean PDF & Print Export (Navbar & UI Chrome Exclusion)**: Applied `print:hidden` to `<x-admin.navbar />`, `<flux:sidebar>`, and `<footer>` with global `@media print` rules to prevent UI headers, avatars, and buttons from appearing on exported PDF evaluation reports.
- **Evaluator Dashboard Tables Scrollability**: Added `overflow-auto max-h-[500px]`, `sticky top-0 z-10` headers, and min-widths across all evaluator dashboards (Student, Faculty, Staff, Dean, Department Head, Program Head).
- **Evaluation Form Mobile UI & Submit Button Optimization**: Redesigned the review and submission action controls in `evaluation-form.blade.php` to responsive `flex-col-reverse sm:flex-row`, eliminating button clipping, and switched 1–5 rating buttons to a responsive 5-column grid (`grid grid-cols-5`) to eliminate clipping on mobile view.
- **Advanced Leetspeak Profanity Normalization & Taglish Code-Switching Context Router**:
  - Built `ProfanityFilterService` in Laravel featuring multi-stage symbol substitutions (`@` $\rightarrow$ `a`, `1`/`!` $\rightarrow$ `i`, `0` $\rightarrow$ `o`, `3` $\rightarrow$ `e`, `$` $\rightarrow$ `s`), character repetition collapsing (`taaaangaaa` $\rightarrow$ `tanga`), and inter-character spacing stripping (`t a n g a`, `g_a_g_o`).
  - Added particle-based language mode detection (`detect_language_mode`) and specialized Taglish multi-word negation & idiom context pre-parsing in Python Flask NLP microservice (`python/app.py`).
- **Scheduled Automated Deadline Reminders & Notification Automation Engine**:
  - Implemented background console command `evaluations:send-reminders` (`SendEvaluationDeadlineReminders.php`) scheduled hourly in `routes/console.php` with milestone window triggers (7d, 3d, 24h, 6h).
  - Added dynamic deadline urgency alerts in `User::getNotifications()` surfacing urgent closing notices in the navbar notification hub and centralized evaluation pending checks in `User::countPendingEvaluations()`.
  - Integrated Completion Tracking (`manage-evaluations.blade.php`) "Send Reminders" action to execute the reminder engine and log activity.
- **Admin Dashboard Visual Analytics (Chart.js)**: Replaced text metrics cards with interactive Chart.js bar charts for Ratings Distribution (1 to 5 stars) and Academic Department Mean Rating Comparisons with modular Alpine.js lifecycle management.
- **Subjects Bulk Data Operations (Import, Export & Template)**: Added CSV/Excel import with header validation, duplicate checking, download CSV template, and CSV export.
- **Evaluation Settings Quick Navigation & Period Management Table**: Added a static quick jump anchor navigation bar and paginated academic periods table with unified modal.
- **Responsive Table Standardization & Directory Overhaul**: Standardized all system tables to full width on desktop with min-width horizontal scrolling on mobile, and overhauled `/evaluation-results` directory with multi-role relational querying.

### August 15, 2026
- **Official GRC Summary of Faculty Performance Evaluation on Teaching Effectiveness**: Replicated the official Global Reciprocal Colleges (GRC) 2-page print-ready individual evaluation report format in `reports.blade.php`.
  - **Page 1**: Replicated the official GRC scorecard featuring the official system logo asset `GRC-o-Evaluation-LOGO.png`, boxed title, Roman numeral criteria breakdown across 5 categories with 360-degree Peer Evaluation integration (40% Student / 80 pts, 20% Dean / 40 pts, 20% Program Head / 40 pts, 15% Peer / 30 pts, 5% Self / 10 pts $\rightarrow$ 200 Max Scale), GRC Legend table (Excellent 194.95-200, Very Satisfactory 181.05-194.94, Satisfactory 153.26-181.04, Need Improvement 139.35-153.25, Poor 1.00-139.34), Overall Rating Box with composite score and performance level, and 3-signatory block (*Prepared by*, *Noted by*, *Approved by*).
  - **Page 2**: Embedded official big system logo, replaced raw comment text dumps with structured AI qualitative analysis (sentiment distribution gauge, Top Student Commendations, Key Opportunities for Growth, representative bilingual feedback quotes), with signatures omitted so the page is focused exclusively on NLP insights.
- **Actionable Evaluation Summary Report Redesign (`/reports`)**: Overhauled the high-level Evaluation Summary Report into an actionable executive dashboard featuring:
  - **KPI Cards**: Institutional Average, Student Average, Total Submissions, and Total Faculty Evaluated.
  - **Faculty Requiring Attention**: Surfaces instructors below 3.50 rating or with $\ge 30\%$ negative sentiment, displaying severity levels and AI-generated root-cause drivers.
  - **Turnout & Data Confidence Rates**: Tracks expected vs submitted evaluations per department with low-turnout data confidence warnings (<60%).
  - **Prescriptive Pedagogical & Operational Recommendations**: 3 structured action cards (Institutional Strength, High-Impact Growth Area, Leadership Next Steps).
  - **Rating Distribution Spread**: Visual 5-Star to 1-Star histogram.
  - **Academic Department Leaderboard**: Ranked table with performance badges and dean/head details.
- **Completion Tracking Modernization across All 7 Standardized Categories (`/manage-evaluations`)**: Upgraded completion monitoring with dedicated tabs for Student, Dean, Program Head, Department Head, Peer, Supervisor, and Self evaluations, multi-criteria filter bars, category context descriptions, and broadcast reminder action logging.
- **Dean-to-Faculty Evaluation Flow**: Added an **Academic Faculty Evaluations** tab to the Dean Dashboard (`dean/dashboard.blade.php`) and sidebar navlink so Deans can evaluate professors in their academic college.
- **Reworked Evaluation Weight Score Card & Dynamic Max Points Target (`/admin/evaluation-settings`)**: Added report-specific tab switching (Teaching Effectiveness 40/20/20/15/5 weights, Administrative Staff 360°, and Global Master Targets) with dynamic scale configuration and instant balance calculation.
- **Color System Audit & Semantic Token Standardization**: Created `docs/color audit.md`, unified primary brand accent `#9b0000` (Light) / `#f89696` (Dark) with hover `#7a0000` / `#f57575`, standard card left borders `border-l-[5px]`, and standardized Call-to-Action (CTA) primary buttons globally.
- **Global Typography (Lexend)**: Migrated system font family globally to Google Font **Lexend**.
- **Welcome Page Aesthetic Enhancements**: Updated Log In CTA to high-contrast white button with black text, and updated logo drop shadow to a luminous white glow.
- **Sidebar Streamlining & Logo Sizing**: Adjusted `<x-app-logo>` to fit expanded sidebar width cleanly, resolved collapsed favicon visibility, and removed redundant profile dropdown and notifications link from the sidebar.
- **Interactive Navbar Notification Hub**: Built Livewire component `livewire:notification-dropdown` in the top header with **Read All**, **Individual Dismiss** (`✕`), **Clear All**, and persistent database storage (`users.dismissed_notifications`).

### August 11, 2026
- **Redesigned Admin Reports Page (`/admin/reports`)**: Eliminated traditional data tables from both Summary and Individual reports in favor of executive scorecards, criteria progress bars (`4.50 / 5.0`), AI Sentiment & Insights Blocks (positive/neutral/constructive sentiment distribution + automated narrative text), submitted comments cards streams, and Faculty Performance Grid Cards with full single-page `window.print()` document export support.
- **Evaluation Form Draft Persistence (`localStorage`)**: Added reactive `localStorage` draft saving in Alpine (`x-data`) for 1-5 rating answers, comments, and question step across page reloads & dashboard navigation. Enforced `required|string|min:3` comments validation on submit with UI red asterisk `Comments & Suggestions *` and error alert. Updated progress bar line fill to `bg-amber-400 dark:bg-amber-400`.
- **Evaluator Navbar, Footer & Table Cleanliness**: Enabled navbar and footer for all logged-in evaluator roles (`@if(auth()->check())`), fixed notification badge positioning, auto-hid dashboard header banner when evaluation form is open, and cleaned up table cells in all 5 evaluator dashboards to display strictly single-line strings under Name and Subject headers.
- **Single-Question Interactive Evaluation Wizard**: Re-architected `evaluation-form.blade.php` into a focused single-question evaluation wizard with real-time progress header, question number grid navigator, 300ms auto-advance, enlarged horizontal rating buttons, and a final Summary & Review screen.
- **Collapsed Mini Sidebar & Navigation Enhancements**: Added collapsible icon-only mini sidebar mode with dark red (`#800000`) GRC logo asset, right-aligned tooltips, dark red active page indicator, and persistent dark mode switcher (`$flux.appearance`).

### August 8, 2026
- **Department Leadership Alignment (`/admin/departments`)**: Updated the Department Management page and modal to assign **Program Head** leadership instead of Dean. Replaced the stat card, filter dropdowns, table headers, form select inputs, and deletion modals to use `program_head_id` and active `program head` employees. Added migration `2026_08_08_000001_add_program_head_id_to_departments_table.php` and updated tests.

### August 7, 2026
- **Admin Departments Management Page (`/admin/departments`)**: Added a dedicated department management panel in the main sidebar with 4 metric cards (5px `#800000` dark red left border, live `<x-odometer>` counters), Dean assignment filter (`Assigned Dean`, `Unassigned Dean`), search, sorting modes, and `<flux:dropdown>` actions for department editing and deletion.
- **Admin Programs Management Page (`/admin/programs`)**: Added a dedicated academic programs panel directly below Departments in the sidebar with 4 metric cards, Department filter, Program Head filter, search, sorting modes, and `<flux:dropdown>` actions. Removed duplicate department/program cards from Evaluation Settings.
- **Evaluation Weight Score Card & Dynamic Target Points**: Added a dynamic Evaluation Weight Score Card to Evaluation Settings with dynamic target point inputs for all 7 evaluation types (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`), real-time percentage weight calculations, color-coded progress bars, balance status badge, and database migration support.
- **Evaluation Settings 4-Section Dashboard**: Re-architected Evaluation Settings into a spacious 4-section vertical layout (Active Access Status Banner, 2-Column Schedule & Period Grid, Evaluation Weight Score Card, Questionnaire Parts Setup).
- **Completion Tracking Dashboard Redesign (`/manage-evaluations`)**: Redesigned Completion Tracking into a multi-perspective evaluation monitoring dashboard with 4 metric cards (Total Submissions Received, Student Progress %, Supervisor Ratings %, Self Appraisals Done), tabs (**Student Upward Progress**, **Supervisor & Executive Ratings**, **Self Appraisals**), search, department filter, completion status filter, and reminder broadcast.
- **Institutional Rankings Page (`/rankings`)**: Added a dedicated Rankings page below Results in the sidebar featuring 4 metric cards, **Faculty Leaderboard** (with 🥇, 🥈, 🥉 medals, composite rating, performance badges), **Department Leaderboard** (ranked department averages), search, department filter, and sort modes.
- **Code Quality & Testing**: Formatted codebase using **Laravel Pint**, verified static analysis using **PHPStan** (0 errors), and confirmed 100% pass across all Pest feature tests.

### August 6, 2026
- **Unified User Portals & Suffix Support**: Consolidated user management into `/admin/employees` (Deans, Program Heads, Faculty, Staff, Admin) and `/admin/students` (Students). Added `suffix` database column and `formatted_name` accessor (`Last Name, First Name Middle Name Suffix`).
- **Self-Account & System Safeguards**: Implemented backend guards preventing active logged-in administrators (`auth()->id()`) from self-disabling or self-deleting (with visual `YOU` badges), and added a safeguard protecting the last remaining active Administrator account.
- **Admin Dashboard Restructuring**: Overhauled top 4 statistic cards (Total Employees, Total Students, Current Evaluation Progress with expected sum formula, Pending Submissions with live odometer counter), simplified the Evaluation Period Status card (explicit date-time windows, real-time schedule indicator badge, and specific button text), and updated the overall feedback sentiment card for all evaluator groups.
- **Role-Anonymized Submission Stream**: Transformed recent submissions feed into a strict role-relationship stream (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`) maintaining complete anonymity.
- **Branding & Visual System**: Integrated official logo asset `public/GRC-o-Evaluation-LOGO.png` across header and sidebar navigation, and standardized all admin dashboard cards with a 5px thick dark red (`#800000`) left border.

### June 18, 2026
- **Larastan & Spatie Laravel Activitylog Integration**: Configured Larastan (PHPStan) at level 5 with a baseline file to ensure clean static analysis runs. Published and configured Spatie Laravel Activitylog to audit changes to key models (`User`, `Evaluation`, `EvaluationQuestion`, `AcademicClass`, `Department`, `Program`) while ignoring sensitive parameters such as password updates. Added automated Pest feature tests to verify logging accuracy and password filtering.
- **Welcome Page Issue Reporting Button**: Added a fixed glassmorphic "Report an Issue" button with a warning triangle icon in the bottom-right corner of the landing welcome page (`welcome.blade.php`). This button links directly to the external report submission website (`https://grc-reporting.vercel.app`) to streamline bug and user experience reporting.

### June 17, 2026
- **Lazy Loading & Shimmer Skeletons**: Integrated lazy loading (`#[Lazy]`) and hardware-accelerated shimmer skeleton loader views across all remaining admin-facing pages (Deans, Program Heads, Staff, Subjects, Classes, AI Sentiment, Questions, Settings, Evaluations, Results, and Analytics) for a consistent loading transition. Added inline table skeleton loaders during search/filter operations.
- **Questionnaire Management Alignment**: Updated the admin Questionnaire Management (`manage-questions`) page to support all five current evaluation types (`upward_student`, `upward_employee`, `downward`, `peer`, `self`). Refactored the UI tabs to dynamically load active semester target points configuration, and aligned the validation rules, delete confirmation labels, helper defaults, and feature tests to use the correct schema types.
- **System Font & Welcome Page Redesign**: Migrated the default system font-family from `Instrument Sans` to `Inter` globally. Overhauled the central landing welcome page (`welcome.blade.php`) to use a vibrant abstract gradient cover background, a containerless floating layout, elegant serif typography via **Playfair Display**, a clean floating brand logo with drop shadows, and a centralized Access Portal CTA button.
- **Odometer Count-Up Effect**: Integrated a custom rolling digit `<x-odometer>` Blade component with elastic mechanical bounce transitions (similar to YouTube's live subscriber counter) into the four Admin Dashboard stats cards and the three AI Sentiment Analysis distribution count cards.

### June 16, 2026
- **Admin Dashboard Upgrades**: Added top statistics cards, live schedule status trackers, Live AI Sentiment Lexicon analytics breakdown, department completion rates progress, anonymized recent submissions feed, and quick system shortcuts.
- **UI Alignment Enhancements**: Left-aligned the Admin Dashboard header text, right-aligned the Active Period badge on all viewports, and stacked/expanded date picker inputs under Evaluation Settings.
- **Admin Dashboard & Rate Limiting Refinements**: Corrected recent submission activity feed evaluator role mappings, resolved subject fallback labels for non-class evaluations, fixed department evaluation completion counts using `'upward_student'`, and relaxed evaluation submission rate limits to 50 attempts per 5 minutes to accommodate larger user sessions.
- **AI Sentiment Correction & Retraining Pipeline**: Built a Human-in-the-Loop admin panel to manually override comment sentiments anonymously (preserving evaluator/evaluatee anonymity), retrain the Decision Tree model on-demand, and display validation accuracy and a Confusion Matrix. Refactored the training model to use a multimodal text + rating feature extractor with an Agreement Gate (confidence filter). Expanded the lexicon to **424 words** (including 60+ neutral terms and Taglish negatives) and balanced the seed dataset to **410 comments** (~33% split per class), achieving a **93.8% validation accuracy** with **0% polarity swaps**.

### June 14, 2026
- **Summary Evaluation Reports**: Added toggleable Individual/Summary tabs to the reports page, filtering by academic period, tabular details for all target faculty (including Student, Peer, Self averages, Total Submissions, and Overall ratings), print/PDF styles, and full Pest test coverage.

### June 13, 2026
- **Evaluation Schedule Protection**: Prevented deleting or removing active evaluation schedules while the evaluation period is open.
- **AI Sentiment Analysis Pipeline**: Built a Flask API service featuring VADER sentiment analysis (with Tagalog/Taglish custom lexicon) and a TF-IDF Decision Tree Classifier to auto-classify evaluation comments. Added model migrations, automated backfill commands, and a daily scheduled retraining task.
- **AI Port Migration & IDE Environment setup**: Resolved Windows socket port binding conflict by moving the Flask API to port `5001` and configured workspace/python-level interpreter paths to resolve static analyzer import errors.
- **Security Hardening**: Patched all 14 composer package vulnerabilities, implemented API Key protection (`X-API-KEY`) on the Flask API, moved Flask URLs to `.env` variables, and added rate limiting to guest password reset routes.

### June 10, 2026
- **User Account Deletion**: Enabled transactional deletion for Deans, Program Heads, Faculty, Staff, and Students in admin views with cascading warning alerts.
- **Advanced User Filters**: Added a "None" option to Department filters and added Program and Year Level filters on the Students page.
- **Custom Searchable Dropdowns**: Implemented `<x-searchable-select>` for reports, class, and student management views.
- **Reusable Confirmation Modals**: Deployed `<x-confirmation-modal>` across all admin destructive actions.
- **Sidebar Notification Fix**: Capped dynamic notification timestamps to prevent persistent badge counts.

### June 4, 2026
- **Subject & Class CRUD**: Single-file Livewire Volt pages for CRUD operations on Subjects and Classes (with scrollable student enrollments).
- **Sidebar Reorganization**: Replaced flat "Manage Evaluations" with a collapsible "My Evaluations" dropdown for all evaluator roles.
- **Dashboard Tab Routing**: Added URL-bound tab routing for Dean and Program Head dashboards.
- **Window Schedule Modals**: Added overlap confirmation and removal confirmation modals for evaluation window scheduling.
- **Timezone Alignment**: Configured Asia/Manila timezone to ensure accurate scheduling relative to server time.

### June 1, 2026
- **Alpine Rating buttons**: Fixed Livewire DOM collisions on 1–5 scale buttons.
- **Profanity toast animations**: Built custom shake notifications for the comment filter.
