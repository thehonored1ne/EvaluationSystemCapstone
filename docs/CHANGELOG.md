# Changelog

All notable changes to the **Evaluation System** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

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
