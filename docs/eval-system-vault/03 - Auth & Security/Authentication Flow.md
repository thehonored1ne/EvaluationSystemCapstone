---
title: "Authentication & Session Flow"
category: "Auth & Security"
tags: [auth, login, fortify, sanctum, session, security]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Roles & Permissions]] • [[Security Audit]]

# Feature Overview
We built a role-based authentication system. The login form accepts custom identifiers. The system directs users to specific dashboards. The admin manages all account creations.

# Technical Logic
The `users` table links to either employee or student profiles. The Livewire login component (`login.blade.php`) supports multi-identifier lookups:
- **Email Address**: (e.g. `dion.areglo1234@gmail.com`, `francheska.deasis020@grc.edu.ph`)
- **Student Number**: (e.g. `2026-01-0001`, `2026-CCS-0001`)
- **Employee Number**: (e.g. `FAC-001`, `STF-001`, `ADMIN-001`)
- **Admin Aliases**: (e.g. `admin`, `admin@grc.edu.ph`)

The query performs automatic whitespace trimming and case-insensitive matching (`LOWER(TRIM(?))`). Credentials are authenticated using Laravel Auth with `throttle:auth` rate-limiting. Spatie role middleware directs authenticated users to their corresponding dashboard (Admin, Dean, Program Head, Faculty, Department Head, Staff, Student).

# Test Credentials Overview (Password for all: `password`)
- **System Administrator**: `dion.areglo1234@gmail.com` / `ADMIN-001` / `admin`
- **College Dean**: `dean@grc.edu.ph` / `DEAN-001`
- **Program Heads**:
  - CCS: `ph.ccs@grc.edu.ph` / `PH-001`
  - COA: `ph.coa@grc.edu.ph` / `PH-002`
  - COE: `ph.coe@grc.edu.ph` / `PH-003`
  - CBAE: `ph.cbae@grc.edu.ph` / `PH-004`
- **Department Heads** (11 Administrative Offices): `dh.accounting@grc.edu.ph` (`DH-001`), `dh.admission@grc.edu.ph` (`DH-002`), etc.
- **Faculty Professors** (50): `FAC-001` to `FAC-050`
- **Staff Members** (57): `STF-001` to `STF-057`
- **Students** (3,200): `2026-01-0001` to `2026-04-0800`

# Default Password Security Advisory Modal
To safeguard user accounts and prevent security risks associated with default passwords, the system automatically checks if an authenticated user is operating with the initial system password (`password`).
- **Detection**: `User::isUsingDefaultPassword()` checks if `password_changed_at` is null and `Hash::check('password', $this->password)`.
- **Advisory Modal (`default-password-modal.blade.php`)**: Pops up on login prompting the user with **Change Password Now** (routes to `/settings/password`) or **Remind Me Later**.
- **Session Lifecycle**: Dismissing via "Remind Me Later" snoozes the reminder for the active session. The modal re-triggers upon future logins until the user updates their password.
- **Resolution**: Updating the password on `/settings/password` sets `password_changed_at` to the current timestamp and permanently resolves the advisory.

# File Manifest
- `c:\Users\USER\Herd\evaluationsystem\database\migrations\0001_01_01_000000_create_users_table.php`
- `c:\Users\USER\Herd\evaluationsystem\database\migrations\2026_08_20_000001_add_password_changed_at_to_users_table.php`
- `c:\Users\USER\Herd\evaluationsystem\app\Models\User.php`
- `c:\Users\USER\Herd\evaluationsystem\database\seeders\DatabaseSeeder.php`
- `c:\Users\USER\Herd\evaluationsystem\database\seeders\EvaluationPhase2Seeder.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\livewire\auth\login.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\livewire\default-password-modal.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\livewire\settings\password.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\resources\views\components\layouts\app\sidebar.blade.php`
- `c:\Users\USER\Herd\evaluationsystem\routes\auth.php`
- `c:\Users\USER\Herd\evaluationsystem\routes\web.php`
- `c:\Users\USER\Herd\evaluationsystem\bootstrap\app.php`

# Current State
Authentication is fully functional, responsive, and styled with official GRC deep red branding with isolated light-card aesthetics. Multi-identifier logins operate seamlessly across all 7 user roles, paired with automated default password security prompts.