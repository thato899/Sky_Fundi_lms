# core/Licensing

**Purpose**: enterprise licensing — the entitlement contract governing what a licensee (a future Organization, or the platform itself) is allowed: tier, user/storage limits, enabled modules, AI provider, support level, and validity window. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/License` — `licensee_type`/`licensee_id` are a nullable polymorphic pair; a null licensee means a platform-wide license. This keeps Licensing usable today and forward-compatible with a future Organization/tenant model (see [Multi-Tenancy](../../docs/architecture/multi-tenancy.md)) without a hard dependency on a table that doesn't exist yet.
- `Domain/Enums/{LicenseTier,LicenseStatus}` — Trial, Starter, Professional, Enterprise, Government, Custom tiers; Pending Activation, Active, Suspended, Expired, Cancelled statuses.
- `Domain/Services/LicenseKeyGenerator` — pure domain logic (no persistence) generating a Crockford-base32 license key.
- `Application/LicenseService` — issue/activate/suspend/cancel/renew, entitlement checks (`isValid()`, `License::allowsModule()`), and `expireOverdueLicenses()` for the scheduled sweep (see `core/Support/Console` and [Scheduler](../../docs/deployment/README.md)).
- Every transition fires an event implementing `Core\Support\Contracts\Auditable`, automatically recorded to the audit trail by `Core\AuditLogs\Listeners\AuditableEventSubscriber` — no manual `AuditLogService::record()` call needed in this service.

**Allowed dependencies**: `Core\AuditLogs` (via the Auditable event mechanism), `Core\Users` (created_by/updated_by). Never a module.

**Routes**: `GET/POST /api/v1/licenses`, `GET /api/v1/licenses/{license}`, `POST /api/v1/licenses/{license}/{activate,suspend,cancel,renew}` — all gated by `core.licenses.manage`.

**No payment gateway integration** — out of scope per the brief. `metadata` is reserved for future gateway wiring.
