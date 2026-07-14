# Local runbook

## Docker

Run `docker compose up -d --build`; then `docker compose exec app composer install`, copy `.env.example` to `.env`, `docker compose exec app php artisan key:generate`, `docker compose exec app php artisan migrate --seed`, and `docker compose exec app php artisan test`. Open `http://localhost:8000`; inspect local mail at `http://localhost:8025`.

Use `DB_HOST=mysql`, `DB_DATABASE=skyfundi`, `DB_USERNAME=skyfundi`, and `DB_PASSWORD=skyfundi`. Run `docker compose exec app php artisan queue:work` and `docker compose exec app php artisan schedule:work`. Reset development data with `docker compose down -v`.

## Native WSL/Linux

Install PHP 8.3 with `pdo_mysql`, `pdo_sqlite`, `zip`, and `intl`, Composer, MySQL, and optional Node. Copy `.env.example` to `.env`, configure the database, then run `composer install`, `php artisan key:generate`, `php artisan migrate --seed`, `php artisan storage:link`, and `php artisan test`.

## Ollama

For Docker use `AI_OLLAMA_BASE_URL=http://host.docker.internal:11434`; on Linux Docker add a host-gateway mapping or use the host IP. Set `AI_OLLAMA_MODEL=qwen3:8b`. Ollama is optional: an unavailable provider should be a degraded health result, not a boot failure.

## Troubleshooting

For database refusal, wait for MySQL health. Use `php artisan optimize:clear` for stale configuration; use `php artisan key:generate` for a missing key. Keep `SESSION_SECURE_COOKIE=false` only for local HTTP. Ensure `storage/` and `bootstrap/cache/` are writable.
