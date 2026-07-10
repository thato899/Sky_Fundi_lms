# Environments

## Tiers

| Environment | Purpose | Notes |
|---|---|---|
| `local` | Individual developer machines | Seeded demo data, all modules enabled for testing |
| `staging` | Pre-production validation | Mirrors production configuration, anonymized/sample tenant data only |
| `production` | Live tenant traffic | Real data, strict access control, no debug output |

## Deployment Model

Given the database-per-tenant default described in [Multi-Tenancy](../architecture/multi-tenancy.md), production deployment must account for provisioning a new tenant (new database, migrations run, initial admin user, licensed modules enabled) as a first-class, repeatable operation rather than a manual one-off. The concrete provisioning tooling is future work; this document fixes the requirement.

## Configuration

- Environment-specific values live in `.env` (never committed) per environment; see [Environment Variables](../environment-variables.md).
- Feature/module enablement is tenant-level data (see [Module Lifecycle](../modules/module-lifecycle.md)), not environment-level config — a module being "installed" is an environment concern, a module being "enabled" is a tenant concern.

## Infrastructure Assumptions

Documented at a principle level for now (concrete infra-as-code is future work):
- PHP 8.3+ runtime, MySQL, optional Redis for cache/queue/session backing.
- Queue workers run as long-lived processes (Supervisor or equivalent), separate from web request handling.
- Horizontal scaling of web/API nodes is expected; nothing in the application may rely on local filesystem state persisting between requests (use `core/Storage` abstractions instead of raw local disk for anything that must survive across nodes).
