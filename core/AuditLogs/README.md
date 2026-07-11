# core/AuditLogs

**Purpose**: an immutable, searchable audit trail of security- and platform-sensitive actions. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/AuditLog` — immutable (no `updated_at`, never soft-deleted, never edited) record of an action: actor, action name, polymorphic target, before/after state, IP, user agent.
- `Application/AuditLogService::record()` — the single write path. Every other Core service (Auth, Users, RBAC, Settings, Branding, Modules) calls this directly for its own actions rather than duplicating audit-writing logic or relying on a generic event subscriber — see the "Events" section of each service's own README for the distinction between this direct audit call and the domain events those same actions also fire for other modules to subscribe to.
- `Application/AuditLogService::search()` — filterable, paginated query builder consumed by the read-only `AuditLogController`.

**Allowed dependencies**: `Core\Users` (actor relation). Never a module.

**Routes**: `GET /api/v1/audit-logs` (permission `core.logs.view`) — read-only by design.
