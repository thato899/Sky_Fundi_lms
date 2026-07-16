# Module System

## Principle

> No educational feature exists inside the platform Core. Everything educational is a module.

A module is a bounded unit with its own provider, manifest, routes, migrations, services, models, permissions, and tests. Core Module Manager records install/enable/disable state, but registered providers currently load code and routes unconditionally; dynamic activation and package removal are not implemented.

## Implemented and future modules

Implemented: Academics, Organizations, Staff, Learners, Attendance, Assessments, Reports, and Scheduling. Future candidates are tracked in the [roadmap](../roadmap.md).

## Module Anatomy

Each module lives under `/modules/<ModuleName>` and follows the same internal shape (mirroring the [Clean Architecture layers](clean-architecture.md)):

```
modules/Academics/
├── README.md                  # purpose, responsibilities, allowed dependencies
├── module.json                # module manifest (see below)
├── Domain/                    # entities, value objects, repository interfaces
├── Application/                # services / use cases, DTOs, events
├── Http/                       # controllers, form requests, API resources
├── Console/                     # artisan commands owned by this module
├── Infrastructure/              # Eloquent models, repository implementations
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

> **Note:** earlier drafts of this document nested the four code layers under an additional `src/` folder. The platform's `composer.json` PSR-4 mapping (`"Modules\\": "modules/"`, set up alongside the rest of this architecture) maps a module's namespace directly onto its folder with no `src/` layer, so the anatomy above was corrected to match — see [`modules/Academics`](../../modules/Academics/README.md) for the first module built against it.

## Module Manifest (`module.json`)

Every module declares a manifest describing its identity and declared dependencies. The loader/registry that reads this manifest is implemented — see [`core/Modules`](../../core/Modules/README.md). The schema below is what `Core\Modules\Application\ModuleManager::discover()` expects to find at `<module>/module.json`:

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
- `moduleDependencies` — established module dependencies required by the implementation. Older manifests use a combined `dependencies` field; registry discovery preserves this metadata rather than enforcing one normalized dependency schema.
- `provides.events` / `provides.permissions` — the module's public contract exposed to the rest of the platform.

## Module Lifecycle

| State | Meaning |
|---|---|
| **Installed** | Registry record says code is installed. Providers and migrations remain explicitly registered at application boot. |
| **Enabled** | Registry/organization assignment says enabled. Runtime routes are already loaded by the provider. |
| **Disabled** | Registry/organization assignment says disabled; data is retained, but provider/routes are not dynamically unloaded. |
| **Updated** | New version installed; module-owned migrations run; manifest version bumped. |
| **Removed** | Code and (optionally, with explicit confirmation) data removed. |

Full runtime enable/disable and package/data removal remain future lifecycle work; see [module lifecycle](../modules/module-lifecycle.md).

## Cross-Module Communication

New dependencies should prefer loose contracts, but implemented education modules also use explicit, validated module relationships and imports where synchronous workflows require them. These dependencies are declared in manifests/READMEs and must not be circular.

1. **Domain events** — a module emits an event (e.g. `academics.subject.created`); other modules subscribe if interested. This is the default and preferred mechanism.
2. **Published service interfaces** — use an interface when a stable abstraction is warranted; do not create ceremonial interfaces for every relationship.
3. **Shared Core concepts** — anything both modules need (Users, Organisations/Tenants, Notifications) should probably be a Core concept, not duplicated logic in each module.

What is never allowed: two modules writing the same table, an undocumented new hard dependency, a circular dependency, or bypassing the owning module's validation/business contract.

## Module Isolation Rules

- Each module owns its own database tables, prefixed by module name (see [Database Conventions](../database/conventions.md)).
- Each module owns its own permissions, registered through Core RBAC.
- Module tests live with the module and may exercise declared dependencies.
- Dynamic runtime disabling is not yet available; dependency-safe disabling remains a target lifecycle property.

## Where This Is Enforced

`Core\Modules\Application\ModuleManager` implements discovery and registry transitions. `bootstrap/providers.php` remains the runtime code-loading mechanism; see [runtime architecture](runtime.md).
