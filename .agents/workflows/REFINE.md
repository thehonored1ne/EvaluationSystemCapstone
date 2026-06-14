---
description: Agentic generate → critique → refine loop with mandatory Pest, Pint, and security gates. Tailored for a Laravel + Livewire full stack capstone application. Invoke with `/refine` followed by your task description.
---

# Workflow: /refine

---

## Before You Start — Loop Prevention Checklist

Read this before every run. These are hard constraints that override everything else:

| # | Safeguard | Trigger |
|---|-----------|---------|
| 1 | Test failure loop | Same test fails 2 consecutive refine rounds → STOP, explain, present options |
| 2 | Lint loop | Same Pint error after 2 fix attempts → @pint-ignore + flag in delivery |
| 3 | Clarification loop | Max 1 question per task → assume and proceed after that |
| 4 | Scope creep loop | Step 5 (Refine) runs max 3 times → deliver what works, list the rest |
| 5 | Hallucination loop | Verify every class/method exists before referencing it |
| 6 | Context loss loop | Re-read every file before editing it — never rely on memory |
| 7 | Over-engineering loop | Refining = simpler + safer, not more abstraction layers |
| 8 | Silent failure loop | Always paste raw terminal output — never summarise test/lint results |
| 9 | Dependency conflict loop | 2 failed install attempts → stop and escalate to user |

If the user types `/abort` at any point → stop immediately, summarise what is done and what is not, exit.

---

## Step 1 — Understand the Task

Before writing any code:

- Restate the task in your own words in 2–3 sentences.
- Identify which layers are affected: Livewire component, controller, service/action, Eloquent model, migration, policy, route, or a combination.
- List any ambiguities. If anything is blocking, ask ONE clarifying question and wait before proceeding. This is your only question — after the answer, make assumptions for anything else and state them.

---

## Step 2 — Reality Check

Before writing any code:

- List every existing class, method, or package you plan to reference.
- For each one, confirm it exists by reading the relevant file or checking `composer.json` / `package.json`.
- If something does not exist, decide now: create it as part of this task, or flag it as out of scope.
- Re-read any existing file you plan to modify. Do not rely on memory.

This step prevents hallucination and context loss before they happen.

---

## Step 3 — Generate

Write the initial implementation across all affected layers:

- **Migration** (if schema changes): reversible, with indexes on foreign keys and filtered columns.
- **Model**: `$fillable` defined, relationships declared, no business logic.
- **FormRequest** (if handling HTTP input): all validation rules here, not in the controller.
- **Policy** (if acting on a resource): one method per action.
- **Service or Action class** (if business logic is non-trivial): single responsibility.
- **Controller** (if needed): thin — delegate to service/action, return response.
- **Livewire component** (if UI): small, focused, `#[Locked]` on protected properties.
- **Blade view** (if needed): logic-free, use `{{ }}` not `{!! !!}`.

At the end of this step write a short **"Known gaps"** list.

---

## Step 4 — Critique (Code Quality)

Review your own output as a critical senior Laravel engineer:

- **Logic:** Any bugs, incorrect Eloquent usage, or broken relationships?
- **Laravel conventions:** Are controllers thin? Is validation in FormRequests? Are Policies used?
- **Livewire:** Any unnecessary `wire:model` causing extra round trips? Sensitive data in public properties?
- **Edge cases:** Empty collections, unauthenticated access, null model lookups (`findOrFail` vs `find`)?
- **Readability:** Would a junior Laravel dev understand this without explanation?
- **Complexity:** Is anything over-engineered? Could it be simpler without losing correctness?

Produce a numbered list of issues. Do not fix yet.

---

## Step 5 — Critique (Security)

Review specifically for Laravel + Livewire security issues:

- **Mass assignment:** Is `$fillable` defined on every model? Is `$request->all()` used anywhere unsafely?
- **Auth & Authz:** Are routes protected by `auth` middleware? Is `$this->authorize()` called before resource actions?
- **XSS:** Is `{!! !!}` used anywhere? If so, is the content provably safe?
- **SQL Injection:** Any raw queries with concatenated user input?
- **CSRF:** Is the CSRF middleware intact on all mutating routes?
- **Livewire properties:** Could a user manipulate a public property to access data they shouldn't?
- **File uploads:** If any, is MIME type and file size validated server-side?
- **Secrets/logging:** Any risk of tokens, passwords, or PII being logged?

Add findings to the numbered list. Mark each **[LOW]**, **[MEDIUM]**, or **[HIGH]**.

---

## Step 6 — Refine

Fix every issue from Steps 4 and 5.

**Rules:**
- Address all items in order of severity (HIGH first).
- For each fix, note which issue number it resolves.
- If a fix introduces a trade-off, call it out explicitly.
- Simplify where possible — do not add abstraction layers unless the task requires it.
- **This step may run a maximum of 3 times.** On the 3rd iteration, deliver what is working and move any remaining issues to an "Outstanding items" section in Step 9.
- **If the same issue appears in 2 consecutive refine rounds**, stop refining that issue. Explain why it is resistant to fixing and present 2 options to the user.

Track iteration count: **Refine round: __ / 3**

---

## Step 7 — Tests (Pest)

Write or update Pest tests for all affected code.

**For backend (controllers, services, models):**
- Use `actingAs()` for authenticated routes.
- Use `assertDatabaseHas()` / `assertDatabaseMissing()` to verify persistence.
- Test 403 / 404 / 422 responses explicitly — not just happy paths.
- Use `RefreshDatabase` on every test that touches the DB.
- Use model factories — never raw inline data.

**For Livewire components:**
- Use `Livewire::test(MyComponent::class)` to test component state.
- Test `->call('methodName')` and assert state changes.
- Test validation errors with `->assertHasErrors(['field'])`.
- Test that locked/protected properties cannot be tampered with.

**Run the tests:**
```bash
./vendor/bin/pest
```

**Paste the full raw terminal output here. Do not summarise it.**

**Gate rule:**
- If tests pass → proceed to Step 8.
- If tests fail → return to Step 6 (counts as a refine round).
- If the same test has failed across 2 consecutive refine rounds → STOP. Do not attempt another fix. Explain exactly why the test keeps failing and present 2 options to the user: (a) adjust the implementation, (b) adjust the test. Wait for a decision.

---

## Step 8 — Lint & Format (Laravel Pint)

Run Laravel Pint across all PHP files touched:

```bash
./vendor/bin/pint --test   # check
./vendor/bin/pint           # fix
```

**Paste the full raw terminal output of `./vendor/bin/pint --test` here.**

- Fix all issues reported.
- Do not alter formatting style beyond what Pint enforces.
- Check Blade files manually — Pint does not lint Blade. Remove any inline PHP logic from templates.
- If the same Pint error reappears after 2 fix attempts, add `// @pint-ignore` with a clear comment and flag it in the delivery notes.

**Gate rule:** `./vendor/bin/pint --test` must exit with 0 errors before proceeding.

---

## Step 9 — Final Delivery

Deliver the finished implementation with this structure:

### ✅ What was built
Plain-English summary of what the code does and which layers were touched.

### 📁 Files changed
List every file created or modified, e.g.:
- `database/migrations/xxxx_create_x_table.php`
- `app/Models/X.php`
- `app/Http/Requests/StoreXRequest.php`
- `app/Policies/XPolicy.php`
- `app/Services/XService.php`
- `app/Http/Controllers/XController.php`
- `app/Livewire/X.php`
- `resources/views/livewire/x.blade.php`
- `tests/Feature/XTest.php`

### 🧪 Test results
Paste the final Pest terminal output. Confirm 0 failures.

### 🔒 Security notes
Confirm each security item in Step 5 was reviewed. List any residual [MEDIUM] or [LOW] risks.

### ⚠️ Assumptions & risks
Anything the team should know before merging — assumptions made, out-of-scope items, recommended follow-up.

### 📋 Outstanding items (if refine limit was reached)
Any issues that could not be resolved within 3 refine rounds. Each item must include: what the issue is, why it was not resolved, and a recommended next action.

---

## Gate Rules Summary

The workflow must not reach Step 9 if any of the following are true:

- [ ] Any Pest test is failing
- [ ] `./vendor/bin/pint --test` reports errors
- [ ] A **[HIGH]** security finding is unresolved
- [ ] A clarifying question from Step 1 was never answered
- [ ] Raw terminal output for tests or lint has not been pasted