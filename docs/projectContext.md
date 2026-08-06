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
- **Evaluation Submission**: Idempotent queue-based submissions (runs on `sync` connection). Dashboard buttons are locked with "Processing" / "Completed" badges once submitted to prevent double-evaluation.
- **Profanity Filter**: Real-time removal of curse words from evaluation comments with constructive toast warnings.
- **Evaluation Windows**: Configurable schedules with conflict protection modals ("Overwrite" / "Remove"), schedule removal protection when evaluations are open, and Asia/Manila timezone alignment.
- **AI Sentiment Pipeline**: Integrates Flask-based VADER sentiment analysis (with Tagalog/Taglish custom lexicon) and a multimodal Decision Tree Classifier (combining text TF-IDF + rating values) to analyze evaluation comments, featuring a confidence Agreement Gate, daily scheduled retraining, and a Human-in-the-Loop sentiment override management dashboard (with validation accuracy, a Confusion Matrix, and fully anonymized data views).
- **Custom Searchable Selects**: Custom Alpine-based `<x-searchable-select>` replacing native listbox dropdowns.
- **Reusable Confirmation Modals**: Standardized modal `<x-confirmation-modal>` with custom details and relationship cascade warnings before deletions.
- **Notifications**: Automated sidebar badges that clear instantly upon visiting the notifications page.
- **Summary Reports**: Tabular summary reports of evaluation results filtered by semester/school year, downloadable as a print-optimized PDF, with strict role-based department filtering (Admin sees all; Dean/Program Head see department only).
- **Skeleton Shimmer Loading**: Hardware-accelerated shimmer skeleton loader page shells display instantly upon navigating to any admin-facing dashboard or management portal, loading actual data asynchronously in the background.

---

## Milestones & Summary of Work Done

### August 6, 2026
- **Unified User Portals & Suffix Support**: Consolidated user management into `/admin/employees` (Deans, Program Heads, Faculty, Staff, Admin) and `/admin/students` (Students). Added `suffix` database column and `formatted_name` accessor (`Last Name, First Name Middle Name Suffix`).
- **Self-Account & System Safeguards**: Implemented backend guards preventing active logged-in administrators (`auth()->id()`) from self-disabling or self-deleting (with visual `YOU` badges), and added a safeguard protecting the last remaining active Administrator account.
- **Admin Dashboard Restructuring**: Overhauled top 4 statistic cards (Total Employees, Total Students, Current Evaluation Progress with expected sum formula, Pending Submissions with live odometer counter), simplified the Evaluation Period Status card (explicit date-time windows, real-time schedule indicator badge, and specific button text), and updated the overall feedback sentiment card for all evaluator groups.
- **Role-Anonymized Submission Stream**: Transformed recent submissions feed into a strict role-relationship stream (`Student Evaluation`, `Self Evaluation`, `Dean Evaluation`, `Program Head Evaluation`, `Peer Evaluation`, `Supervisor Evaluation`, `Staff Evaluation`) maintaining complete anonymity.
- **Branding & Visual System**: Integrated official logo asset `public/GRC-o-Evaluation-LOGO.png` across header and sidebar navigation, and standardized all dashboard cards with a full 4-side 2px dark red (`#800000`) border.

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
