# System Suggestions & Improvements

This document lists suggestions for enhancing the **Evaluation System** project.

## 1. Analytics & Visualizations
* **Sentiment Trend Dashboards**: Build charts (e.g., using Chart.js) in the Admin and Dean portals to display positive, neutral, and negative sentiment distribution per department, program, or faculty.
* **TF-IDF Word Clouds**: Generate interactive word clouds in reports to show the most distinguishing descriptors used in comments for specific faculty members (utilizing TF-IDF weights to automatically ignore common filler words).

## 2. Infrastructure & Queue Management
* **Asynchronous Queue Driver**: Migrate `QUEUE_CONNECTION` from `sync` to `database` or `redis` in staging and production to offload the Flask HTTP call entirely from the web request lifecycle, preventing slow user submissions.
* **Flask Process Supervisor**: Deploy the Python API under a process manager like PM2, Systemd, or Supervisor in production to ensure it auto-restarts if it crashes.

## 3. NLP & Text Classification Enhancements
* **Tagalog-English Code-Switching Detection**: Add a lightweight language detector to route pure English comments to standard VADER, and Tagalog/Taglish comments to our custom lexicon.
* **Negation and Valence Shifters**: Improve Tagalog phrase parsing (e.g. *"hindi masyadong magaling"*) in the Python API to prevent misclassifying combined negators.
* **Model Versioning and Retraining UI**: Add a simple Admin control panel to trigger `php artisan ai:train` manually, displaying the last training date and model accuracy metrics.

## 4. System & Security Features
* **Audit Logs for Deletions**: Record administrative actions in an audit log (especially account deletions and evaluation schedule modifications).
* **Feedback Archival**: Prevent historical evaluation sentiment data from being deleted if a faculty member or student account is deleted (e.g., using soft deletes or archiving tables).
