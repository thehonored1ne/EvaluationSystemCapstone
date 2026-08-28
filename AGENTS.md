# Agent Operating Rules & Source of Truth

- **Single Source of Truth:** The agent must treat [`docs/eval-system-vault/`](file:///c:/Users/USER/Herd/evaluationsystem/docs/eval-system-vault) as the project's single source of truth.
- **Pre-Task Check:** Inspect `00 - Home/Dashboard.md` and related vault notes before executing tasks.
- **Context Maintenance:** Write all architecture decisions, schema updates, RBAC changes, and roadmap progress directly into `docs/eval-system-vault/`.

Modular rule definitions are located in [`.agents/rules/`](file:///c:/Users/USER/Herd/evaluationsystem/.agents/rules):
- [`vault.md`](file:///c:/Users/USER/Herd/evaluationsystem/.agents/rules/vault.md) — Obsidian Vault source of truth rules
- [`laravel-livewire.md`](file:///c:/Users/USER/Herd/evaluationsystem/.agents/rules/laravel-livewire.md) — Backend & frontend engineering standards
- [`qa-security.md`](file:///c:/Users/USER/Herd/evaluationsystem/.agents/rules/qa-security.md) — Pest testing, Pint linting, and security gates
- [`workflow.md`](file:///c:/Users/USER/Herd/evaluationsystem/.agents/rules/workflow.md) — Direct communication, loop prevention, and `/abort` protocol
