# Testing, Quality & Security

## Testing & Quality
- **Automated Tests:** Use Pest PHP with `RefreshDatabase` and model factories. Cover happy path, edge cases, and failure modes.
- **Verification:** Always provide raw terminal output of `./vendor/bin/pest` and `./vendor/bin/pint`.
- **Code Standards:** No dead code, no unresolved TODOs, strict typing, and PSR-12 compliance via Laravel Pint.

## Security
- **Data Protection:** Explicit `$fillable` allowlists on models. Auto-escape Blade output with `{{ }}`.
- **Auth & Access:** Protect private routes with `auth` middleware and enforce policy gates on mutations.
- **SQL & CSRF:** Use parameterized bindings only. Never disable CSRF on state-changing routes.
- **Uploads & Secrets:** Validate file types/sizes server-side. Never commit `.env` or log sensitive data.
