# Edge Cases, Vulnerabilities & Failure Modes

This document tracks identified edge cases, potential failure modes, security boundaries, and architectural mitigations in the **Evaluation System** project.

---

## 1. Data Integrity & Relational Queries

### 1.1 Multi-Role Polymorphic / Relational Querying
* **Edge Case**: The `users` table does not contain a raw `role` column. Roles are managed through Spatie Permissions (`model_has_roles`) and separated domain models (`employees.role` and `students` relation).
* **Failure Mode**: Querying `User::where('role', ...)` directly throws SQL column errors or returns 0 results on directories like `/evaluation-results`.
* **Mitigation**: Standardized relational Eloquent constraints across all filtering layers:
  ```php
  $query->whereHas('employee', fn($q) => $q->where('role', $selectedRole))
        ->orWhereHas('student');
  ```
* **Status**: **Handled**.

### 1.2 Cascading Account Deletions & Orphaned Data
* **Edge Case**: Deleting a Dean, Program Head, Faculty member, or Student when dependent records exist (classes, evaluation submissions, enrolled sections).
* **Failure Mode**: Foreign key constraint violations or orphaned evaluations with null evaluator/evaluatee IDs.
* **Mitigation**: Reusable confirmation modal warns the administrator of downstream impacts; soft-delete/detach relationships ensure historical evaluation data remains auditable.
* **Status**: **Handled**.

### 1.3 Department Leadership Desynchronization
* **Edge Case**: Changing an employee's role (e.g., Program Head $\rightarrow$ Faculty) or reassigning their department leaving obsolete foreign keys in `departments.program_head_id` or `departments.department_head_id`.
* **Failure Mode**: Ghost leaders or duplicate leadership display in departmental cards and filters.
* **Mitigation**: Implemented `syncDepartmentHeadship()` hook in employee create, update, and role sync operations to automatically clear and reassign department leadership pointers.
* **Status**: **Handled**.

---

## 2. Bulk Data Operations & File Uploads

### 2.1 Malformed Subject CSV/Excel Imports
* **Edge Case**: Admin uploads a spreadsheet with missing columns, invalid header labels (e.g. "Subj Code" instead of "Code"), duplicate subject codes, or non-numeric year levels.
* **Failure Mode**: Unhandled fatal exceptions or corrupted database records during bulk insert.
* **Mitigation**: Added pre-import header validation, sanitized row trimming, duplicate code detection/upsert handling, and descriptive toast notifications indicating exact error lines.
* **Status**: **Handled**.

---

## 3. UI, Rendering & Frontend Architecture

### 3.1 Inline JavaScript HTML Attribute Escaping in Alpine.js
* **Edge Case**: Writing complex JavaScript functions, methods with quotes, or template literals (e.g., `${ctx.raw}`) directly inside an inline HTML attribute like `x-data="{ ... }"`.
* **Failure Mode**: The browser HTML parser interprets quotes as the closing attribute delimiter (`"`), causing raw JS code to spill onto the web page as visible plain text.
* **Mitigation**: Extracted all multi-line component logic into `<script>` tags using `Alpine.data('componentName', (config) => ({ ... }))` and passed clean PHP JSON encoded parameters into `x-data`.
* **Status**: **Handled**.

### 3.2 Responsive Table Layouts (Desktop Void vs. Mobile Crushing)
* **Edge Case**: Using hardcoded fixed pixel widths (e.g., `w-[850px]`) prevents tables from expanding on wide desktop screens (leaving wide empty gaps), while omitting `min-w` causes columns to crush, wrap chaotically, and overlap text on mobile devices.
* **Failure Mode**: Unusable layout on mobile phones (<400px) and awkward, narrow layouts on desktop monitors (1200px+).
* **Mitigation**: Applied the standardized responsive table pattern across all system tables:
  ```blade
  <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
      <table class="w-full min-w-[750px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
          ...
      </table>
  </div>
  ```
  with proportional column widths (`w-[...%] min-w-[...px]`) and `whitespace-nowrap` on action buttons and status badges.
* **Status**: **Handled**.

### 3.3 Chart.js Script Loading & Canvas Reuse Conflicts
* **Edge Case**: Loading Chart.js asynchronously via dynamic script creation inside Livewire SPA components causes race conditions where Alpine attempts to render before the script loads. When Livewire morphs the DOM, creating a new Chart on an existing `<canvas>` triggers canvas reuse errors (`Chart with ID '0' must be destroyed`).
* **Failure Mode**: Blank chart containers or runtime JavaScript console errors blocking dashboard execution.
* **Mitigation**: Global Chart.js CDN script placed in `<head>`, chart instances explicitly destroyed before re-instantiation, and DOM initialization deferred using `this.$nextTick()`.
* **Status**: **Handled**.

---

## 4. Evaluation Scheduling & Evaluation Windows

### 4.1 Modifying Active Schedules Mid-Evaluation
* **Edge Case**: An administrator attempting to delete or truncate an ongoing evaluation window while students and faculty are actively submitting ratings.
* **Failure Mode**: Incomplete submissions or orphaned records referencing inactive/deleted academic periods.
* **Mitigation**: UI safeguards and backend validation prevent deleting active evaluation schedules; admins are required to close the evaluation window first before deletion.
* **Status**: **Handled**.

### 4.2 Timezone & Server Time Skew
* **Edge Case**: Evaluator device clock differs from the institutional server time (`Asia/Manila`).
* **Failure Mode**: Client-side date checks incorrectly permitting or rejecting submissions outside the official evaluation window.
* **Mitigation**: All evaluation schedule checks and time comparisons are computed strictly server-side using Carbon in the application timezone.
* **Status**: **Handled**.

---

## 5. Security & Access Control Boundaries

### 5.1 Self-Disabling & Last-Admin Lockout
* **Edge Case**: An active logged-in administrator attempting to deactivate or delete their own account, or an admin deleting the last remaining active Administrator account.
* **Failure Mode**: Permanent lockout of institutional administrative access.
* **Mitigation**: Guard clauses in `toggleActive()` and `deleteUser()` block actions targeting `auth()->id()` or when `activeAdminCount <= 1`.
* **Status**: **Handled**.

### 5.2 Department Scoping in Faculty Reports & Rankings
* **Edge Case**: Administrative departments (Accounting, IT, HR) participating in Academic Faculty rankings or Teaching Effectiveness summary reports.
* **Failure Mode**: Non-faculty staff appearing on academic teacher scorecards with invalid 0.00 ratings or mismatched rubrics.
* **Mitigation**: Scoped faculty evaluation reports, summary exports, and teacher rankings strictly to Academic Departments (`type === 'academic'`) and teaching faculty (`role === 'faculty'`).
* **Status**: **Handled**.

---

## 6. AI Pipeline & NLP Engine

### 6.1 Flask Service Availability & Timeouts
* **Edge Case**: The Python Flask NLP microservice is offline or experiences port conflicts during evaluation submission.
* **Failure Mode**: Submission transaction hanging or crashing, preventing users from completing their evaluation.
* **Mitigation**: Asynchronous queue processing via `ProcessEvaluationSubmission` with a 5-second HTTP timeout and graceful fallback to default neutral sentiment without rolling back the evaluation submission.
* **Status**: **Handled**.

### 6.2 Bilingual Taglish & Complex Negation Interpretation
* **Edge Case**: Feedback comments containing double negatives or Tagalog slang (e.g., *"di naman gaano kasama"*).
* **Failure Mode**: Rule-based lexicons classifying constructive feedback as positive or vice versa.
* **Mitigation**: Hybrid NLP pipeline combining customized VADER Tagalog sentiment rules with TF-IDF Decision Tree classification and agreement gating.
* **Status**: **Handled**.

---

## 7. Future & Optional Enhancements (Open / Under Review)

The following items are non-critical edge cases and potential future iterations to review and enhance over time:

### 7.1 Advanced Leetspeak & Obfuscated Profanity
* **Description**: Users attempting to bypass the profanity filter using symbol substitution, spacing, or intentional character interleaving (e.g., `p@ng1t`, `b0b0`, `t a n g a`, `g_a_g_o`).
* **Current Behavior**: Handled via `ProfanityFilterService` multi-stage pre-parser mapping symbol substitutions (`@` $\rightarrow$ `a`, `1`/`!` $\rightarrow$ `i`, `0` $\rightarrow$ `o`, `3` $\rightarrow$ `e`, `$` $\rightarrow$ `s`), character repetition collapsing (`taaaangaaa` $\rightarrow$ `tanga`), and inter-character spacing stripping.
* **Status**: **Handled & Verified**.

### 7.2 Colloquial Taglish Dataset Expansion
* **Description**: Idiomatic Taglish feedback expressions containing rare slang, sarcasm, or multi-layered colloquialisms.
* **Current Behavior**: The hybrid VADER + Decision Tree engine successfully processes standard English, Tagalog, and common Taglish phrases with agreement gating.
* **Recommended Next Step**: Use the system's interactive retraining interface (`/settings/training`) to continuously label edge-case student feedback comments, expanding the confusion matrix validation dataset over multiple academic semesters.
* **Status**: **Open Enhancement**.

### 7.3 Cross-Paper Print Dimension Adaptations (Letter vs. A4 vs. Legal)
* **Description**: Administrators printing or exporting the 2-page GRC Evaluation Report across varying printer paper dimensions (e.g. US Letter $8.5 \times 11\text{ in}$ vs. Standard A4 $210 \times 297\text{ mm}$ vs. Legal).
* **Current Behavior**: Standardized with CSS print media queries (`page-break-inside: avoid`), but minor vertical margin adjustments may be needed when client printers use custom non-standard scaling.
* **Recommended Next Step**: In production guidelines, advise administrators to select standard Letter or A4 with default margins when printing via browser print dialog.
* **Status**: **Review / Documentation**.

### 7.4 Mid-Semester Section Transfers After Evaluation Submission
* **Description**: A student completes an evaluation for a professor in Section A, but is subsequently transferred to Section B or unenrolled from the class mid-semester.
* **Current Behavior**: Handled securely. Historical evaluation records maintain immutable entity references (`evaluator_id`, `evaluatee_id`, `semester_id`, `class_id`), ensuring ratings remain permanently preserved in aggregate reporting.
* **Recommended Next Step**: Maintain existing immutability policy so enrollment shifts do not alter historical evaluation records.
* **Status**: **Handled & Documented**.

### 7.5 Fresh Deployment Initialization with Zero Active Semesters
* **Description**: A completely clean system installation or database migration with no active Academic Year or Semester configured yet.
* **Current Behavior**: Handled safely. Dashboards and components include `@if($activeSemester)` null checks and display informative placeholder states (`"No active academic period configured"`) rather than failing.
* **Recommended Next Step**: Ensure onboarding instructions guide administrators to `/admin/evaluation-settings` as the initial setup step upon system deployment.
* **Status**: **Handled & Documented**.
