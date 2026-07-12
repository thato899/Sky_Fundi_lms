# Deployment Documentation

- [`environments.md`](environments.md) — environment tiers and configuration
- [`release-process.md`](release-process.md) — how a release moves from `develop` to production

## Installation, Deployment Profiles, and Backups

These are implemented, not just planned — see each service's own README for the full detail:

- **Installer**: [`core/Installer/README.md`](../../core/Installer/README.md) — `php artisan platform:install` runs the first-run setup workflow (application name, mail/storage/AI provider, branding, localization, initial administrator, license key).
- **Deployment profiles**: [`core/Deployment/README.md`](../../core/Deployment/README.md) — stores *what* deployment strategy a licensee uses (single server, dedicated server, cloud, Docker, future Kubernetes) and its metadata. Deliberately configuration storage only — no automation.
- **Backups**: [`core/Backup/README.md`](../../core/Backup/README.md) — `php artisan platform:backup` backs up the database, storage, configuration (never `.env`), and logs. Scheduled weekly by [`core/Scheduler`](../../core/Scheduler/README.md). Restore is explicitly future work.
- **Health checks**: [`core/Health/README.md`](../../core/Health/README.md) — `GET /api/v1/health` (public, minimal) and `/health/detailed` (authenticated, full breakdown), plus an hourly scheduled check that logs the result.
