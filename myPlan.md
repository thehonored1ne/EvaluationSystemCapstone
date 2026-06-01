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
