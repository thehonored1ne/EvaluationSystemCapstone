# Laravel & Livewire Standards

## Backend
- **Routing & Controllers:** Resource controllers preferred. Keep controllers thin; put business logic in Action or Service classes.
- **Eloquent:** Always define explicit `$fillable`. Prevent N+1 queries using `with()`, `load()`, or `loadMissing()`. Use relationships over raw joins.
- **Validation & Authorization:** Use dedicated `FormRequest` classes and Laravel Policies/Gates.
- **Database:** All changes require reversible migrations with indexes on foreign and query filter keys.
- **Environment:** Use `config()`, never call `env()` directly outside config files.

## Frontend (Livewire & UI)
- **Component Scope:** Single responsibility per component.
- **Reactivity:** Use `wire:model` by default; use `.live` or `.live.debounce` only when needed. Use `#[Locked]` on immutable public properties.
- **Livewire 4:** Use `@island` and `#[Computed]` for heavy sub-regions; use client actions `$js` and native directives over custom scripts.
- **Alpine.js:** Strict boundary for DOM presentation toggles (modals, dropdowns); no server data management.
- **UI Design:** Prioritize whitespace, typography, and data tables over excessive card wrappers. Maintain 44x44px minimum click targets.
