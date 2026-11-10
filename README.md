# A 360-Degree Evaluation System for Faculty and Staff Using VADER Sentiment Analysis and Decision Tree Classification (Capstone Project)

A role-based academic evaluation platform with an integrated sentiment analysis service to evaluate faculty performance, process 360-degree institutional appraisals, and classify qualitative feedback in English and Tagalog/Taglish.

---

## Table of Contents

- [Group Members & Roles](#group-members--roles)
- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Running the Application](#running-the-application)
- [User Roles and Demo Accounts](#user-roles-and-demo-accounts)
- [Sentiment Analysis Service](#sentiment-analysis-service)
- [Artisan Commands](#artisan-commands)
- [Testing and Code Quality](#testing-and-code-quality)
- [Documentation](#documentation)
- [Contacts](#contacts)
- [License](#license)

---

## Group Members & Roles

- **Hazel Ann S. Convicto** - Documentation/Project Manager
- **Dion L. Areglo** - Lead Developer/Backend Developer
- **Florenz L. Deonila** - Frontend/UiUx QA Tester
- **Christine D. Capina** - Documentation/QA Tester
- **Mark Benjie M. Arabe** - Documentation/Machine Learning Engineer

---

## Overview

The 360 Degree Evaluation System is a full-stack institutional evaluation platform designed for higher education institutions. Built with Laravel, Livewire, and a Python sentiment analysis service, the system combines quantitative evaluation ratings with natural language sentiment classification on student, dean, heads, professors, and staff feedback.

The sentiment pipeline processes English, Tagalog, and Taglish text to categorize qualitative comments into positive, neutral, or negative classifications.

---

## Key Features

### 360-Degree Evaluation Hierarchy
- Weighted evaluation breakdown:
  - Students (40%): Classroom instruction and learning delivery
  - Deans and Program Heads (40%): Administrative and curricular competence
  - Peer Faculty (15%): Departmental collaboration
  - Self-Evaluation (5%): Personal reflection and professional development

### Sentiment Analysis
- Dual-layer text classification using lexical scoring and a machine learning classifier
- Language detection optimized for English, Tagalog, and mixed Taglish comments
- Automatic classification of open-ended evaluation comments

### Administrative and Faculty Portals
- Role-specific dashboards for Deans, Program Heads, Faculty, Students, and Administrators
- Performance ranking, score distributions, and summarized qualitative feedback
- Automated deadline notifications for pending evaluations

---

## Tech Stack

| Layer | Technology |
| :--- | :--- |
| **Backend** | Laravel 12 (PHP 8.4+) |
| **Frontend** | Livewire 3, Livewire Volt, Alpine.js, Tailwind CSS |
| **Sentiment Service** | Python 3.10+, Flask, Scikit-Learn, NLTK |
| **Database** | SQLite (default) / MySQL |
| **Local Server** | Laravel Herd |
| **Testing** | Pest PHP, PHPStan, Laravel Pint |

---

## System Requirements

- **[Laravel Herd](https://herd.laravel.com/)** (Windows or macOS): provides PHP, Composer 2.x, Node.js 20.x, and the local web server
- **PHP 8.4 or higher**: select it in Herd (see Installation, step 3)
- **Python**: 3.10 to 3.13 and pip
- **Database**: SQLite 3 (default) or MySQL 8.0+
- **Internet access on first run**: the sentiment service downloads the NLTK VADER lexicon automatically

---

## Installation

### 1. Install Laravel Herd

Download and install Herd from https://herd.laravel.com/. Open Herd once after installing so it finishes setting up PHP and the web server.

### 2. Clone the Project into Herd's Directory

Clone the repository inside your Herd folder (by default `C:\Users\<you>\Herd` on Windows or `~/Herd` on macOS). Name the folder `evaluationsystem` so the local domain matches the one in `.env.example`:

```bash
cd ~/Herd
git clone https://github.com/thehonored1ne/EvaluationSystemCapstone.git evaluationsystem
cd evaluationsystem
```

> If you cloned it somewhere else, open the project folder in a terminal and run `herd link evaluationsystem` instead.

### 3. Set PHP 8.4 and Enable HTTPS

This project requires PHP 8.4 or higher. In the project folder, run:

```bash
herd isolate 8.4
herd secure
```

(You can also do both from the site's settings in the Herd app.)

### 4. Backend Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

> On Windows Command Prompt, use `copy .env.example .env` instead of `cp`. PowerShell and Git Bash accept `cp`.

`.env.example` is already configured for local use:

- `APP_URL` is set to `https://evaluationsystem.test`. If your folder has a different name, change it to `https://<your-folder-name>.test`.
- `AI_API_KEY` is a shared secret between Laravel and the Python sentiment service. No external account is needed. Any non-empty value works, as long as both services read the same `.env` (they do by default).
- `MAIL_MAILER=log`, so emails are written to `storage/logs/laravel.log` instead of being sent. To send real emails, set the `MAIL_*` values in your own `.env`.

### 5. Database Migration and Seeding

Create the SQLite database file, then migrate and seed:

```bash
# macOS / Linux / Git Bash:
touch database/database.sqlite

# Windows (PowerShell):
New-Item database/database.sqlite -ItemType File

php artisan migrate --seed
```

> Seeding creates thousands of demo records (including 3,200 students), so it may take a few minutes.

> Using MySQL instead? Herd's free version does not include it. Install MySQL separately (for example via DBngin), then update the `DB_*` values in `.env` before migrating.

### 6. Frontend Dependencies

```bash
npm install
npm run build
```

### 7. Sentiment Service Setup

```bash
cd python
python -m venv venv

# Windows (PowerShell):
.\venv\Scripts\activate

# macOS / Linux:
source venv/bin/activate

pip install -r requirements.txt
cd ..
```

---

## Running the Application

With Herd running, the Laravel app is already served at **https://evaluationsystem.test**. You do not need `php artisan serve`.

Open a separate terminal for each of the following services, from the project root:

| Service | Command | Default URL |
| :--- | :--- | :--- |
| **Laravel App** | Served by Herd | `https://evaluationsystem.test` |
| **Queue Worker** | `php artisan queue:listen --tries=1` | n/a |
| **Vite Dev Server** | `npm run dev` | `http://127.0.0.1:5173` |
| **Sentiment API** | `python python/app.py` | `http://127.0.0.1:5001` |

> **The queue worker is required.** Evaluation submissions and sentiment analysis are processed as queued jobs, so submitted evaluations will not be processed while it is not running.

> In a new terminal, activate the Python virtual environment first: `.\python\venv\Scripts\activate` (Windows PowerShell) or `source python/venv/bin/activate` (macOS / Linux).

> Optional: run `php artisan schedule:work` to enable the scheduled jobs (hourly deadline reminders and daily model retraining).

---

## User Roles and Demo Accounts

All demo accounts use the default password: `password`

| Role | ID | Description |
| :--- | :--- | :--- |
| **System Administrator** | `ADMIN-001` | Full institutional access, criteria management, user administration |
| **College Dean** | `DEAN-001` | College-wide evaluation summaries, rankings, and reports |
| **Program Head** | `PH-004` | Program faculty evaluations, peer reviews, and analytics |
| **Department Head** | `DH-2026-002` | Department staff evaluations and administrative reviews |
| **Faculty Professor** | `FAC-039` | Self-evaluation and personal evaluation results |
| **Student** | `2026-CCS-0452` | Evaluation submission for enrolled faculty |
| **Staff Member** | `STF-056` | Staff evaluations and peer reviews |

---

## Sentiment Analysis Service

The Python service exposes REST API endpoints for sentiment inference and model training. `/analyze` and `/train` require the `X-API-KEY` header to match `AI_API_KEY` in `.env`.

| Endpoint | Method | Description |
| :--- | :--- | :--- |
| `/analyze` | POST | Analyzes text sentiment and returns polarity scores and classification label |
| `/train` | POST | Retrains the classifier with updated labeled feedback |
| `/status` | GET | Returns service status and loaded model details |

---

## Artisan Commands

| Command | Description |
| :--- | :--- |
| `php artisan ai:train` | Fetches labeled comments from the database and triggers model retraining |
| `php artisan evaluations:send-reminders` | Checks active deadlines and sends reminder notifications for pending evaluations |
| `php artisan evaluations:send-reminders --force` | Dispatches reminder notifications immediately, ignoring scheduled thresholds |

---

## Testing and Code Quality

```bash
# Run test suite
./vendor/bin/pest

# Run static analysis
./vendor/bin/phpstan analyse

# Check code styling
./vendor/bin/pint --test

# Fix code styling
./vendor/bin/pint
```

---

## Documentation

Comprehensive project documentation is maintained in the Obsidian Vault:

- **Vault Root:** [`docs/eval-system-vault/`](docs/eval-system-vault/)
- **Dashboard:** [`docs/eval-system-vault/00 - Home/Dashboard.md`](docs/eval-system-vault/00%20-%20Home/Dashboard.md)
- **Visual Mindmap:** [`docs/eval-system-vault/System Overview.canvas`](docs/eval-system-vault/System%20Overview.canvas)

---

## Contacts

If you have any problem with the installation process. Please Contact our Lead Developer Dion Areglo.

- **Facebook** - https://web.facebook.com/dion.areglo.1
- **Email** - dion.areglo1234@gmail.com

---

## License

This project is open-source software licensed under the MIT License.
