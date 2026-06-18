---
name: regression-testing
description: Evaluates whether code modifications, bug fixes, or framework updates have broken existing application functionalities.
---
# Regression Testing Skill

When analyzing code changes for regression risks, follow these steps:

## Review checklist

1. **Impact Analysis**
   - Identify which existing modules share dependencies with the modified code.
   - Trace data flow changes: Check if alterations to Laravel database schemas or Eloquent models affect Livewire components or Python payload structures.
2. **State & UI Regression (Livewire)**
   - Check if modifications to component lifecycle hooks (e.g., `mount()`, `updated()`) break existing frontend interactions or reactive states.
   - Verify that DOM-diffing updates do not break existing third-party JavaScript or custom styles.
3. **AI Pipeline Integrity (Python)**
   - Ensure modifications to data tokenization, feature extraction, or text parsing do not alter the input format expected by the Python AI models.
   - Verify that updates to inference thresholds or classification rules preserve the accuracy of legacy evaluation metrics.
4. **Test Suite Selection**
   - Identify critical paths (e.g., login, form submission, evaluation processing) that require high-priority automated testing.
   - Recommend specific Feature, Unit, or Integration tests that must be executed based on the affected files.

## How to provide feedback

- Detail the specific upstream or downstream feature at risk of breaking.
- Reference the precise files, data properties, or API endpoints where the conflict occurs.
- Suggest exact test assertions (e.g., `$this->assertDatabaseHas(...)` or Python unit test checks) to validate the fix.
