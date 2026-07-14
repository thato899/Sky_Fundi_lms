# Local development runbook

## Prerequisites

Use Docker Desktop (Windows/macOS) or Docker Engine (Linux), Docker Compose v2, and Git. Allocate at least 4 GB RAM to Docker for the PHP and MySQL containers. Ollama is optional; no Laravel service requires it to boot.

## Clean Docker startup

From the repository root, run the following commands in order:

```bash
docker compose up --build init
docker compose up -d
docker compose exec app php artisan migrate --seed
```

`init` is a one-time Compose service. It waits for MySQL, creates `.env` only when it does not exist, installs the dependencies recorded in `composer.lock`, creates runtime directories, generates `APP_KEY` only if missing, and writes a completion marker. The `app`, `queue`, and `scheduler` services depend on its successful completion; they do not compete to initialise the bind-mounted source tree. `.env` is created locally, never overwritten, and remains ignored by Git.

The application is available at `http://localhost:8000` and its public liveness endpoint is `GET /up`. Mailpit is available at `http://localhost:8025`.

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

## Optional Ollama on Windows

Start Ollama on the Windows host and use `AI_OLLAMA_BASE_URL=http://host.docker.internal:11434` and `AI_OLLAMA_MODEL=qwen3:8b` in `.env`.

```powershell
Invoke-WebRequest http://localhost:11434/api/tags
docker compose exec app php -r "var_dump(@file_get_contents('http://host.docker.internal:11434/api/tags') !== false);"
```

Ollama being unavailable is a degraded AI condition, not a Docker boot failure.

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

- **Malformed ZIP extraction:** use the delivered archive and run `scripts/verify-archive.py archive.zip`; it rejects backslashes and unsafe paths.
- **App exits or `vendor/autoload.php` is missing:** run `docker compose up --build init` and inspect `docker compose logs init`.
- **`composer.lock` is missing:** generate it with a Composer-capable environment using `composer update --lock`, validate it, then commit it before relying on reproducible Docker builds.
- **MySQL unavailable:** inspect `docker compose logs mysql`; the init service retries with a bounded timeout.
- **Missing `APP_KEY`:** rerun `docker compose up init`; it only generates a key when the value is blank.
- **Migration failure:** check credentials in `.env`, then run `docker compose exec app php artisan migrate:status`.
- **Storage permissions:** rerun init; it repairs `storage/` and `bootstrap/cache/` permissions.
- **Route/config cache problems:** run `docker compose exec app php artisan optimize:clear`.
- **Queue restarts or scheduler is absent:** inspect their logs and confirm init completed successfully.
- **Mailpit unreachable:** verify `docker compose ps mailpit` and use `http://localhost:8025`.
- **Docker cannot reach Windows Ollama:** verify the PowerShell request above and retain `host.docker.internal:host-gateway`.
- **Port conflict:** change the host side of the `8000`, `3306`, or `8025` mappings in `compose.yaml`.
- **Reset development data:** `docker compose down -v` removes the MySQL and storage volumes. This is destructive.
