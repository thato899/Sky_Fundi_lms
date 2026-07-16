# Migration standards

Core migrations live under `core/<Service>/database/migrations`; module migrations live under `modules/<Name>/database/migrations`; Laravel cache/queue infrastructure lives under root `database/migrations`. Providers load owner migrations.

Use timestamped action names, the exact referenced key type, database foreign keys with explicit delete behavior, justified indexes/uniqueness, and a complete `down()` in reverse dependency order. Changes are additive by default. Never edit a deployed migration; add a new migration with an explicit backfill/compatibility plan.

An owner normally changes only its tables. Established module relationships may use foreign keys when the implementation contract already requires them; optional historical integrations should avoid destructive cascades. Organization-owned tables require indexed UUID `organization_id` and organization-scoped uniqueness where identity is local.

Seeders are idempotent, contain no production secrets, and are not test fixtures. Validate MySQL migrate, seed, rollback, and re-migrate with `make migrate-check`; it uses a unique temporary database and does not reset the developer database.
