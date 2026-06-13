# Edge Cases & Vulnerabilities

This document lists identified edge cases and potential failure modes of the **Evaluation System** project.

## 1. AI Pipeline & Flask Service
* **Flask API Service Down**: If the Flask server crashes or is unavailable (port conflict, network timeout), the Laravel job will fail to get a sentiment.
  * *Status*: Handled. The HTTP request has a 5-second timeout and fails silently without crashing the main evaluation submission database transaction.
* **Empty/Whitespace-only Comments**: Comments containing only spaces, tabs, or newlines might trigger empty payload errors on Flask.
  * *Status*: Handled. The queue job trims and ignores empty comment strings.
* **Non-Alphabetical Comments**: Input consisting of only symbols, numbers, or single characters (e.g. *"?"*, *"*.*"*, *"123"*). 
  * *Status*: Open. Flask will analyze them, but they may yield inaccurate `neutral` classifications.
* **Complex Negations**: Tagalog statements like *"di naman gaano kasama"* (not really that bad) might confuse the lexicon-based VADER model due to multiple modifier words.
  * *Status*: Open. Can be resolved by refining the custom python parser rules.

## 2. Profanity Filter
* **Leetspeak and Obfuscation**: Users bypassing the filter by inserting symbols or spaces in curse words (e.g., `p@ng1t`, `g a l i t`).
  * *Status*: Open. The regex-based profanity filter does not currently catch advanced obfuscated text.

## 3. Database & System Integrity
* **Cascading Account Deletions**: Deleting a Dean, Program Head, or Faculty member deletes their user account and associated profiles.
  * *Status*: Handled. The confirmation modal alerts the admin, but we must make sure all dependent tables (classes, student enrollments) handle this cascading relationship safely.
* **Evaluation schedule edits**: Admins modifying or closing active evaluation windows while a student is mid-evaluation.
  * *Status*: Handled. UI protection prevents removal of active schedules, but active sessions must expire gracefully.
* **Timezone Mismatches**: If the user's browser time is ahead/behind the database server (which is set to `Asia/Manila`), they might be blocked from evaluating due to client-server time sync skew.
  * *Status*: Open. We should ensure evaluation windows are calculated strictly using the server-side timezone.
