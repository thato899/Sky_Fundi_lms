# core/Subscriptions

**Purpose**: billing-cycle subscriptions and live usage tracking against a License's entitlements. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/Subscription` — plan, billing cycle (Monthly/Annual/Lifetime/Custom), status (Active/Grace Period/Suspended/Cancelled/Expired), optional `license_id`, and live usage counters (`current_users`, `current_storage_mb`, `ai_usage`) tracked against `max_users`/`max_storage_mb`.
- `Application/SubscriptionService` — start/renew/enter-grace-period/suspend/reactivate/cancel, `recordUsage()`, and the scheduled sweep `suspendOverdueGracePeriods()`.
- **History is not a separate table.** Every transition fires an `Auditable` event, automatically recorded by `Core\AuditLogs`; `SubscriptionService::history()` just queries the existing audit trail scoped to that subscription, rather than duplicating a second "action log" concept.
- **No payment gateway integration** — `external_reference`/`metadata` are reserved for that later; "invoices ready" means the schema has what a gateway integration would need to attach to, not a built invoice feature.

**Allowed dependencies**: `Core\Licensing` (optional `license_id`), `Core\AuditLogs`. Never a module.

**Routes**: `GET/POST /api/v1/subscriptions`, `GET /api/v1/subscriptions/{subscription}[/history]`, `PUT .../usage`, `POST .../{suspend,reactivate,cancel}` — all gated by `core.billing.manage` (the same permission already governing billing, per [config/permissions.php](../../config/permissions.php)).
