# Database Conventions

## Engine

MySQL (InnoDB), UTF-8mb4 throughout for full Unicode/emoji support in names, messages, etc.

## Table Ownership and Naming

- Core tables are unprefixed or `core_`-prefixed for genuinely platform-level concepts: `users`, `roles`, `permissions`, `tenants`, `audit_logs`.
- Every module's tables are prefixed with the module's snake_case name: `academics_subjects`, `attendance_registers`, `attendance_entries`, `library_books`.
- This prefixing is mandatory — it makes table ownership visually unambiguous in any database client and prevents accidental naming collisions between modules built by different contributors.
- No module may create a table without its module prefix. No module may write to a table owned by another module or by Core.

## Naming

- Table names: plural, `snake_case` (`attendance_registers`).
- Column names: `snake_case`.
- Primary key: `id`, unsigned big integer, auto-increment (or ULID/UUID where a module has a documented reason — e.g. data that must be globally unique across a future multi-database sync scenario).
- Foreign keys: `<singular_referenced_table>_id` (`tenant_id`, `subject_id`).
- Boolean columns: prefixed `is_`/`has_` (`is_active`, `has_paid`).
- Timestamps: `created_at`, `updated_at` (standard Laravel); `deleted_at` for soft deletes.

## Soft Deletes

Default to soft deletes (`deleted_at`) for any table representing a real-world entity a user could reasonably need to restore or audit later (learners, staff, assessments, invoices). Hard deletes are reserved for genuinely transient/derived data, and for the explicit, audited "Remove module data" lifecycle action (see [Module Lifecycle](../modules/module-lifecycle.md)).

## Tenant Scoping

Every module table representing tenant-owned data includes a `tenant_id` column even in database-per-tenant deployments, so the same schema and query code work under both isolation strategies described in [Multi-Tenancy](../architecture/multi-tenancy.md). Global scopes enforce tenant filtering automatically at the Eloquent layer; raw/unscoped queries against tenant tables are disallowed.

## Indexing

- Foreign key columns are always indexed.
- Composite indexes are added for known common query patterns (e.g. `(tenant_id, status)`), documented in the migration's comment/PR description, not left implicit.
- Avoid indexing speculatively; add indexes driven by real query patterns as modules are built.

## Constraints

- Foreign key constraints are enforced at the database level, not only in application code, unless a specific, documented performance reason (at scale) requires otherwise.
- `NOT NULL` by default; nullable columns must have a documented reason.

## JSON Columns

Used sparingly, for genuinely schemaless or module-specific configuration (e.g. a module's per-tenant settings blob), never as a substitute for proper relational modeling of core entities.
