# Security Audit Report & Architectural Vulnerability Assessment

**System**: Academic Evaluation System (Global Reciprocal Colleges)  
**Last Updated**: August 19, 2026  
**Auditor**: Antigravity AI Security Auditor  
**Audit Scope**: Secrets, Endpoints, RBAC, Database & ORM Integrity, Input Sanitization, Session Security, and Infrastructure Configuration.

---

## Executive Summary

The Academic Evaluation System implements defense-in-depth security controls across multiple layers:
1. **Zero Raw SQL Injections**: 100% of database interactions are executed via Laravel Eloquent ORM with PDO parameter binding.
2. **Strict RBAC Route Protection**: Role-based access control (Spatie Permissions) strictly guards admin, dean, program head, faculty, staff, and student routes.
3. **Internal Microservice Authentication**: The Python Flask NLP microservice is secured with an `X-API-KEY` middleware verification layer.
4. **Brute-Force & Flood Defense**: Multi-tier rate limiters (`throttle:global`, `throttle:auth`, and Livewire submission throttles) protect endpoints.
5. **Data Immutability & Anonymity**: Student evaluator identities are separated from faculty comment reviews to ensure unbiased feedback.

---

## 1. Secrets & Credentials Management

### Finding 1.1: Microservice API Key & Endpoint Configuration
* **Severity**: **Medium** (Previously Low)
* **Status**: **RESOLVED / MITIGATED**
* **Location**:
  * [app/Jobs/ProcessEvaluationSubmission.php](file:///c:/Users/USER/Herd/evaluationsystem/app/Jobs/ProcessEvaluationSubmission.php)
  * [app/Console/Commands/TrainAI.php](file:///c:/Users/USER/Herd/evaluationsystem/app/Console/Commands/TrainAI.php)
  * [python/app.py](file:///c:/Users/USER/Herd/evaluationsystem/python/app.py)
* **Risk**: Hardcoding microservice URLs or exposing unauthenticated NLP endpoints allows unauthorized network actors to invoke sentiment analysis or trigger resource-heavy retraining jobs.
* **Remediation**:
  * Extracted `AI_API_URL` and `AI_API_KEY` into `.env` and [config/services.php](file:///c:/Users/USER/Herd/evaluationsystem/config/services.php).
  * Implemented `check_api_key` `before_request` hook in Flask verifying the `X-API-KEY` header on `/analyze` and `/train` routes.

### Finding 1.2: Default Seeded User Passwords
* **Severity**: **High** (If deployed without remediation)
* **Status**: **RESOLVED / MITIGATED**
* **Location**: `database/seeders/DatabaseSeeder.php`, `database/seeders/PopulateDataSeeder.php`, `resources/views/livewire/default-password-modal.blade.php`
* **Risk**: Pre-seeded user accounts use standard development passwords (`password`). If run on a production server without immediate credential resets, accounts could be hijacked.
* **Remediation**:
  * Added automated `isUsingDefaultPassword()` detection and a prominent Livewire security modal (`default-password-modal.blade.php`) that alerts users upon login until they change their password.
  * In production deployments, administrators are guided to encourage immediate credential resets.
  * Seeder execution is restricted to local/development environments.

---

## 2. Authentication, Authorization & Account Integrity

### Finding 2.1: Self-Account Deletion & Administrator Lockout
* **Severity**: **High**
* **Status**: **RESOLVED / MITIGATED**
* **Location**: [app/Livewire/Admin/ManageEmployees.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-employees.blade.php)
* **Risk**: An administrator could accidentally deactivate or delete their own logged-in account, or delete the last remaining active Administrator account in the database, causing total system lockout.
* **Remediation**:
  * Added guard clauses in `toggleActive()` and `deleteUser()` preventing actions where `user_id === auth()->id()`.
  * Added validation ensuring at least one active Admin exists:
    ```php
    $activeAdminCount = User::whereHas('employee', fn($q) => $q->where('role', 'admin'))
        ->where('is_active', true)
        ->count();
    if ($activeAdminCount <= 1) { /* Abort with error */ }
    ```

### Finding 2.2: Relational User Role Escalation & Privilege Scoping
* **Severity**: **Medium**
* **Status**: **RESOLVED / MITIGATED**
* **Location**: [routes/web.php](file:///c:/Users/USER/Herd/evaluationsystem/routes/web.php) & Livewire View Components
* **Risk**: Direct URL access or ID parameter tampering across non-authorized roles (e.g. students accessing administrative reports or faculty accessing institutional rankings).
* **Remediation**:
  * Role middleware (`role:admin`, `role:dean`, `role:faculty`, `role:student`, `role:staff`) enforced on all route groups.
  * Rankings route access restricted to Admin, Dean, and Program Head roles.
  * Livewire authorization checks ensure users can only submit evaluations for assigned class enrollments or peer relationships.

---

## 3. Endpoints, Rate Limiting & Denial of Service

### Finding 3.1: Brute-Force & Flood Attacks on Public & Submission Endpoints
* **Severity**: **Medium**
* **Status**: **RESOLVED / MITIGATED**
* **Location**: [app/Providers/AppServiceProvider.php](file:///c:/Users/USER/Herd/evaluationsystem/app/Providers/AppServiceProvider.php), [routes/auth.php](file:///c:/Users/USER/Herd/evaluationsystem/routes/auth.php), [evaluation-form.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php)
* **Risk**: Automated scripts attempting password credential stuffing on `/login` or flooding evaluation submissions to distort rating averages.
* **Remediation**:
  * Global Web Rate Limiter: `throttle:global` (100 requests per minute).
  * Auth Route Limiter: `throttle:auth` (5 attempts per 3 minutes).
  * Evaluation Form Submission Limiter: Server-side throttle (5 attempts per 3 minutes) with client-side reactive Alpine.js countdown timer to prevent HTTP 429 crashes.

---

## 4. Input Sanitization, Profanity & File Upload Integrity

### Finding 4.1: Toxic / Vulgar Student Feedback Comments
* **Severity**: **Low**
* **Status**: **RESOLVED / MITIGATED**
* **Location**: [resources/views/livewire/evaluation-form.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/evaluation-form.blade.php)
* **Risk**: Profane or abusive student comments appearing on official faculty performance scorecards.
* **Remediation**:
  * Real-time debounced profanity filter running on student comment inputs.
  * Sanitizes curse words and triggers constructive toast warning messages before submission.

### Finding 4.2: Bulk Spreadsheet Import Injection & Corrupted Headers
* **Severity**: **Medium**
* **Status**: **RESOLVED / MITIGATED**
* **Location**: [resources/views/livewire/admin/manage-subjects.blade.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/manage-subjects.blade.php)
* **Risk**: Uploading malicious CSV/Excel files containing formula injection (`=cmd|...`) or corrupted headers causing fatal database exceptions.
* **Remediation**:
  * Strict column header whitelisting (`Code`, `Subject Name`, `Year Level`, `Semester Offered`).
  * Row-level data sanitization and type casting prior to insertion.
  * Duplicate code checks with toast feedback displaying line-by-line error details.

---

## 5. Database, ORM & Concurrency Integrity

### Finding 5.1: SQL Injection Protection
* **Severity**: **Critical**
* **Status**: **PASS (Zero Vulnerabilities)**
* **Location**: All Models & Livewire Components
* **Risk**: Unauthorized query execution or data exfiltration via input parameters.
* **Remediation**:
  * 100% of queries use Eloquent ORM or `DB::table()` with PDO prepared statements.
  * No raw query string concatenation (`DB::raw("... $input")`) exists in the codebase.

### Finding 5.2: Evaluation Modification Race Conditions
* **Severity**: **Medium**
* **Status**: **RESOLVED / MITIGATED**
* **Location**: [app/Livewire/Admin/EvaluationSettings.php](file:///c:/Users/USER/Herd/evaluationsystem/resources/views/livewire/admin/evaluation-settings.blade.php)
* **Risk**: Modifying or truncating academic periods or criteria point weights while an evaluation cycle is live.
* **Remediation**:
  * Active schedule removal is blocked while `is_evaluation_open === true`.
  * Database transactions wrap multi-step submission and recalculation jobs.

---

## 6. Production Infrastructure Readiness Checklist

| Security Control | Development Value | Production Target | Status |
| :--- | :--- | :--- | :--- |
| **`APP_DEBUG`** | `true` | `false` |  Enforce `false` on deploy |
| **`APP_ENV`** | `local` | `production` |  Set to `production` |
| **`SESSION_SECURE_COOKIE`** | `false` | `true` (HTTPS) |  Enforce with SSL |
| **`AI_API_KEY`** | Default dev key | Cryptographically random 64-char key |  Rotate on staging/prod |
| **`CACHE_DRIVER` / `QUEUE_CONNECTION`** | `database` / `sync` | `redis` / `database` worker |  Configure supervisor queue workers |