# Local development runbook

After signing in at `/login` and selecting an active organization, authorized members can open `/academics` directly or through the Academic management dashboard card. Verify protected pages with authenticated automated tests.

## Prerequisites

Use Docker Engine, Docker Compose v2, and Git. This repository was executable-verified with Docker Engine running directly inside WSL Ubuntu, not Docker Desktop. Allocate at least 4 GB RAM to Docker for the PHP and MySQL containers. Ollama is optional; no Laravel service requires it to boot.

## Clean Docker startup

From the repository root, run the following commands in order:

```bash
docker compose up --build init
docker compose up -d
docker compose exec app php artisan migrate --seed
```

`init` is a one-time Compose service. It waits for MySQL, creates `.env` only when it does not exist, installs the dependencies recorded in `composer.lock`, creates runtime directories, generates `APP_KEY` only if missing, and writes a completion marker. The `app`, `queue`, and `scheduler` services depend on its successful completion; they do not compete to initialise the bind-mounted source tree. `.env` is created locally, never overwritten, and remains ignored by Git.

The public application entry page is available at `http://localhost:8000`, web login at `http://localhost:8000/login`, and the public liveness endpoint at `GET /up`. Mailpit is available at `http://localhost:8025`. MySQL is published on host port 3307 and remains available as `mysql:3306` to Compose services.

After web login, a Super Admin is sent to `/super-admin`. A user with one active membership in an active organization and `organization.dashboard.view` is sent to `/dashboard`; multiple active memberships require trusted organization selection at `/access`. Accounts without usable access receive a safe authenticated explanation. The organization dashboard is read-only; learner, staff, and academic web management pages and learner, guardian, and teacher portals are not implemented. Logout is a CSRF-protected `POST /logout` action that invalidates the session.

## Verification

After the application is started, use:

```bash
docker compose ps
docker compose logs init
docker compose logs app
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
```

The application container health check requests `/up` internally. Detailed health information must be accessed through the existing authorised application facilities; do not expose service configuration or secrets through the public liveness endpoint.

## Queue and scheduler

Compose runs the queue worker as `php artisan queue:work --sleep=3 --tries=3` and the scheduler as `php artisan schedule:work`. Inspect them with:

```bash
docker compose logs queue
docker compose logs scheduler
docker compose ps queue scheduler
```

## Development Super Admin

Before the first `docker compose exec app php artisan migrate --seed`, set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in your local `.env`. The supplied `SuperAdminUserSeeder` reads these values, creates the account idempotently, and skips creation when either is absent. Do not commit credentials or leave real production credentials in `.env`.

Log in at `http://localhost:8000/login`. Organization users must be provisioned through the existing authenticated administration/API workflows; there is no public registration or school-signup flow.

## Optional Ollama with direct-WSL Docker Engine

Do not assume `host.docker.internal` reaches Windows when Docker Engine runs directly inside WSL. In the verified environment it resolved to the WSL Docker gateway, while the Windows host address was the nameserver in `/etc/resolv.conf`. Ollama was not listening on either address during verification, so no working Windows-host URL is claimed here.

Verify each hop before setting `AI_OLLAMA_BASE_URL`:

```bash
curl -sS http://localhost:11434/api/tags
awk '/nameserver/ { print $2; exit }' /etc/resolv.conf
docker compose exec app php -r "echo file_get_contents('http://host.docker.internal:11434/api/tags');"
```

If Ollama runs on Windows, it must listen on an address reachable from WSL and Docker and the Windows firewall must permit that traffic. Use the address that succeeds in both checks; do not make it an application health dependency. Ollama being unavailable is a degraded AI condition, not a Docker boot failure.

## Native WSL/Linux

Install PHP 8.3 with `pdo_mysql`, `pdo_sqlite`, `zip`, and `intl`, plus Composer and MySQL. Copy `.env.example` to `.env`, set `DB_HOST=127.0.0.1` (or your database host), then run:

```bash
composer install --no-interaction --prefer-dist
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan test
```

## Troubleshooting

- **App exits or `vendor/autoload.php` is missing:** run `docker compose up --build init` and inspect `docker compose logs init`.
- **`composer.lock` is missing:** generate it with `composer update --no-interaction`, validate it, and review it before relying on reproducible Docker builds. `composer update --lock` only refreshes an existing lock file.
- **MySQL unavailable:** inspect `docker compose logs mysql`; the init service retries with a bounded timeout.
- **Missing `APP_KEY`:** rerun `docker compose up init`; it only generates a key when the value is blank.
- **Migration failure:** check credentials in `.env`, then run `docker compose exec app php artisan migrate:status`.
- **Storage permissions:** rerun init; it repairs `storage/` and `bootstrap/cache/` permissions.
- **Route/config cache problems:** run `docker compose exec app php artisan optimize:clear`.
- **Queue restarts or scheduler is absent:** inspect their logs and confirm init completed successfully.
- **Mailpit unreachable:** verify `docker compose ps mailpit` and use `http://localhost:8025`.
- **Docker cannot reach Windows Ollama:** identify the Windows address from WSL, confirm Ollama is listening beyond Windows loopback, and verify the Windows firewall before changing the application URL.
- **Port conflict:** change the host side of the `8000`, `3307`, or `8025` mappings in `compose.yaml`.
- **Reset development data:** `docker compose down -v` removes the MySQL and storage volumes. This is destructive.
