# Module Development Guide

This guide walks through building a new module. Platform Core and the Enterprise Infrastructure Layer are implemented — see [`/core`](../../core/README.md) — so "Core exists" is no longer a future condition; this is the live workflow.

## 1. Propose the Module

Open a "New module proposal" issue (`.github/ISSUE_TEMPLATE/module_proposal.md`) describing purpose, tenant types, Core dependencies, and roadmap placement. Get it agreed before scaffolding code.

## 2. Scaffold the Folder Structure

Follow the anatomy defined in [`../architecture/module-system.md`](../architecture/module-system.md#module-anatomy):

```
modules/<ModuleName>/
├── README.md
├── module.json
├── Domain/
├── Application/
├── Http/
├── Console/
├── Infrastructure/
├── database/{migrations,seeders}
├── routes/api.php
├── config/<module>.php
├── resources/{views,lang}
└── tests/{Unit,Feature}
```

See [`modules/Academics`](../../modules/Academics/README.md) for a real example of this shape.

## 3. Write the Manifest

Fill in `module.json` per the schema in [`../architecture/module-system.md`](../architecture/module-system.md#module-manifest-modulejson). Be explicit and conservative about `coreDependencies` and `moduleDependencies`.

## 4. Domain First

Start in `Domain/`. Define entities and value objects that express the module's business rules without any Laravel dependency. Define repository interfaces here.

## 5. Application Layer

Write services/use-cases in `Application/` that orchestrate the domain. Emit domain events for anything another module might care about.

## 6. Infrastructure and Interface

Implement Eloquent models, migrations, and repository implementations in `Infrastructure/`. Implement controllers, Form Requests, and API Resources in `Http/`, following [API conventions](../api/conventions.md).

## 7. Permissions

Register the module's permissions through Core RBAC (see [Security → RBAC](../security/rbac.md)) — concretely, call `Core\RBAC\Application\RoleService::registerPermission()` for each permission from a module seeder (see `modules/Academics/database/seeders/AcademicsPermissionSeeder.php` for the pattern). Permission keys are namespaced by module: `<module>.<resource>.<action>` (e.g. `academics.subjects.manage`).

## 8. Tests

Unit-test Domain and Application layers with Core faked/mocked. Feature-test Http endpoints against a real (test) database. See [Testing Strategy](../development/testing-strategy.md).

## 9. Documentation

Write the module's own `README.md` (purpose, responsibilities, allowed dependencies, future usage) — this is not optional, per repository convention.

## 10. Review

Open a PR using the standard template. A CODEOWNER must confirm the module respects isolation rules (no direct cross-module class imports, no shared tables, no direct AI provider calls) before merge.

## Anti-Patterns to Avoid

- Reaching into another module's Eloquent models or services directly.
- Putting business logic in a controller or an Eloquent model.
- Calling an AI provider SDK directly instead of going through the AI Gateway.
- Assuming a single-database, single-tenant context (see [Multi-Tenancy](../architecture/multi-tenancy.md)).
- Hardcoding a specific tenant type's rules into what should be generic module logic.
