# Environments

`local`, `testing`, and production-like deployments share one Laravel application. The implemented tenancy model is a shared database with organization-owned rows—not database-per-tenant provisioning.

- Local uses `compose.yaml`, MySQL, Mailpit, database queues, and optional Redis.
- PHPUnit testing uses in-memory SQLite and synchronous queues by default; `make migrate-check` supplies MySQL compatibility coverage.
- Staging/production topology is operator-owned and not automated in this repository.

Configuration is supplied through environment values consumed by `config/`; module enablement is database state, although registered providers currently load module code/routes unconditionally. Production must provide TLS, secret management, worker/scheduler supervision, durable storage, backups, monitoring, and a real web server.
