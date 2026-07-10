# Multi-Tenancy

## Principle

Sky Fundi is **tenant-aware by design**, even though a given production deployment may run **one database per school/institution** rather than a shared database with row-level tenant isolation. Tenant-awareness must never be an afterthought retrofitted later.

## Tenant Types

The platform recognizes the following tenant types today, with room for more in the future:

- School (Primary / Secondary)
- Tutoring Centre
- College
- Training Academy
- Individual Tutor (a degenerate, single-user "tenant")
- Future Organisation types

A `TenantType` is a documented enumeration, not a hardcoded assumption baked into module logic. Modules declare which tenant types they support via their manifest (see [`module-system.md`](module-system.md)).

## Isolation Model

Two isolation strategies are supported by the architecture; the choice per deployment is an operational decision, not an application-code decision:

1. **Database-per-tenant** (expected default for production schools/colleges) — each tenant has its own MySQL database. Application code must never assume it can query "all tenants" from a single connection.
2. **Shared database with tenant scoping** (useful for smaller tutoring centres, trials, or SaaS-style onboarding) — every tenant-owned table carries a `tenant_id` column, and all queries are automatically scoped to the current tenant context.

Because both must be supported, **all module and Core code must access tenant data only through tenant-aware abstractions** — never through raw, unscoped Eloquent queries — so the same code works regardless of which isolation strategy a given deployment uses.

## Tenant Context

- Every authenticated request resolves a **current tenant context** early in the request lifecycle (Core concern, part of `core/Auth` and `core/Api`).
- Background jobs and queued work must carry tenant context explicitly in their payload — a queue worker is not implicitly scoped to a tenant the way a web request is.
- Console commands that operate on tenant data must require an explicit `--tenant=` argument; there is no "current tenant" outside a request/job context.

## Cross-Tenant Data Access

By default, **no code path may read or write another tenant's data**. Any legitimate exception (platform admin support tooling, billing aggregation, analytics) must:

- Go through a dedicated, explicitly-named Core service (not ad-hoc queries),
- Be permission-gated to a platform-admin role (see [RBAC](../security/rbac.md)),
- Be audit-logged (see [Security → Audit Logs](../security/README.md)).

## Licensing and Billing Interaction

Tenant type and enabled modules together determine licensing/billing (see `core/Billing`, `core/Licensing`). This document only covers data isolation; licensing rules are documented separately as Core is implemented.

## Future Organisation Types

Adding a new tenant type (e.g. "Training Academy") should require:
- A new entry in the `TenantType` enumeration,
- Module manifests updated to opt in where relevant,
- No changes to Core's isolation mechanism itself.

If adding a new tenant type ever requires touching isolation logic in Core, that is a signal the isolation abstraction has a gap and needs to be revisited — not that the new tenant type is a special case to hardcode around.
