# UI Conventions

## Current State

The organization-scoped [Learner management interface](learner-management.md) is available to authorized members at `/learners`. The [organization administrator dashboard](organization-admin-dashboard.md) links to it when the active membership has `learners.view`.

The frontend is server-rendered **Blade**. Thin web controllers reuse the same application services, policies, validation, organization context, and domain workflows as the versioned REST API (see [`../api/conventions.md`](../api/conventions.md)); views contain presentation logic only.

## Future Frontends

React (web) and Flutter/Android (mobile) are possible future clients of the same API. The existing Blade interface does not change the API contract.

## Principles for Blade Views

- Views render presentation only; business workflows remain in module application services and organization-scoped option queries remain in controllers.
- Shared layout/components live under `resources/views` at the appropriate scope (platform-wide vs module-specific) — see [`resources/README.md`](../../resources/README.md).
- Module-provided UI (if a module ships Blade views) lives inside that module's own `resources/views`, per [Module Anatomy](../architecture/module-system.md#module-anatomy), not in the shared `resources/` tree, keeping module removal clean.

## Branding

Tenant-specific branding (logo, colors) is a `core/Branding` concern, applied to shared layouts, not hardcoded per view.

## Accessibility and Localization

- `resources/lang` holds translations; no hardcoded user-facing strings in views or API error messages (see [Error Handling](../api/error-handling.md)).
- Accessibility (WCAG-aware markup) is expected of shared layout/components as they're built; detailed standards to be documented alongside first real UI implementation.
