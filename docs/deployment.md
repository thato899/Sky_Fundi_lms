# Deployment

Sky Fundi ships one development Compose stack and a hardened production overlay. Application, queue, and scheduler processes use the same immutable PHP image. Production traffic terminates at Caddy, which provisions TLS certificates and forwards PHP requests to PHP-FPM. MySQL and uploaded files use named persistent volumes.

## Local Docker

Requirements: Git, Docker Engine, and Docker Compose v2. No host PHP, Composer, Node, or MySQL installation is required. The repository currently has no Node package manifest or frontend build pipeline.

```bash
git clone <repository-url> Sky_Fundi_lms
cd Sky_Fundi_lms
cp .env.example .env
make up
```

`make up` builds the development image, installs locked Composer dependencies, waits for MySQL, generates `APP_KEY` when missing, runs migrations and core seeders, creates the storage link, validates runtime configuration, and starts the application, queue worker, scheduler, MySQL, and Mailpit.

- Application: `http://localhost:8000`
- Readiness: `http://localhost:8000/ready`
- Liveness: `http://localhost:8000/up`
- Mailpit: `http://localhost:8025`

Set `DEMO_MODE=true` and a unique `HACKATHON_DEMO_PASSWORD` of at least 12 characters before the first `make up` to include the repeatable Demo School dataset. Existing installations can run `make demo-reset`, which is destructive and must never be used in production.

## Environment

Use `.env.example` only for local development and `.env.production.example` as the production contract. Never commit `.env`, `.env.production`, database passwords, mail credentials, AI keys, or the MySQL root-password file.

Production requires:

- `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, and a generated `APP_KEY`.
- A private database password and root-password file with mode `0600`.
- Database-backed or supported cache, session, and queue drivers.
- A real SMTP provider and verified sender address.
- Writable durable storage and a working public storage link.
- Explicit AI-provider enablement. Disabled AI providers require no credentials; enabled hosted providers require their own API key.

Validate without deploying:

```bash
ENV_FILE=.env.production ./scripts/deploy.sh --validate
make deployment-validate
```

## Docker production

On an Ubuntu VPS install Docker Engine, the Compose plugin, Git, and a firewall permitting SSH, HTTP, HTTPS, and HTTPS/UDP only. Point the application domain’s A/AAAA records to the server, copy `.env.production.example` to `.env.production`, fill every placeholder, and create the root password file.

```bash
mkdir -p secrets
openssl rand -base64 48 > secrets/mysql_root_password
chmod 600 secrets/mysql_root_password .env.production
ENV_FILE=.env.production ./scripts/deploy.sh
```

The deployment script validates configuration, builds immutable application and web images, waits for MySQL, enables maintenance mode, applies forward migrations, creates optimized Laravel caches, restarts queue and scheduler processes, checks readiness, and then restores service. Caddy obtains and renews certificates using `APP_DOMAIN` and `ACME_EMAIL`.

## Native Ubuntu deployment

If Docker cannot be used, provision PHP 8.3 with required extensions, Composer 2, MySQL 8, a web server with TLS, and a process supervisor. Deploy locked dependencies with:

```bash
composer install --no-dev --classmap-authoritative --no-interaction
php artisan migrate --force
php artisan storage:link --relative --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan platform:validate-environment
```

Run `php artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600` under systemd or Supervisor and restart it after each release. Run `php artisan schedule:run` once per minute from cron, or supervise `php artisan schedule:work`. The web-server user must own `storage/` and `bootstrap/cache/`.

## Native cPanel shared hosting

For a cPanel account without Docker or Supervisor, use PHP 8.3, MariaDB,
database-backed runtime state, and bounded cron workers. The complete directory
layout, LiteSpeed document-root setup, deployment/update commands, cron entries,
backup procedure, and rollback checklist are in
[cPanel deployment](cpanel-deployment.md).

## Operations

- Health: `/up` is dependency-free liveness. `/health` and `/ready` return secret-safe database, cache, queue, storage, mail, and AI-configuration status. Detailed health remains authenticated.
- Backups: configure `BACKUP_DESTINATION`, run `scripts/backup-database.sh`, encrypt and copy backups off-host, and regularly validate restoration with `scripts/restore-database-validation.sh`.
- Storage: back up the production storage volume alongside MySQL. For S3, configure the documented AWS variables and validate permissions before changing `FILESYSTEM_DISK`.
- Queues: monitor `failed_jobs`, queue depth, worker restarts, memory, and job runtime. Horizon is not installed; the deployment uses standard Laravel workers.
- Scheduler: keep exactly one scheduler process per deployment and monitor scheduled-command logs.
- Logs: application logs use Laravel channels; Docker JSON logs rotate at five 10 MB files per service in the bundled production stack.
- SSL/domain: DNS must resolve before Caddy can issue certificates. Keep ports 80 and 443 reachable and restrict MySQL to the Compose network.

## CI and release checks

The CI workflow validates Composer metadata, migration forward/rollback behaviour, PHPUnit, Pint, and PHPStan inside Docker. Before a manual release run:

```bash
git diff --check
docker compose config
make verify
make deployment-validate
```
