---
name: code-review
description: Reviews Laravel, Livewire, and Python AI codebase changes for security, logic, and architectural best practices.
---
# Code Review Skill

When reviewing code, follow these steps:

## Review checklist

1. **Full-Stack Security**
   - Check Livewire public properties for exposed sensitive data.
   - Verify input sanitization before data passes to Python AI modules.
2. **Performance & Architecture**
   - Detect N+1 queries in Eloquent relations.
   - Ensure large datasets are not serialized inside Livewire component states.
   - Verify that long-running AI inference processes use asynchronous Laravel Queues.
3. **Robustness & Error Handling**
   - Check boundary conditions and input validation rules.
   - Confirm fallback mechanisms exist if the Python AI service fails or times out.
4. **Maintainability & Testing**
   - Ensure strict separation of concerns between frontend blade templates, Livewire components, and backend microservices.
   - Confirm automated tests cover happy paths, validation failures, and external service outages.

## How to provide feedback

- State the exact file and line number.
- Explain the technical rationale behind the change.
- Provide a clear code example for the fix.
- Use constructive, objective language. Avoid personal pronouns.
