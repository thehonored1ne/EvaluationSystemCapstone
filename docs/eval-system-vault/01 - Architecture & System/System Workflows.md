---
title: "Evaluation & Operational Workflows"
category: "Architecture & System"
tags: [workflow, business-logic, evaluation-cycle, processes]
created: 2026-08-28
last_updated: 2026-08-28
---

> [!INFO] Navigation
> **Related Notes:** [[Dashboard]] • [[Module Breakdown]] • [[Roles & Permissions]] • [[Task Roadmap & Todo]]

# Git Workflow: dev → uat → main

## Branch Overview

| Branch | Purpose |
|--------|---------|
| `dev`  | Where you code and add features |
| `uat`  | Testing/QA — client or testers verify it works |
| `main` | Live/production — stable, tested code only |

---

## Full Flow

```
[dev] → push → merge to uat → test → merge to main → sync back down
  ↑                                                         ↓
  └──────────────── pull from main ────────────────────────┘
```

---

## Step 1 — Start: Make sure dev is up to date

```bash
git checkout dev
git pull origin dev
```

---

## Step 2 — Do your work, then commit

```bash
git add .
git commit -m "your message here"
git push origin dev
```

---

## Step 3 — Merge dev → uat

```bash
git checkout uat
git pull origin dev      # bring dev changes into uat
git push origin uat
```

---

## Step 4 — Test on UAT

- Deploy `uat` to your test environment
- Let testers / client verify
- If bugs found → go back to **Step 2**, fix on `dev`, repeat

---

## Step 5 — Merge uat → main

```bash
git checkout main
git pull origin uat      # bring uat changes into main
git push origin main
```

---

## Step 6 — Keep dev and uat in sync with main

> ⚠️ This step is often forgotten. Always sync after merging to `main`.

```bash
# Update dev
git checkout dev
git pull origin main
git push origin dev

# Update uat
git checkout uat
git pull origin main
git push origin uat
```

---

## Quick Reference

| Step | Command | What it does |
|------|---------|--------------|
| 1 | `git checkout dev` | Switch to dev |
| 1 | `git pull origin dev` | Get latest dev from remote |
| 2 | `git add .` | Stage all changes |
| 2 | `git commit -m "msg"` | Commit your work |
| 2 | `git push origin dev` | Push dev to remote |
| 3 | `git checkout uat` | Switch to uat |
| 3 | `git pull origin dev` | Merge dev into uat |
| 3 | `git push origin uat` | Push uat to remote |
| 5 | `git checkout main` | Switch to main |
| 5 | `git pull origin uat` | Merge uat into main |
| 5 | `git push origin main` | Push main to remote |
| 6 | `git pull origin main` | Sync branches back with main |

---

# Evaluation Engine Workflows

## Peer Evaluation "Unable to Observe" Exemption & Dynamic Normalization

```mermaid
graph TD
    A[Faculty Member Logs In] --> B{Evaluate Peer Professor?}
    B -->|Has Sufficient Basis| C[Complete 1-5 Questionnaire]
    C --> D[Standard Submission Queue]
    D --> E[Standard Category Weights: Student 40%, Dean 20%, PH 20%, Peer 15%, Self 5%]
    
    B -->|Schedule Conflict / No Interaction| F[Click 'No Basis to Observe']
    F --> G[Select Institutional Reason & Remarks]
    G --> H[Create EvaluationExemption Record]
    H --> I[Mark Evaluator Task as Fulfilled 100%]
    H --> J{Does Evaluatee Have Remaining Valid Peer Reviews?}
    J -->|Yes N >= 1| E
    J -->|No N = 0| K[Trigger Dynamic Weight Normalization]
    K --> L[Calculate Active Scale M_active = Sum of Non-Peer Categories]
    L --> M[Calculate Scale Factor = Total_Scale / M_active]
    M --> N[Scale Observable Categories Proportionally to preserve 100% / 200.0 pts]
    N --> O[Generate Transparent Report with 'Exempted / Normalized' Badge]
```

### Business Rules & Edge Cases:
1. **Audit Requirement:** An evaluator cannot anonymously or silently skip an evaluation. Every exemption requires selecting a recognized accreditation reason (`schedule_conflict`, `different_specialization`, `new_faculty`, or `other` with mandatory notes).
2. **Evaluator Duty Fulfillment:** Once exempted, the evaluation is marked as resolved. The evaluator's completion status advances toward 100%, and automated deadline reminders are suppressed.
3. **Non-Penalization Guarantee:** An evaluatee whose peers skipped with no basis is never penalized. The active observable evaluation categories (Students, Supervisors, Self) expand proportionally to fill 100% of the composite score.
4. **Accreditation Transparency:** The faculty individual report card explicitly notes when dynamic normalization was triggered, maintaining compliance with CHED and NBC 461 standards.

---

## Non-Teaching Staff Performance Appraisal Workflow

```mermaid
graph TD
    A[HR / Administrative Leaders in Reports Hub] --> B[Toggle Track: 'Non-Teaching Staff']
    B --> C[Filters Auto-Populate: Administrative Units & Staff Roster]
    C --> D[Select Staff Member & Semester]
    D --> E[Aggregate 360 Appraisal Submissions]
    
    E --> F[Dept Head Evaluation: 50% max]
    E --> G[Peer Staff Evaluation: 30% max]
    E --> H[Self-Appraisal: 20% max]
    
    G --> I{Any Peer Submissions?}
    I -->|No / Solitary Unit| J[Trigger Dynamic Normalization: 100 / 70 Scale]
    J --> K[Dept Head: 71.43 pts, Self: 28.57 pts, Peer: Exempted Badge]
    I -->|Yes| L[Standard Composite Calculation: Head + Peer + Self]
    
    K --> M[Compute 100% Overall Composite & GRC Legend Rating]
    L --> M
    
    M --> N[Page 1: Summary Scorecard & Official Signatures]
    M --> O[Page 2: Qualitative Feedback & HR Administrative Action Checklist]
    O --> P[HR Action: Regularization, Probation Extension, Salary Increment, Reassignment, Separation]
```

### Staff Appraisal Scoring Breakdown:
- **Department Head (50.0 pts / 50%):** Evaluates Job Knowledge & Quality of Work (12.5 pts), Customer Service & Communication (12.5 pts), Initiative & Time Management (12.5 pts), Attendance & Professional Ethics (12.5 pts).
- **Peer Staff (30.0 pts / 30%):** Evaluates Collegiality, Professional Competence, and Ethical Conduct.
- **Self-Appraisal (20.0 pts / 20%):** Evaluates Self-Rating and Commitment to Departmental Objectives.
- **Rating Legend (100% Base):** Excellent (>=95%), Very Satisfactory (85-94.99%), Satisfactory (75-84.99%), Need Improvement (65-74.99%), Poor (<65%).