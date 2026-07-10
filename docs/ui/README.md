# UI Conventions

## Current State

The initial frontend is server-rendered **Blade**, consuming the same versioned REST API (see [`../api/conventions.md`](../api/conventions.md)) that any future client would use — Blade views call internal API endpoints rather than embedding business logic directly in controllers/views, so the UI layer never becomes a shortcut around the API-first principle.

## Future Frontends

React (web) and Flutter/Android (mobile) are expected future clients of the same API. Because Blade already talks to the API rather than bypassing it, introducing these should require no Core/module changes beyond ordinary API evolution.

## Principles for Blade Views

- Views render presentation only; no business logic, no direct Eloquent queries from a view or its controller beyond what's needed to call the module's own API/service layer.
- Shared layout/components live under `resources/views` at the appropriate scope (platform-wide vs module-specific) — see [`resources/README.md`](../../resources/README.md).
- Module-provided UI (if a module ships Blade views) lives inside that module's own `resources/views`, per [Module Anatomy](../architecture/module-system.md#module-anatomy), not in the shared `resources/` tree, keeping module removal clean.

## Branding

Tenant-specific branding (logo, colors) is a `core/Branding` concern, applied to shared layouts, not hardcoded per view.

## Accessibility and Localization

- `resources/lang` holds translations; no hardcoded user-facing strings in views or API error messages (see [Error Handling](../api/error-handling.md)).
- Accessibility (WCAG-aware markup) is expected of shared layout/components as they're built; detailed standards to be documented alongside first real UI implementation.
