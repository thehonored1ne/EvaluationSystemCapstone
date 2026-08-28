# Documentation Vault Rules

- **Single Source of Truth:** `docs/eval-system-vault/` is the project's single source of truth.
- **Pre-Task Check:** Always inspect `00 - Home/Dashboard.md` and relevant domain notes before executing tasks.
- **Post-Task Update:** After completing any task, the agent MUST immediately update corresponding documentation notes in `docs/eval-system-vault/` (architecture, schema, changelog, task tracking).
- **Context Maintenance:** Write all architecture decisions, schema updates, RBAC changes, and roadmap progress directly into corresponding notes in `docs/eval-system-vault/`.
- **Conventions:** Preserve YAML frontmatter and standard Obsidian `[[wikilinks]]`.
