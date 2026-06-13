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