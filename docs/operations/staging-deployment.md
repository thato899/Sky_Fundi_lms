# Staging deployment on one Ubuntu VPS

This runbook deploys the existing Sky Fundi Laravel application to one Ubuntu
VPS with Docker Compose. It is a single-host, maintenance-window deployment,
not a zero-downtime design.

## Audited baseline and corrected blockers

The development stack uses source bind mounts, `artisan serve`, runtime
dependency installation/key generation, fixed local MySQL credentials, a
public MySQL port, and Mailpit. It has no TLS proxy, production override,
release command, guarded restore exercise, or application rollback procedure.
Queue and scheduler processes exist, but worker resource limits, deployment
restart semantics, scheduler uniqueness, and bounded container logs were
missing. Upload and database volumes existed, but their production backup and
recovery responsibilities were not defined.

The production override replaces those assumptions with immutable PHP-FPM and
Caddy images, private application/database networking, automatic HTTPS,
database-backed queues/cache/sessions, one scheduler, named durable volumes,
health-conditioned startup, explicit release actions, and bounded JSON
container logs. No Redis or other database technology is added.

## Topology, DNS, and firewall

The minimum service set is Caddy (`web`), PHP-FPM (`app`), MySQL (`mysql`), one
database queue worker (`queue`), and exactly one Laravel scheduler
(`scheduler`). Caddy alone publishes host ports 80/tcp, 443/tcp, and 443/udp.
The PHP-FPM and MySQL ports are internal only. Mailpit, the development init
service, and optional Redis are excluded by profiles.

Create an A record (and an AAAA record only when IPv6 is correctly routed) for
the staging hostname to the VPS. Permit SSH from trusted administration
networks and public 80/tcp, 443/tcp, and 443/udp. Deny MySQL and all Docker
application ports at the host firewall.

Caddy obtains and renews a public certificate automatically when `APP_DOMAIN`
is a real DNS name, ports 80/443 reach the VPS, and `ACME_EMAIL` is valid.
Certificate state persists in the Caddy volumes. The repository does not test
public DNS or ACME issuance locally. For a local simulation use
`APP_DOMAIN=http://localhost` and loopback high ports; that explicitly disables
automatic HTTPS for the simulation only.

Forwarded headers are accepted only across the private Compose network because
PHP-FPM is not host-published. Caddy preserves Host, forwarding information,
and the configured `X-Request-ID` header. Do not publish PHP-FPM directly or
place an untrusted container on this network.

## Host and environment preparation

Install current Docker Engine with Compose v2 on a supported Ubuntu release.
Run Docker as a dedicated deployment operator without `sudo`. Clone the
repository, check out an explicitly reviewed release, and create:

```bash
cp .env.production.example .env.production
install -d -m 700 secrets backups/database backups/uploads
openssl rand -base64 48 > secrets/mysql_root_password
chmod 600 .env.production secrets/mysql_root_password
```

Fill every `CHANGE_ME` value. Generate the Laravel application key without
writing a local `.env`:

```bash
docker run --rm sky-fundi-app:RELEASE php artisan key:generate --show
```

This requires the release image to have been built first; alternatively use a
reviewed local development container and copy only the printed `base64:` value.
Generate the key once, store it in protected configuration backup/secret
storage, and never rotate or regenerate it during a normal deployment.

`.env.production` defines application identity, canonical HTTPS URL, immutable
image tag, database, cache, session, queue, SMTP, filesystem, logging, request
IDs, slow thresholds, and optional one-time administrator seed inputs. It must
set `APP_ENV=production`, `APP_DEBUG=false`, secure session cookies, and real
SMTP rather than Mailpit. `scripts/validate-production-env.sh` fails on absent,
placeholder, or unsafe mandatory values. Compose injects the file at runtime;
it is excluded from the image and Git. Operators remain responsible for host
permissions, encrypted secret storage, rotation, and access auditing.

## Validate and deploy

From the repository root:

```bash
ENV_FILE=.env.production scripts/deploy.sh --validate
ENV_FILE=.env.production scripts/deploy.sh --dry-run
ENV_FILE=.env.production scripts/deploy.sh
```

The deployment validates tools, environment, and rendered Compose; builds the
locked artifact; starts and waits for MySQL; enables maintenance mode; checks
migration status; applies forward migrations with `--force`; creates the
relative public-storage link idempotently; clears and regenerates config,
route, and view caches; runs `platform:diagnose`; signals old queue workers and
scheduler; recreates runtime services; waits for readiness; disables
maintenance mode; starts Caddy; and reports the Git revision.

`platform:diagnose` intentionally fails while migrations are pending, so
pre-migration deployment uses read-only `migrate:status`; the full diagnostic
runs immediately after the forward migration and again after startup. A failed
release prints the relevant inspection target. Failures before readiness leave
maintenance mode enabled for operator investigation; failures after the
services become ready trigger the cleanup trap.

First deployment never seeds. If initial platform administration is approved,
set temporary strong seed variables, invoke the named seeder once manually,
then remove those values. Review the seeder before doing so.

## Verification

```bash
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml ps
curl -fsS https://staging.example.com/up
curl -fsS https://staging.example.com/api/v1/ready
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  exec -T app php artisan platform:diagnose
```

`/up` is process liveness. `/api/v1/ready` checks required database, cache, and
storage dependencies and returns 503 when unhealthy. Detailed diagnostics stay
permission-protected. Verify login and a small authorized upload; confirm it
survives `docker compose ... up -d --force-recreate app web`. Private files use
the default local disk under `storage/app/private`; only deliberate public-disk
objects under `storage/app/public` are reachable through `/storage`.

For a full local production-like exercise with isolated bind-mounted state and
loopback-only HTTP, run `scripts/simulate-staging.sh`. It renders/builds the
production stack, migrates, checks liveness/readiness/diagnostics, confirms
worker/scheduler commands and upload persistence across app recreation, then
removes only its containers/network and temporary `/tmp` state. It neither
uses nor removes normal Docker volumes, sends mail, nor calls AI providers.

Queue and scheduler checks:

```bash
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  exec -T app php artisan queue:monitor default,notifications --max=100
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  exec -T app php artisan queue:failed
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  exec -T app php artisan schedule:list
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  logs --tail=100 queue scheduler
```

The worker uses `queue:work` with sleep 3s, three tries, 90s timeout, 256 MiB
worker memory limit, and hourly recycling. Compose restarts failures; deployment
signals and recreates it so stale code cannot persist. Review idempotency and
the exception before `php artisan queue:retry UUID`; use `queue:forget UUID`
only under an approved data-recovery decision.

Exactly one `scheduler` service must run. Never scale it above one or add host
cron alongside it. `schedule:work` is interrupted/recreated on deploy.
`schedule:list` and recent scheduler logs are the available checks; there is no
persistent heartbeat or distributed lock, so external monitoring remains an
operator responsibility.

Configure a real SMTP provider and perform an approved synthetic delivery.
Mail readiness is intentionally not part of application readiness.

## Backups and restore exercises

Create and integrity-check a timestamped host-side database backup:

```bash
ENV_FILE=.env.production BACKUP_DIR=/srv/sky-fundi/backups/database \
  scripts/backup-database.sh
gzip -t /srv/sky-fundi/backups/database/skyfundi_TIMESTAMP.sql.gz
```

The password stays in container environment and is not placed on the command
line. The `.partial` file is removed on failure, and success requires a
non-empty valid gzip stream. Back up the Docker `storage` volume separately
during a coordinated maintenance window so uploads and the database represent
a consistent recovery point. Also protect `.env.production`, the original
`APP_KEY`, MySQL root secret, and Caddy state according to the secret policy.

Validate restoration only into a clearly named non-production database:

```bash
ENV_FILE=.env.production scripts/restore-database-validation.sh \
  /srv/sky-fundi/backups/database/skyfundi_TIMESTAMP.sql.gz \
  skyfundi_20260717_restore_validation
```

The script refuses the configured production database and names not ending in
`_restore_validation`; it never drops a database or volume. Inspect the table
count and application compatibility, then remove the validation database only
through a separately approved manual procedure.

Keep daily database/upload backups for at least 14 days and monthly recovery
points according to organizational policy. Copy them off the VPS promptly.
Operators must supply encryption at rest/in transit, access controls, retention
enforcement, off-server/object storage, restore drills, and monitoring. A live
production restore is an incident operation requiring explicit approval,
maintenance mode, a verified matched database/upload set, and a written plan.

## Updates and rollback boundary

For an update, protect fresh backups, set a new immutable `APP_IMAGE_TAG`, and
run the deployment script. It uses forward migrations only and does not promise
zero downtime.

Application rollback means restoring the previous `.env.production` image tag
and deploying known previous application/web images, then checking `/up`,
`/api/v1/ready`, `platform:diagnose`, queue, scheduler, login, and an authorized
read. Configuration rollback restores a protected known-good environment
version, preserving its matching `APP_KEY`.

Never automatically roll back database migrations. Prefer a reviewed forward
fix because older code may not understand a migrated schema and `migrate:
rollback` can destroy data. Restore the database only when a reviewed,
backward-incompatible/data-corrupting release cannot be forward-fixed. Restore
uploads from the matching recovery point only when objects were deleted or
corrupted; application image rollback does not roll them back. Database and
uploads may need coordinated restoration to preserve references.

## Logs, failures, and safe shutdown

```bash
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  logs --tail=200 app web queue scheduler mysql
docker compose --env-file .env.production -f compose.yaml -f compose.production.yaml \
  down
```

The second command stops/removes containers and the network while preserving
all named volumes. Docker JSON logs rotate at 10 MiB with five files; Laravel
daily application logs use their configured retention in persistent storage.
Ship required audit/incident records off-host according to policy.

Common failures are DNS/ACME reachability, occupied ports, unreadable secret
files, placeholder environment values, database health, pending migrations,
storage permissions, SMTP configuration, and an unhealthy readiness component.
Use `compose config --quiet`, `compose ps`, scoped logs, `migrate:status`, and
`platform:diagnose`; never print the rendered environment or secrets.

Never casually run `docker compose down -v`, `docker volume rm`, `migrate:
fresh`, `db:wipe`, destructive SQL, `git reset --hard`, or regenerate
`APP_KEY`. None appears in the deployment or backup scripts.

## Staging acceptance checklist

- DNS resolves to the VPS and Caddy serves a trusted HTTPS certificate.
- Only SSH, HTTP, and HTTPS are reachable; MySQL/PHP-FPM are private.
- Environment validation passes with debug disabled and secure cookies.
- MySQL, storage, bootstrap-cache, and Caddy volumes are present.
- `/up`, `/api/v1/ready`, and `platform:diagnose` pass.
- Login, authorized upload persistence, and real SMTP are verified.
- One queue worker and exactly one scheduler are running current code.
- A database backup passes gzip validation and a non-production restore drill.
- Upload/configuration backups are encrypted and copied off-server.
- Previous image/configuration identifiers and the rollback decision owner are recorded.
