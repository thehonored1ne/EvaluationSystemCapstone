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
