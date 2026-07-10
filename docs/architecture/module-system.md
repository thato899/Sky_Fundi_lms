# Module System

## Principle

> No educational feature exists inside the platform Core. Everything educational is a module.

A module is a self-contained unit of functionality that can be **installed, enabled, disabled, updated, and removed without affecting other modules or Core**.

## Example Modules (future)

Academics, Schools, Tutoring, Attendance, Homework, Assessments, AI, Library, Sports, Transport, Finance, Messaging, Newsletters, Reports, Hostel, Clinic, Visitors, Inventory.

None of these exist yet in this repository — this document defines the contract they must follow when built.

## Module Anatomy

Each module lives under `/modules/<ModuleName>` and follows the same internal shape (mirroring the [Clean Architecture layers](clean-architecture.md)):

```
modules/Academics/
├── README.md                  # purpose, responsibilities, allowed dependencies
├── module.json                # module manifest (see below)
├── src/
│   ├── Domain/                # entities, value objects, repository interfaces
│   ├── Application/            # services / use cases, DTOs, events
│   ├── Http/                   # controllers, form requests, API resources
│   ├── Console/                 # artisan commands owned by this module
│   └── Infrastructure/          # Eloquent models, repository implementations
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
├── config/
│   └── academics.php
├── resources/
│   ├── views/                   # Blade views, if the module ships UI
│   └── lang/
└── tests/
    ├── Unit/
    └── Feature/
```

## Module Manifest (`module.json`)

Every module declares a manifest describing its identity and declared dependencies. This is documentation-first; the concrete loader/registry implementation will be built when Core is implemented, but the manifest contract is fixed now so modules built by different contributors stay compatible:

```json
{
  "name": "academics",
  "displayName": "Academics",
  "version": "0.1.0",
  "description": "Curriculum, subjects, classes, and grade structures.",
  "tenantTypes": ["school", "college", "training-centre"],
  "coreDependencies": ["auth", "rbac", "users", "notifications"],
  "moduleDependencies": [],
  "provides": {
    "events": ["academics.subject.created"],
    "permissions": ["academics.subjects.view", "academics.subjects.manage"]
  }
}
```

Key fields:
- `tenantTypes` — which tenant types this module is relevant for. A module can decline to be enabled for a tenant type it doesn't support.
- `coreDependencies` — Core services this module relies on. Must only reference Core, never other modules.
- `moduleDependencies` — **discouraged**. See "Cross-Module Communication" below. If listed, it must be a soft/optional dependency, not a hard compile-time one.
- `provides.events` / `provides.permissions` — the module's public contract exposed to the rest of the platform.

## Module Lifecycle

| State | Meaning |
|---|---|
| **Installed** | Code present, migrations available, not yet active for any tenant. |
| **Enabled** | Active for one or more tenants; routes, permissions, and scheduled jobs register. |
| **Disabled** | Code present but inactive; routes/jobs do not register; data is retained. |
| **Updated** | New version installed; module-owned migrations run; manifest version bumped. |
| **Removed** | Code and (optionally, with explicit confirmation) data removed. |

A module must be able to move through Enabled → Disabled → Enabled without data loss or corruption, and Disabled → Removed only via an explicit, audited action (see [Security → Audit Logs](../security/README.md)).

## Cross-Module Communication

Modules **must not** import another module's classes directly. Allowed communication paths, in order of preference:

1. **Domain events** — a module emits an event (e.g. `academics.subject.created`); other modules subscribe if interested. This is the default and preferred mechanism.
2. **Published service interfaces via Core** — if two modules genuinely need a synchronous contract (e.g. Attendance needing to know a learner's enrolled Class from Academics), the *interface* is defined and registered through Core's service container, and the consuming module depends on the interface, never the concrete module class. The producing module binds the implementation.
3. **Shared Core concepts** — anything both modules need (Users, Organisations/Tenants, Notifications) should probably be a Core concept, not duplicated logic in each module.

What is never allowed: one module's controller, service, or Eloquent model importing another module's internal classes, or two modules writing to the same database table.

## Module Isolation Rules

- Each module owns its own database tables, prefixed by module name (see [Database Conventions](../database/conventions.md)).
- Each module owns its own permissions, registered through Core RBAC.
- Each module can be tested in isolation with Core mocked/faked.
- Disabling a module must not throw errors elsewhere in the platform — dependents on its events must degrade gracefully (e.g. skip a scheduled report section) rather than fail hard.

## Where This Is Enforced

The concrete module loader/registry (a Core concern) will live under `core/` once Core is implemented, and will be documented in `core/README.md` at that time. This document defines the contract now so early module scaffolding stays consistent.
