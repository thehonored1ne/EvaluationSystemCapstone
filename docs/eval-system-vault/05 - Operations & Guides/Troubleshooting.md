---
title: "Troubleshooting & Common Fixes"
category: "Operations & Guides"
tags: [troubleshooting, debug, errors, fixes, faq]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Deployment Guide]] • [[Edge Cases & Testing]]

# Development Troubleshooting Guide

This guide contains common development issues, error messages, and their solutions for the **Evaluation System** project.

---

## 1. AI Pipeline & Python Issues

### Error: `An attempt was made to access a socket in a way forbidden by its access permissions`
* **Cause**: The default port (5000) or selected port (5001) is already in use by another process (on Windows, PID 4 "System" often locks port 5000).
* **Solution**: 
  1. Find the process using port 5001:
     ```powershell
     netstat -ano | findstr :5001
     ```
  2. If it's a python process, you can terminate it:
     ```powershell
     Stop-Process -Id <PID>
     ```
  3. Alternatively, update the port configuration in `python/app.py`, `app/Console/Commands/TrainAI.php`, `app/Jobs/ProcessEvaluationSubmission.php`, and `tests/Feature/AISentimentTest.php`.

---

### Error: `LookupError: Resource 'sentiment/vader_lexicon.zip' not found`
* **Cause**: NLTK VADER lexicon is not downloaded.
* **Solution**: The app has auto-download code in `python/app.py`, but you can download it manually. Activate your venv and run:
  ```bash
  python -c "import nltk; nltk.download('vader_lexicon')"
  ```

---

### Error: `cURL error 28: Operation timed out` (in Laravel log)
* **Cause**: Laravel tried to connect to the Flask API, but the Flask server is either not running, or is running on the wrong port/host.
* **Solution**: Ensure your Python dev server is running on `http://127.0.0.1:5001` via:
  ```powershell
  .\python\venv\Scripts\python.exe python/app.py
  ```

---

## 2. Database & Migration Issues

### Error: `Database is locked` (SQLite)
* **Cause**: Multiple write transactions are attempting to write to the SQLite file simultaneously, or a transaction was left uncommitted.
* **Solution**:
  1. Restart your PHP/Laravel local dev server or Herd.
  2. If running tests, ensure you are using the `RefreshDatabase` trait which handles transaction rollbacks cleanly.

---

### How to completely reset and seed the database:
If your database schema becomes out of sync:
```powershell
php artisan migrate:fresh --seed
php artisan ai:train
```

---

## 3. Frontend & Asset Compilation

### Error: `Vite manifest not found`
* **Cause**: You are attempting to load a page, but the compiled frontend assets haven't been generated.
* **Solution**: Start the hot-reloading dev compiler in the root directory:
  ```bash
  npm run dev
  ```
  Or build them for production:
  ```bash
  npm run build
  ```

---

## 4. Livewire Testing & Lazy Loading

### Error: `assertSee()` or status checks failing on HTTP requests to Lazy components
* **Cause**: In Livewire 3, when a component is marked as `#[Lazy]`, standard HTTP GET requests (like `$this->get('/route')`) will initially return the placeholder stub view instead of the actual fully-mounted component page. Assertions on data or text within the component will therefore fail.
* **Solution**: Call `\Livewire\Livewire::withoutLazyLoading()` inside the `beforeEach` hook of your feature tests. This forces all lazy components to load synchronously during the test run:
  ```php
  beforeEach(function () {
      \Livewire\Livewire::withoutLazyLoading();
  });
  ```

---

## 5. Windows OS Specific Issues

### Error: `Permission denied` or view cache files locked during concurrent testing
* **Cause**: On Windows systems, concurrent file access might temporarily lock compiled view files in `storage/framework/views`.
* **Solution**: Clear the compiled views cache before running your tests:
  ```powershell
  php artisan view:clear
  php artisan test
  ```
