# System Suggestions, Roadmap & Future Enhancements

This document outlines strategic suggestions, architectural improvements, and feature roadmap ideas for the **Academic Evaluation System** (Global Reciprocal Colleges).

---

## 1. Accomplished & Implemented Suggestions

* [X] **Admin Visual Analytics (Chart.js)**: Integrated interactive bar charts for 1-5 Star Ratings Distribution and Department Average Comparisons. *(Implemented Aug 2026)*
* [X] **Official 2-Page Print-Ready GRC Scorecard**: Created exact print replica of the GRC teaching effectiveness evaluation scorecard with 360° rubric, 200-point scale, and AI qualitative insights. *(Implemented Aug 2026)*
* [X] **Model Versioning & Retraining UI**: Added dedicated `/settings/training` interface with confusion matrix metrics, accuracy percentage, and manual retraining triggers. *(Implemented Aug 2026)*
* [X] **Subject Catalog Bulk Import / Export**: Added CSV/Excel import with header format validation, duplicate detection, and downloadable template. *(Implemented Aug 2026)*
* [X] **Single-Question Interactive Evaluation Wizard**: Mobile-friendly 1-question-per-step wizard with auto-advance, rating pills navigator, and comment profanity filter. *(Implemented Aug 2026)*
* [X] **Multi-Role Activity Logging**: Integrated Spatie Activitylog tracking administrative updates, deletions, and user modifications. *(Implemented Aug 2026)*
* [X] **Multi-Tier Rate Limiting**: Added `throttle:global`, `throttle:auth`, and Livewire submission throttling with client-side reactive countdown timers. *(Implemented Aug 2026)*

---

## 2. User & Data Management Enhancements

### 2.1 Bulk Import & Export for Students and Employees
* **Description**: Extend the spreadsheet import/export pattern currently in Subjects (`/admin/subjects`) to Students (`/admin/students`) and Employees (`/admin/employees`).
* **Value**: Enables institutional registrars and HR staff to onboard hundreds of new student enrollments and faculty hires via CSV or Excel (`.xlsx`) spreadsheets at the beginning of each semester.
* **Key Features**:
  * Downloadable standard CSV templates with pre-filled sample columns.
  * Validation checking for duplicate Student IDs / Employee Numbers and valid program/department references.
  * Bulk export for external institutional reporting.

### 2.2 Automated Academic Period Rollover Wizard
* **Description**: A multi-step administrative wizard for closing an academic term and preparing the next.
* **Value**: Eliminates manual class re-assignments each semester.
* **Key Features**:
  * Automatically sets current semester `is_active = false` and activates the subsequent semester.
  * Promotes student year levels ($1^{\text{st}}\text{ Year} \rightarrow 2^{\text{nd}}\text{ Year}$, etc.).
  * Clones recurring class schedules and subjects from previous academic periods as draft templates.

---

## 3. Analytics, Reporting & Insights

### 3.1 TF-IDF Keyword & Thematic Word Clouds
* **Description**: Visual word clouds rendered on Page 2 of individual faculty reports showing the most distinguishing descriptive terms used by students.
* **Value**: Allows deans and faculty to instantly absorb the qualitative essence of 100+ student comments in a single visual glance.
* **Implementation Note**: Utilize Python TF-IDF feature weights to filter out generic stopwords and highlight statistically significant praise or constructive themes.

### 3.2 Longitudinal Multi-Semester Growth Trends
* **Description**: Multi-semester historical trend line charts (spanning 2 to 4 years) on faculty and departmental dashboards.
* **Value**: Tracks pedagogical improvement, demonstrating how specific interventions (e.g. teaching workshops, syllabus updates) positively impact ratings over time.

---

## 4. Notifications & Engagement Automation

### 4.1 Scheduled Automated Deadline Reminders
* **Description**: An automated background cron job that periodically checks for pending evaluations as deadlines approach.
* **Value**: Drastically increases student and peer turnout rates without requiring manual administrative intervention.
* **Key Features**:
  * Configurable broadcast schedules (e.g. *7 days before closing*, *3 days before closing*, *Final 24 hours*).
  * Targeted notifications sent strictly to users with pending/incomplete evaluation queues.

### 4.2 Real-Time WebSockets Push Notifications
* **Description**: Integrating Laravel Reverb or Echo for real-time browser push alerts.
* **Value**: Instant feedback when administrative announcements, new evaluation schedules, or batch reminders are broadcast, without requiring a manual page refresh.

---

## 5. NLP & Machine Learning Enhancements

### 5.1 Advanced Leetspeak & Symbol Normalization in Profanity Filter
* **Description**: A character normalization pre-parser that maps symbol substitutions (e.g. `@` $\rightarrow$ `a`, `1`/`!` $\rightarrow$ `i`, `0` $\rightarrow$ `o`) and strips inter-character spacing prior to regex inspection.
* **Value**: Prevents evasive vulgarities from bypassing comment sanitization.

### 5.2 Tagalog-English Code-Switching Context Router
* **Description**: Automatic language detection routing comments to dedicated linguistic sub-pipelines:
  * Pure English comments $\rightarrow$ Standard VADER sentiment engine.
  * Pure Tagalog / Taglish comments $\rightarrow$ Custom Tagalog lexicon with localized valence shifters.
* **Value**: Minimizes misclassification on colloquial expressions containing regional idioms.

---

## 6. Infrastructure & Deployment Scaling

### 6.1 Redis Queue Driver & Supervisor Daemons
* **Description**: Transitioning `QUEUE_CONNECTION` from `database` to `redis` with background Supervisor workers in production.
* **Value**: Provides sub-millisecond job dispatching for asynchronous evaluation score calculations and NLP sentiment requests during peak final-exam submission hours.

### 6.2 Python Microservice Process Supervision
* **Description**: Managing the Flask AI microservice under Systemd, Supervisor, or PM2 on the production server.
* **Value**: Guarantees zero-downtime auto-restarting and health check monitoring if the Python process experiences memory leaks or unhandled exceptions.
