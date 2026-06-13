# Production Deployment Guide

This document lists requirements, configuration settings, and server commands required to safely deploy the **Evaluation System** to production.

---

## 1. Environment Configurations
Configure the following options in your production `.env` file:

```env
APP_ENV=production
APP_DEBUG=false

# Queue Driver
# Recommend 'database' or 'redis' for asynchronous background processing
QUEUE_CONNECTION=database

# Timezone alignment
APP_TIMEZONE=Asia/Manila

# AI Service Configuration
AI_API_URL=http://127.0.0.1:5001
AI_API_KEY=your_secure_random_api_key_here
```

*Note: If setting `QUEUE_CONNECTION=database`, run `php artisan queue:table` and `php artisan migrate` on setup, and ensure a queue worker is active on the server using Supervisor. Also ensure `AI_API_KEY` is kept secret and matches on both the Laravel server and Flask service environment.*

---

## 2. Production Python Flask Setup
Running the Flask debug server (`app.run()`) is **not** safe for production. You must use a production WSGI server.

### 1. Install Gunicorn (for Linux servers)
Within your virtual environment:
```bash
pip install gunicorn
```

### 2. Run with Gunicorn
Bind to localhost port `5001` with multiple workers (e.g. 4 workers):
```bash
gunicorn --workers 4 --bind 127.0.0.1:5001 python.app:app
```

### 3. Setup Process Manager (Systemd)
To ensure the Flask server runs continuously and auto-restarts on system boot, create a systemd service file at `/etc/systemd/system/flask-ai.service`:

```ini
[Unit]
Description=Gunicorn instance to serve AI Sentiment Flask API
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/evaluationsystem/python
Environment="PATH=/var/www/evaluationsystem/python/venv/bin"
ExecStart=/var/www/evaluationsystem/python/venv/bin/gunicorn --workers 4 --bind 127.0.0.1:5001 app:app

[Install]
WantedBy=multi-user.target
```

Enable and start the service:
```bash
sudo systemctl enable flask-ai
sudo systemctl start flask-ai
```

---

## 3. Laravel Scheduler Config
Our automated AI retraining job (`php artisan ai:train`) is scheduled to run daily at midnight. To activate Laravel's scheduler, add the following cron entry to your production server:

1. Open crontab editor:
   ```bash
   crontab -e
   ```
2. Add the Laravel schedule runner line:
   ```text
   * * * * * cd /var/www/evaluationsystem && php artisan schedule:run >> /dev/null 2>&1
   ```
