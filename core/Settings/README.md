# core/Settings

**Purpose**: database-driven global platform configuration — "no hardcoded configuration," per the original brief. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/Setting` — a single `key` -> `value` (JSON) row, grouped (`general`, `system`, `storage`, `security`, `ai`, `branding`, ...), with optional at-rest encryption for secret-bearing values (API keys) via `Application/SettingsService`.
- `Application/SettingsService::get()/set()/all()/setMany()` — the only read/write path; every Core service and future module reads its runtime configuration through this rather than `config()`/`env()` so it stays changeable without a redeploy. Cached, cache-invalidated on write, fires `Events\SettingsUpdated`.
- `database/seeders/SettingsSeeder.php` (repository root) seeds System Name, Timezone, Maintenance Mode, Storage default disk, Security (login attempts, lockout, password expiry, 2FA enforcement flag), and AI default/fallback provider — matching the original settings brief.

**Allowed dependencies**: `Core\AuditLogs`. Used by `Core\Branding` (branding is stored as a Settings group, see below). Never a module.

**Routes**: `GET/PUT /api/v1/settings` (permission `core.settings.manage`), optionally filtered by `?group=`.
