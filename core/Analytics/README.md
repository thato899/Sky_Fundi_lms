# core/Analytics

**Purpose**: infrastructure-only platform analytics — an append-only event stream recording users, organizations, storage, requests, AI usage, modules, logins, and errors. **No dashboards** — this is the data layer a future dashboard queries, per the brief. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/AnalyticsEvent` — `metric`, an optional polymorphic subject, a numeric `value` (defaults to `1` for simple counting, but supports e.g. storage bytes or AI token counts), and JSON metadata.
- `Domain/Enums/AnalyticsMetric` — the fixed vocabulary of what's tracked, so recorded metric names never drift.
- `Application/AnalyticsRecorder::record()` — the single write path. `summarize()` provides a simple day-bucketed total for a metric over a range — the only "read" capability shipped, deliberately minimal.

**Distinct from `Core\AuditLogs`**: audit logs are a security/compliance trail of discrete sensitive actions, kept indefinitely, queried by actor/action/target (see [Security](../../docs/security/README.md)). Analytics events are a high-volume, low-detail counter stream meant to be rolled up and eventually pruned — the two are never the same write.

**Allowed dependencies**: none. Never a module — though modules are expected to call `AnalyticsRecorder::record()` for their own metrics once modules exist.

**Routes**: `GET /api/v1/analytics/metrics`, `GET /api/v1/analytics/summary?metric=&from=&to=` — both gated by `core.analytics.view`.
