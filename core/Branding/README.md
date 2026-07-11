# core/Branding

**Purpose**: platform branding — logo, favicon, colours, platform/company name, support email, login background — dynamically loaded, defaulting to Sky Fundi. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Application/BrandingService` — reads/writes branding as a named group (`branding`) of `Core\Settings` rows rather than its own table, so it's fully database-driven for free. Falls back to `config/branding.php` (Sky Fundi defaults) for any field not yet overridden.
- `resetToDefaults()` restores the seeded Sky Fundi defaults.
- Fires `Events\BrandingChanged` on update, in addition to the audit log entry `SettingsService::set()` already writes.

**Allowed dependencies**: `Core\Settings`, `Core\AuditLogs`. Never a module.

**Routes**: `GET /api/v1/branding` (public — a login screen needs branding before authenticating), `PUT /api/v1/branding` (permission `core.branding.manage`).
