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
- **AI Sentiment Pipeline**: Integrates Flask-based VADER sentiment analysis (with Tagalog/Taglish custom lexicon) and a Decision Tree Classifier to analyze evaluation comments.
- **Custom Searchable Selects**: Custom Alpine-based `<x-searchable-select>` replacing native listbox dropdowns.
- **Reusable Confirmation Modals**: Standardized modal `<x-confirmation-modal>` with custom details and relationship cascade warnings before deletions.
- **Notifications**: Automated sidebar badges that clear instantly upon visiting the notifications page.
- **Summary Reports**: Tabular summary reports of evaluation results filtered by semester/school year, downloadable as a print-optimized PDF, with strict role-based department filtering (Admin sees all; Dean/Program Head see department only).

---

## Milestones & Summary of Work Done

### June 16, 2026
- **Admin Dashboard Upgrades**: Added top statistics cards, live schedule status trackers, Live AI Sentiment Lexicon analytics breakdown, department completion rates progress, anonymized recent submissions feed, and quick system shortcuts.
- **UI Alignment Enhancements**: Left-aligned the Admin Dashboard header text, right-aligned the Active Period badge on all viewports, and stacked/expanded date picker inputs under Evaluation Settings.

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
