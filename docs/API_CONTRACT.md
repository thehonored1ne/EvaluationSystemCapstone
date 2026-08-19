# AI Service API Contract

This document outlines the API endpoints hosted by the Python Flask service (`http://127.0.0.1:5001`) and consumed by the Laravel application.

---

## Authorization Security
All endpoints require a header authorization check. If the header is missing or incorrect, the server returns a `401 Unauthorized` response.

* **Header Name**: `X-API-KEY`
* **Header Value**: Must match the `AI_API_KEY` environment variable configured in `.env`.

---

## 1. POST `/analyze`
Used for real-time sentiment analysis of submitted evaluation comments.

### Request Payload
* **URL**: `http://127.0.0.1:5001/analyze`
* **Method**: `POST`
* **Content-Type**: `application/json`
* **Headers**: `X-API-KEY: <your_api_key>`

```json
{
  "comment": "Napakabait at mahusay magturo si ma'am, marami akong natutunan."
}
```

### Response Payload (Success - 200 OK)
```json
{
  "comment": "Napakabait at mahusay magturo si ma'am, marami akong natutunan.",
  "vader_score": 0.825,
  "vader_label": "positive",
  "dt_label": "positive",
  "language_mode": "taglish"
}
```

* **`vader_score`**: A decimal value between `-1.0` (negative) and `1.0` (positive) representing compound sentiment valence.
* **`vader_label`**: Rules-based classification (`positive` / `neutral` / `negative`).
* **`dt_label`**: Machine learning classification predicted by the Decision Tree classifier.
* **`language_mode`**: Language classification determined by particle heuristics (`taglish` / `english`).

---

## 2. POST `/train`
Used to retrain the Decision Tree model using historical database comments combined with static seed data.

### Request Payload
* **URL**: `http://127.0.0.1:5001/train`
* **Method**: `POST`
* **Content-Type**: `application/json`
* **Headers**: `X-API-KEY: <your_api_key>`

```json
{
  "comments": [
    "Magaling magturo at mabait.",
    "Boring ang lesson at mabagal magdiscuss.",
    "Sakto lang."
  ]
}
```

### Response Payload (Success - 200 OK)
```json
{
  "status": "success",
  "samples_trained": 25,
  "db_samples": 3,
  "seed_samples": 22
}
```

---

## Timeout & Fallback Policies
* **Connection Timeout**: The Laravel HTTP client is configured with a **5-second timeout** on `/analyze` and a **60-second timeout** on `/train`.
* **Failure Resiliency**: If the Flask API fails to respond or returns an error, the Laravel background job (`ProcessEvaluationSubmission`) swallows the exception and logs a warning. The core evaluation submission itself succeeds, leaving the database sentiment record `null` for future backfilling.
