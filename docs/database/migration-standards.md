# Migration Standards

## Ownership

Each module's migrations live inside that module: `modules/<Name>/database/migrations/`. Core's migrations live under `core/<Service>/database/migrations/` (or a platform-level `core/database/migrations` for genuinely shared tables like `users`, `tenants` — exact Core internal layout to be finalized when Core is implemented, per each Core service's own README).

A migration file must only ever touch tables owned by its own module/Core service (see [Database Conventions — Table Ownership](conventions.md#table-ownership-and-naming)).

## Naming

Standard Laravel timestamp-prefixed naming: `2026_01_01_000000_create_academics_subjects_table.php`. Descriptive, action-based names: `create_x_table`, `add_y_to_x_table`, `drop_y_from_x_table`.

## Additive by Default

Migrations shipped in a module update should be additive (new tables, new nullable columns, new indexes) wherever possible, so that Update (see [Module Lifecycle](../modules/module-lifecycle.md)) is low-risk. Destructive changes (dropping/renaming columns, changing types) must:

- Be called out explicitly in the PR description and the module's own changelog/README,
- Include a documented data-migration/backfill plan where relevant,
- Never ship silently in a routine feature PR.

## Rollback

Every migration implements a meaningful `down()` method. "Cannot be rolled back" is acceptable only for genuinely irreversible operations (e.g. data deletion) and must say so explicitly in the migration file.

## Seeders

Module seeders (`database/seeders/`) provide demo/sample data for local development only, and must be safe to run repeatedly (idempotent) without duplicating data. Seeders never run automatically in production deployments.

## Cross-Module References

A module's migration may reference a Core table (e.g. a foreign key to `users.id` or `tenants.id`) because Core is a stable, allowed dependency. A module's migration must never reference another module's table directly — if that kind of relationship seems necessary, revisit whether the two modules should instead communicate via events/service interfaces per [Module System — Cross-Module Communication](../architecture/module-system.md#cross-module-communication), or whether the concept actually belongs in Core.
