# Academic Evaluation System

A role-based academic evaluation platform with an integrated sentiment analysis service to evaluate faculty performance, process 360-degree institutional appraisals, and classify qualitative feedback in English and Tagalog/Taglish.

---

## Table of Contents

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
- [License](#license)

---

## Overview

The Academic Evaluation System is a full-stack institutional evaluation platform designed for higher education institutions. Built with Laravel, Livewire, and a Python sentiment analysis service, the system combines quantitative evaluation ratings with natural language sentiment classification on student and peer feedback.

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
| **Backend** | Laravel 12 (PHP 8.2+) |
| **Frontend** | Livewire 3, Livewire Volt, Alpine.js, Tailwind CSS |
| **Sentiment Service** | Python 3.11+, Flask, Scikit-Learn, NLTK |
| **Database** | MySQL / SQLite |
| **Testing** | Pest PHP, PHPStan, Laravel Pint |

---

## System Requirements

- **PHP**: 8.2 or higher (with `pdo`, `mbstring`, `openssl`, `curl` extensions enabled)
- **Composer**: 2.x
- **Node.js**: 20.x or higher and npm
- **Python**: 3.10 to 3.13 and pip
- **Database**: MySQL 8.0+ or SQLite 3

---

## Installation

### 1. Backend Setup

```bash
git clone https://github.com/thehonored1ne/EvaluationSystemCapstone.git
cd EvaluationSystemCapstone

composer install
cp .env.example .env
php artisan key:generate
```

### 2. Database Migration and Seeding

```bash
php artisan migrate --seed
```

### 3. Frontend Dependencies

```bash
npm install
npm run build
```

### 4. Sentiment Service Setup

```bash
cd python
python -m venv venv

# Windows (PowerShell):
.\venv\Scripts\activate

# macOS / Linux:
source venv/bin/activate

pip install -r requirements.txt
```

---

## Running the Application

### Concurrent Development (Recommended)

Run all services concurrently using the Composer script:

```bash
composer run dev
```

### Manual Service Execution

| Service | Command | Default URL |
| :--- | :--- | :--- |
| **Laravel Server** | `php artisan serve` | `http://127.0.0.1:8000` |
| **Vite Dev Server** | `npm run dev` | `http://127.0.0.1:5173` |
| **Sentiment API** | `python python/app.py` | `http://127.0.0.1:5001` |

---

## User Roles and Demo Accounts

All demo accounts use the default password: `password`

| Role | Email | Description |
| :--- | :--- | :--- |
| **System Administrator** | `admin@grc.edu.ph` | Full institutional access, criteria management, user administration |
| **College Dean** | `dean@grc.edu.ph` | College-wide evaluation summaries, rankings, and reports |
| **Program Head** | `ph.ccs@grc.edu.ph` | Program faculty evaluations, peer reviews, and analytics |
| **Department Head** | `dh.it@grc.edu.ph` | Department staff evaluations and administrative reviews |
| **Faculty Professor** | `jerome.macinas@grc.edu.ph` | Self-evaluation and personal evaluation results |
| **Student** | `student1@grc.edu.ph` | Evaluation submission for enrolled faculty |
| **Staff Member** | `staff1@grc.edu.ph` | Staff evaluations and peer reviews |

---

## Sentiment Analysis Service

The Python service exposes REST API endpoints for sentiment inference and model training:

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

## License

This project is open-source software licensed under the [MIT License](LICENSE).
