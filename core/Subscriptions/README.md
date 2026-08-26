# core/Subscriptions

**Purpose**: billing-cycle subscriptions and live usage tracking against a License's entitlements. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/Subscription` — plan, billing cycle (Monthly/Annual/Lifetime/Custom), status (Active/Grace Period/Suspended/Cancelled/Expired), optional `license_id`, and live usage counters (`current_users`, `current_storage_mb`, `ai_usage`) tracked against `max_users`/`max_storage_mb`.
- `Application/SubscriptionService` — start/renew/enter-grace-period/suspend/reactivate/cancel, `recordUsage()`, and the scheduled sweep `suspendOverdueGracePeriods()`.
- **History is not a separate table.** Every transition fires an `Auditable` event, automatically recorded by `Core\AuditLogs`; `SubscriptionService::history()` just queries the existing audit trail scoped to that subscription, rather than duplicating a second "action log" concept.
- **Payment gateway integration lives in `core/Billing`** — see [docs/adr/010-payfast-payment-gateway.md](../../docs/adr/010-payfast-payment-gateway.md). `external_reference`/`metadata` are populated by Billing once a subscription's signup payment is confirmed; Subscriptions itself has no gateway knowledge.
- `Infrastructure\Models\Plan` — the real plan catalog (`plans` table), replacing the old demo-only `config('hackathon.plans')` array. Keyed by the same string identifiers already stored in `Subscription.plan` (e.g. `"starter"`) rather than a new foreign-key column, so existing rows are unaffected. Seeded by `Database\Seeders\PlansSeeder`. `core/Billing` reads this for checkout amounts and invoice lines; it is Billing that depends on Subscriptions for `Plan`, not the reverse.

**Allowed dependencies**: `Core\Licensing` (optional `license_id`), `Core\AuditLogs`. Never a module.

**Routes**: `GET/POST /api/v1/subscriptions`, `GET /api/v1/subscriptions/{subscription}[/history]`, `PUT .../usage`, `POST .../{suspend,reactivate,cancel}` — gated by `core.billing.manage` via the platform-wide `permission:` middleware (a direct-to-user role — see [core/RBAC/README.md](../RBAC/README.md) — not an organization membership role; this API is platform/Super-Admin tooling, unaffected by `core/Billing`'s org-scoped self-service surface). `GET /api/v1/plans` requires only authentication — it is read-only, non-sensitive catalog data any signed-in user may browse.
