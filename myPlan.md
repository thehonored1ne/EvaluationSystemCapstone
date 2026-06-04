# Heres the functionality i have in mind in this system we are developing.

# Roles: Admin, Dean, Program Head, Faculty Professor, Student, Staff.

## Admin sidebar
- dashboard
- manage users
- evaluation settings
- manage evaluations
- manage questions
- evaluation results
- analytics
- reports
- notifications

## Dean sidebar
- dashboard
- manage evaluations
- evaluation results
- reports
- notifications

## Program Head sidebar
- dashboard
- manage evaluations
- reports
- notifications

## Faculty Professor sidebar
- manage evaluations
- notifications

## Student sidebar
- manage evaluations
- notifications

## Staff sidebar
- manage evaluations
- notifications

# Role responsibilities

## Admin
- Admin will be the one responsibile in managing the evaluation system and can see the all results
- cannot evaluate anyone

## Dean
- Dean can view the result and can generate reports about the results of evaluation
- can evaluate all program heads
- can self eval
- can be evaluated by program heads

## Program Heads
- can evaluate professor on their department
- can be evaluated by the professors on their own department
- can self eval

## Faculty Professor
- can do self evaluation
- can be evaluated by the student they are teaching in the current sem
- can evaluate peer professor
- can evalaute their program head

## Student 
- can evaluate their professors on current semester
- make sure that when student login it list all the their professor in current semester.
- subjects relationships

## Staff
- can self eval
- can evaluate their program head

# Features
- when evaluators open their account, they need to be able to see a form with a comment and they can submit it
- when multiple users submit make sure its indepotent
- impliment a job queue so when multiple users submit at the same time, their submission will go in the queue and our system will process it.
- Queue Connection: use `sync` mode (`QUEUE_CONNECTION=sync` in `.env`). Jobs process instantly on submission — no need to run `php artisan queue:work`. This is sufficient for up to 3K+ users/day since each submission is just a few lightweight DB inserts (~50-100ms). If the system scales to 50K+ users or adds heavy tasks (emails, PDF generation), switch to `database` + Supervisor/Horizon.

---

# Summary of Work Done – June 1, 2026

## 1. Rating Scale Button Fix
- The 1–5 rating buttons on the evaluation form were unclickable/unresponsive.
- Root cause: Livewire's DOM diffing was colliding with static Blade CSS classes (`bg-white` and `bg-indigo-600` both active at the same time), making selected numbers invisible (white text on white background).
- Fix: Integrated Alpine.js (`x-data` + `@entangle`) on the evaluation form component and replaced `wire:model.live` with `x-model` on radio buttons. Used Alpine's `:class` binding to toggle background/text colors dynamically.
- Result: Buttons now respond instantly, show the number clearly with a contrasting indigo background when selected.

## 2. Comment Profanity Filter
- Implemented a real-time profanity detection system in the evaluation comment field.
- Created a configurable `$curseWords` array in the PHP component with common English and Filipino curse words (e.g., `fuck`, `shit`, `gago`, `tangina`, `putangina`, `bobo`, etc.).
- Added a `$strictBoundaryWords` list (e.g., `ass`, `hell`, `tanga`) that are only matched as whole words to avoid false positives in legitimate words like `class`, `hello`, or `tanggapan`.
- Initially replaced curse words with asterisks, then revised to completely remove the detected curse words from the comment.
- Displays a red, shaking danger toast notification telling the user: *"Saying a bad word is not a good thing. Let's keep our comments constructive and respectful!"*
- Uses `wire:model.live.debounce.1000ms` so the filter runs automatically after the user pauses typing for 1 second.
- Also added final profanity filtering in the `submit()` method to guarantee database integrity.

### Profanity Detection Improvements
- Fixed an issue where embedded curse words (e.g., "bbaliw" containing "baliw") were not detected.
- The system now uses substring matching for most curse words and whole-word matching only for short ambiguous words to avoid false positives.

### Toast Notification Styling
- Created custom CSS shake animation (`@keyframes shake`) in `app.css`.
- Styled the danger toast with a red color scheme (light red background, solid red border) to make it highly visible and attention-grabbing.

## 3. Sidebar Feature Pages (Full Implementation)
- Completed all previously non-functional sidebar menu items with rich, fully authorized views:

### Manage Evaluations Page
- Displays completion rates overview across academic classes.
- Shows enrolled vs. evaluated counts with color-coded progress bars.
- Authorized for Admin, Dean, and Program Head roles.

### Evaluation Results Page
- Lists average scores for all teachers in the selected semester.
- Clicking a teacher opens a detailed modal with averages per evaluation type (Student, Peer, Self), averages per criterion category, and qualitative comments.
- Authorized for Admin and Dean roles.

### Analytics Page
- Displays high-level KPIs and SVG-based charts.
- Includes department average comparisons and ratings star distributions.
- Authorized for Admin role only.

### Reports Page
- Generates print-optimized performance report sheets.
- Includes signature lines, profile grids, score tables, and comments.
- Authorized for Admin, Dean, and Program Head roles.

### Notifications Page
- Announcement board showing active semester deadlines.
- Displays counts of pending evaluations (self, peer, subordinate) customized per user role.
- Available to all authenticated users.

## 4. Post-Submission Status Checking & Re-evaluation Prevention
- Problem: After submitting a self-evaluation, users could submit again.
- Implemented `Evaluation::getStatus()` in the Evaluation model as the single source of truth.
  - Checks the database for completed evaluation records.
  - Also queries the `jobs` table for pending `ProcessEvaluationSubmission` queue jobs to detect "processing" state.
  - Uses pattern matching on serialized payloads with wildcard queries.
  - Wrapped in try-catch for robustness when the jobs table doesn't exist.
- Updated all role dashboards (Student, Faculty, Dean, Staff) to:
  - Show a "Processing" badge with a spinner animation when a job is in the queue.
  - Show a "Completed" badge when the evaluation is done.
  - Disable/block the "Evaluate" button in both cases so users cannot re-submit.
  - Display a friendly message like "Your evaluation is being processed. Thank you!" or "Your evaluation has been completed."

## 5. Database Schema Updates
- Added `department_id` foreign key to the `employees` table to link Faculty, Program Heads, and Staff to their departments for boundary-check evaluations.
- Updated the `Evaluation` model and `Employee` model with new fillable fields and relations.

## 6. Automated Tests Written
- `EvaluationSystemTest.php`: Queue job processing, idempotency, department boundary checks, profanity filter.
- `SidebarFeaturesTest.php`: Route authorization policies and evaluation monitoring progress.
- `EvaluationStatusDashboardTest.php`: Queue status retrieval and dashboard UI blocking.

---

## Summary of Work Done – June 4, 2026

## 1. Manage Subjects Admin Page
- File: `resources/views/livewire/admin/manage-subjects.blade.php`
- Single-file Livewire Volt component for CRUD operations on academic subjects (`Subject` model).
- Code format: uppercase formatting of subject codes automatically.
- Inline search (debounce 300ms) for code and name.
- Custom overlay modal matching existing style for creating, editing, and deleting subjects.
- Prevents deletion if the subject has associated classes.

## 2. Manage Classes & Student Enrollment Page
- File: `resources/views/livewire/admin/manage-classes.blade.php`
- Single-file Livewire Volt component for CRUD operations on academic classes (`AcademicClass` model).
- Dropdowns for selecting Subject, Professor (filtered to only show `faculty` or `program head` roles), and Semesters.
- Advanced search (debounce 300ms) for subject code, subject name, teacher name, or section.
- Semester and Department filtering options (defaulting to the active semester).
- Integrates a student enrollment sub-modal allowing:
  - Scrollable view of currently enrolled students.
  - Interactive student unenrollment (detaching pivot relation).
  - Search bar to query students to enroll (excluding already enrolled students).

## 3. Sidebar & Routes Registration
- Registered routes in `routes/web.php` for `/admin/subjects` and `/admin/classes` with `role:admin` middleware.
- Added "Manage Subjects" and "Manage Classes" navigation items inside the "Management" navlist group in `sidebar.blade.php`.

## 4. Automated Tests
- Created `tests/Feature/AdminManagementTest.php` to verify:
  - Access controls for `/admin/subjects` and `/admin/classes` routes.
  - Subject creation, editing, unique constraints, and deletion.
  - Class creation, editing, and deletion.
  - Student enrollment, student detachment, and search filter exclusions.
- All tests run and pass successfully.

## 5. Sidebar Notifications Badge & Auto-Read Feature
- Added a `notifications_last_viewed_at` timestamp column to the `users` table via migration.
- Centralized notification generation in the `User` model (`getNotifications()`), giving each notification a stable `created_at` timestamp in the past.
- Added code in `sidebar.blade.php` to calculate and display a premium amber count badge for any notifications whose `created_at` timestamp is newer than the user's `notifications_last_viewed_at`.
- Configured the `/notifications` page component (`mount()` hook) to automatically update `notifications_last_viewed_at` to `now()`, making them marked as read and clearing the sidebar count immediately.
- Wrote integration tests confirming that unread counts are calculated accurately and auto-read functionality works as intended.

## 6. System Admin Management in Profile Settings
- Modified `manage-staff.blade.php` to strictly display users with employee role `'staff'`. This completely separates departmental staff from the system admin account.
- Integrated the "Employee Number" field directly into the settings profile form (`profile.blade.php`), enabling system admins to view and update their own ID details directly from their profile page.
- Configured settings saving logic to sync changes back to the associated `Employee` record (verifying unique employee number constraints, and ensuring the admin does not have a department).
- Wrote feature tests verifying that saving profile details successfully persists the name, email, and employee number updates. All tests passed.

---

## Summary of Work Done – June 4, 2026 (Session 2)

## 1. Renamed Evaluation Toggle Labels
- File: `resources/views/livewire/admin/evaluation-settings.blade.php`
- Renamed the evaluation override control from **"Enable System"** → **"Open Evaluation"** and **"Disable System"** → **"Close Evaluation"** for more descriptive and user-friendly labeling.
- Updated the accompanying toggle descriptions to match the new terminology.

## 2. Sidebar Reorganization – All Evaluator Roles
Reorganized the sidebar navigation for all non-admin evaluator roles (Student, Faculty, Staff, Dean, Program Head):
- File: `resources/views/components/layouts/app/sidebar.blade.php`
- **Removed** the top-level "Dashboard" / Overview menu item from the sidebar for all non-admin roles. Only Admins retain the Dashboard link.
- **Replaced** the flat "Manage Evaluations" link with a collapsible **"My Evaluations"** Alpine.js dropdown under the Evaluations group.
- Added role-specific submenus inside "My Evaluations":
  - **Dean**: "Self Evaluation" (`?tab=self`), "Program Head Evaluations" (`?tab=program-heads`)
  - **Program Head**: "Self Evaluation" (`?tab=self`), "Supervisor Evaluation" (`?tab=supervisor`), "Faculty Evaluations" (`?tab=faculty`)
  - **Faculty**: "Self Evaluation" (`?tab=self`), "Peer Evaluation" (`?tab=peer`), "Supervisor Evaluation" (`?tab=supervisor`)
  - **Staff**: "Self Evaluation" (`?tab=self`), "Supervisor Evaluation" (`?tab=supervisor`)
  - **Student**: "Evaluate Professors"

## 3. Dashboard Tab Routing – Dean & Program Head
Extended the tab-based routing (already applied to Faculty/Staff) to the Dean and Program Head dashboards:

### Dean Dashboard (`resources/views/livewire/dean/dashboard.blade.php`)
- Added `use Livewire\Attributes\Url;` import.
- Declared `#[Url] public string $tab = 'self';` URL-bound property.
- Wrapped each evaluation card in `@if($tab === '...')` conditionals:
  - `self` → Self Evaluation card
  - `program-heads` → Program Head Evaluations (Subordinates) card

### Program Head Dashboard (`resources/views/livewire/program-head/dashboard.blade.php`)
- Added `use Livewire\Attributes\Url;` import.
- Declared `#[Url] public string $tab = 'self';` URL-bound property.
- Wrapped each evaluation card in `@if($tab === '...')` conditionals:
  - `self` → Self Evaluation card
  - `supervisor` → Supervisor Evaluation (College Dean) card
  - `faculty` → Faculty Evaluations (Subordinate Professors) card

## 4. Manage Classes – UI Fixes & Improvements

### Student Column Overlap Fix
- File: `resources/views/livewire/admin/manage-classes.blade.php`
- Fixed overlapping content in the Students column by rebalancing table column width percentages.
- Expanded the Actions column width from `10%` to `20%` so action buttons no longer overflow into adjacent columns.

### Renamed "Students" Button to "View"
- Changed the enrollment management button label from `Students` → `View` for better descriptiveness and clarity of purpose.

### Actions Column Repositioned & Left-Aligned
- Moved the "Actions" column to the leftmost position in the table since it is the primary differentiating column.
- Applied `text-left` alignment to both the header and cell to match the rest of the table columns.

### Search Bar Width Fix (All Admin Management Pages)
- Applied `class="w-full"` and `min-w-[300px]` container wrappers to the search input across all management pages:
  - Manage Classes, Manage Subjects, Manage Students, Manage Deans, Manage Faculty, Manage Program Heads, Manage Staff
- Prevents search bars from collapsing into icon-only squares inside flex-row layout containers.
- Produces a balanced, full-width appearance matching the rest of the layout.

### Removed "Save Schedule Window" Button
- Investigated the "Save Schedule Window" button in Manage Classes.
- Confirmed the button was a legacy leftover — schedule data is already saved automatically via `wire:model.live` on the date pickers.
- Removed the redundant button to clean up the UI and avoid confusion.

## 5. Automated Tests Updated
- File: `tests/Feature/SidebarFeaturesTest.php`
- Expanded `beforeEach()` to also create and initialize a `'program head'` role and employee user for testing.
- Added assertions in `sidebar renders correct evaluator submenus depending on user role` test:
  - Dean users see "Self Evaluation" and "Program Head Evaluations" submenus and do **not** see the Dashboard overview link.
  - Program Head users see "Self Evaluation", "Supervisor Evaluation", and "Faculty Evaluations" submenus and do **not** see the Dashboard overview link.
- All **55 tests passed** (230 assertions) after all changes.

---

## Summary of Work Done – June 4, 2026 (Session 3)

## 1. Evaluation Settings – Schedule Window Improvements
File: `resources/views/livewire/admin/evaluation-settings.blade.php`

### Current Schedule Display Panel
- Added a **side-by-side two-column layout** to the "Configure Evaluation Window" section.
- Left column: the existing date/time input form.
- Right column: a new **"Current Saved Schedule" panel** that shows the schedule stored in the database.
  - Displays **Opens** (green dot) and **Closes** (red dot) dates in a human-readable format (e.g. `Jun 05, 2026 at 08:18 PM`).
  - Shows a live **status badge**: `Active Now` (pulsing green), `Scheduled` (indigo), `Expired` (grey), or `Locked` (amber).
  - When no schedule is set, shows a dashed placeholder card with a "No schedule set yet" message.

### Remove Schedule Button & Modal
- Added a **Remove** button in the top-right of the Current Saved Schedule panel.
- Clicking it opens a dedicated **"Remove Schedule?" Livewire modal** (not a browser dialog) showing the schedule about to be deleted.
- Modal has **Cancel** and **Yes, Remove** buttons.
- On confirmation, both `evaluation_starts_at` and `evaluation_ends_at` are set to `null` in the DB and the form fields are cleared.
- Added `confirmRemoveSchedule()` method to open the modal and updated `clearSchedule()` to close it before clearing.
- Added `showRemoveScheduleModal` boolean property to track modal state.

### Overwrite Confirmation Modal
- When the admin hits **Save Schedule Window** and a schedule **already exists**, the save is intercepted and an **"Overwrite Existing Schedule?" modal** is displayed instead of saving immediately.
- The modal shows the current saved dates (labeled "Being replaced") so the admin knows exactly what will be overwritten.
- Two buttons: **Cancel** (dismisses, no action) and **Yes, Overwrite** (commits the new dates to the DB).
- Added `confirmSaveSchedule()` method (called on modal confirm) and refactored the actual DB write into a private `commitSaveSchedule(Semester $activeSem)` helper to avoid code duplication.
- Added `showOverwriteModal` boolean property to track modal state.

### Bug Fixes
- **Fixed `variant="warning"` crash**: Flux UI only supports `primary`, `outline`, `ghost`, and `danger` variants. The `warning` variant caused an `UnhandledMatchError`. Changed all instances to `variant="danger"`.
- **Fixed modals not appearing**: Both modals were originally nested inside a card `<div>` with a CSS stacking context, causing `position: fixed` to anchor to the card instead of the viewport. Moved both modals to the **bottom of the root `<div>`** (matching the pattern used by all other modals in the file — e.g. Department, Program modals).
- **Replaced `wire:confirm` (browser dialog)** on the Remove button with the custom Livewire modal for a consistent in-app experience.

---

## Summary of Work Done – June 4, 2026 (Session 4)

## 1. Timezone Fix – Evaluation Window Not Activating
- **Root Cause:** `config/app.php` had `'timezone' => 'UTC'`. The `datetime-local` browser input sends time in the user's local timezone (PHT, UTC+8) with no timezone info attached. `Carbon::parse()` was treating that value as UTC, storing it 8 hours ahead of the intended time. So when the admin set the start time to "now" (e.g. `21:00 PHT`), the system stored `21:00 UTC` — which is actually 8 hours in the future relative to the server's clock — causing the status to remain **"Scheduled"** instead of **"Active Now"**.
- **Fix:** Changed `config/app.php`:
  ```php
  // Before
  'timezone' => 'UTC',

  // After
  'timezone' => 'Asia/Manila',
  ```
- Ran `php artisan config:clear` to flush the cached config.
- **Result:** `now()` and all `Carbon` comparisons now run in Philippine Standard Time (UTC+8), matching what the admin types into the date/time inputs. Setting start time to "now" correctly activates the evaluation window immediately.
