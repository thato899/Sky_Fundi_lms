# Module Lifecycle — Operational Detail

Complements the lifecycle states in [`../architecture/module-system.md`](../architecture/module-system.md#module-lifecycle). It distinguishes current registry behavior from the intended runtime lifecycle.

## Install

- Module code is present under `modules/<Name>` and its provider is explicitly listed in `bootstrap/providers.php`.
- The provider registers migrations and routes independently of registry state.
- `ModuleManager::install()` creates the registry record; package installation is not implemented.

## Enable (per tenant)

- Registry and organization-module assignment state is updated and audited by the applicable service.
- Permissions are registered by idempotent seeders, not dynamically from route enablement.
- Routes, migrations, and listeners are already loaded by the provider; enable does not dynamically activate them.

## Disable (per tenant)

- Registry/assignment state is updated and data is retained.
- Routes, providers, permissions, and scheduled jobs are not dynamically unloaded.
- Dependency-aware runtime deactivation remains future work.

## Update

- Code deployment and normal Laravel migration execution apply updates; the registry can record manifest version changes.
- Additive migration and rollback rules still apply.

## Remove

- Registry removal exists, but package deletion and module-table data removal are not automated.
- Any future destructive removal requires explicit confirmation, elevated authorization, audit, and a backup/rollback plan.

## Tenant-Type Gating

Some manifests declare `tenantTypes`; others use newer compact schemas. Runtime tenant-type gating is not consistently enforced and remains a normalization gap.
