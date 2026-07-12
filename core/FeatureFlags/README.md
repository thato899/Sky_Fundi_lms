# core/FeatureFlags

**Purpose**: toggle platform capability at platform, organization, user, or module scope without a deploy. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/FeatureFlag` — a `key`, a global on/off default.
- `Infrastructure/Models/FeatureFlagOverride` — a scoped exception to the global default (`scope_type`: `organization | user | module`, `scope_id`: that entity's identifier as a plain string, not a foreign key — `organization` has no model yet, per [Multi-Tenancy](../../docs/architecture/multi-tenancy.md), and `module` identifies a `Core\Modules` registration by name).
- `Application/FeatureFlagService::isEnabled()` — resolution order: a matching scope override wins, otherwise the flag's global setting, otherwise `false` if the flag isn't defined at all. Cached briefly per flag key.
- Every toggle fires `Events\FeatureFlagToggled` (`Auditable`), recorded automatically by `Core\AuditLogs`.

**Allowed dependencies**: `Core\AuditLogs`. Never a module — though modules are expected to call `FeatureFlagService::isEnabled()` to gate their own capability once modules exist.

**Routes**: `GET/POST /api/v1/feature-flags`, `PUT /api/v1/feature-flags/{featureFlag}/toggle` — all gated by `core.feature-flags.manage`.

**Future usage**: A/B testing (per the brief) is a natural extension of the override mechanism — e.g. a `user`-scoped override assigned probabilistically at flag-check time — not implemented here.
