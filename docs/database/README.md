# Database Documentation

- [`conventions.md`](conventions.md) — naming, table ownership, soft deletes, indexing
- [`migration-standards.md`](migration-standards.md) — how migrations are written, owned, and sequenced across Core and modules

Academics operational tables are organization-owned. Curriculum, department, and subject codes use composite `(organization_id, code)` uniqueness. The Academics ownership upgrade backfills only when a single organization makes ownership unambiguous and otherwise fails for explicit operator remediation.
