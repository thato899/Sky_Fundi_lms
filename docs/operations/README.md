# Operations runbook

Use `make status` for Git/Compose/migration state, `make health` for public and Laravel checks, and `docker compose logs --tail=200 <service>` for diagnosis. Standard recovery is restart/clear-cache/retry, never volume deletion or destructive migration.

```bash
make status
make health
docker compose ps
docker compose logs --tail=200 app queue scheduler mysql
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
docker compose exec app php artisan schedule:list
```

Backups are created with `docker compose exec app php artisan platform:backup` and weekly scheduling. Operators must copy artifacts to protected durable storage, define retention, monitor failures, and maintain a separately tested restore procedure; restore automation is absent. Database and uploaded storage must be backed up consistently.

For incidents, preserve logs and timestamps, verify `/up` and `/api/v1/health`, inspect database/queue/storage dependencies, and avoid exposing payloads or personal data. Rotate compromised credentials outside the repository. Production monitoring, alerting, restore orchestration, and infrastructure-as-code remain external responsibilities.
