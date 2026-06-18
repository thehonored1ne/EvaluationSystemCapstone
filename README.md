# Academic Evaluation System

A role-based evaluation system with an integrated AI sentiment analysis pipeline to evaluate faculty performance and classify textual feedback.

---

## Tech Stack
* **Backend**: Laravel (PHP)
* **Frontend**: Livewire, Livewire Volt (single-file components), Alpine.js, TailwindCSS
* **Database**: SQLite
* **AI Engine**: Python Flask API
  * **Lexicon Sentiment Analysis**: NLTK VADER (customized with Tagalog/Taglish lexicon modifiers)
  * **Machine Learning Classifier**: Scikit-Learn TF-IDF vectorizer + Decision Tree Classifier

---

## Getting Started

### Prerequisites
* **PHP** (8.3 or higher recommended)
* **Node.js** & npm (latest LTS)
* **Python** (3.13 or higher recommended)
* **Composer** (PHP Package Manager)

---

### Step-by-Step Installation

#### 1. Setup the Laravel Application
1. Clone the repository and navigate into the root directory.
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Copy the environment file:
   ```bash
   cp .env.example .env
   ```
4. Generate the application key:
   ```bash
   php artisan key:generate
   ```
5. Run database migrations and seeders (initializes roles, criteria, users, and demo classes):
   ```bash
   php artisan migrate --seed
   ```

#### 2. Setup the Frontend Assets
1. Install Node modules:
   ```bash
   npm install
   ```
2. Start the Vite development asset compiler:
   ```bash
   npm run dev
   ```

#### 3. Setup the AI Python Service
1. Navigate to the `python/` directory:
   ```bash
   cd python
   ```
2. Create and activate a virtual environment:
   * **Windows**:
     ```bash
     python -m venv venv
     .\venv\Scripts\activate
     ```
   * **macOS/Linux**:
     ```bash
     python3 -m venv venv
     source venv/bin/activate
     ```
3. Install Python dependencies:
   ```bash
   pip install -r requirements.txt
   ```

---

## Running the Application Locally

To run the system locally, you need two terminal windows running concurrently:

### Terminal 1: Python Flask API (Port 5001)
Start the AI Sentiment server:
```bash
.\python\venv\Scripts\python.exe python/app.py
```

### Terminal 2: Laravel Server
Run your local server via Laravel Herd or standard PHP server:
```bash
php artisan serve
```

### Initial AI Model Training
After starting the Python Flask server, run this command once to train the Decision Tree classifier using seed data and existing database comments:
```bash
php artisan ai:train
```

---

## Code Quality, Formatting & Static Analysis

To ensure code quality, type safety, and formatting consistency, run the following commands:

### Running Tests (Pest)
Run the full PHP test suite:
```bash
./vendor/bin/pest
```

### Static Analysis (Larastan/PHPStan)
Run PHPStan to analyze code types and logic:
```bash
./vendor/bin/phpstan analyse
```

### Code Formatting (Laravel Pint)
Automatically fix styling and formatting issues (PSR-12 / Laravel standards):
```bash
./vendor/bin/pint
```
Or check formatting compliance without modifying files:
```bash
./vendor/bin/pint --test
```
