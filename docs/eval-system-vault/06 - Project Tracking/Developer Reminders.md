---
title: "Developer Notes & Key Reminders"
category: "Project Tracking"
tags: [reminders, notes, developer, quick-tips]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Task Roadmap & Todo]]

## 6/18/26
- **Larastan Analysis**: Run `./vendor/bin/phpstan analyse` to verify type safety.
- **PHPStan Baseline Rebuild**: Run `./vendor/bin/phpstan analyse --generate-baseline` to update baseline filters.
- **Pint Formatting**: Run `./vendor/bin/pint` to auto-format files.
- **Activity Log Tinker Checks**:
  * Get recent logs: `Spatie\Activitylog\Models\Activity::latest()->take(5)->get()`
  * Get specific model logs (e.g. User): `App\Models\User::first()->activities`

## 6/17/26
- Aligned `manage-questions` admin component to use the updated evaluation types: `upward_student`, `upward_employee`, `downward`, `peer`, `self`.
- Migrated global font to `Inter` and redesigned `welcome.blade.php` with a containerless floating layout, elegant `Playfair Display` serif header, and a full-cover gradient background.
- Integrated a rolling vertical digit `<x-odometer>` component (YouTube sub-count style) into the Admin Dashboard stats and AI Sentiment Analysis sub-cards.

## 6/10/26
- 2 branch created (addbutton/admin, fix/admin)
- dev is updated
- main branch is behind
- uat branch is behind
- updated 3 branch
- deleted 2 created branch

## 6/13/26
- **Local Dev Server Run**: Run the Python Flask AI service using the virtual environment:
  `.\python\venv\Scripts\python.exe python/app.py` (running on port 5001).
- **AI Train & Backfill**: Run the training CLI command:
  `php artisan ai:train`
- **AI Tests**: Run the sentiment analysis test suite:
  `php artisan test --filter AISentimentTest`
- **Production Deploy Reminder**: Before deploying to production, replace the Flask dev server with a proper WSGI server (e.g. Gunicorn). Run `pip install gunicorn` then `gunicorn app:app`.
- **Tinker Queries**: Run `php artisan tinker` and use these commands to inspect sentiment:
  * Latest sentiment details: `App\Models\EvaluationSentiment::latest()->first()`
  * Latest sentiment with comment text: `App\Models\EvaluationSentiment::with('evaluation')->latest()->first()`
  * All evaluations that have sentiment: `App\Models\Evaluation::has('sentiment')->with('sentiment')->get()`