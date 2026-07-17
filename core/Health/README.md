# core/Health

**Purpose**: system health monitoring — database, queue, storage, cache, mail, and AI provider status, plus a trivial API-is-responding check. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**No dashboard** — this is infrastructure only, consumed by uptime monitors, load balancers, and (later) an admin dashboard, per the brief.

**Responsibilities**:
- `Contracts/HealthCheckInterface` — one check, one dependency. Must never throw; a failing dependency is an `Unhealthy` result, not an exception (`Application/HealthCheckManager` also guards with a try/catch as defence in depth).
- `Domain/Enums/HealthStatus` — `Healthy | Degraded | Unhealthy`, with `worseOf()` to roll up multiple results.
- `Infrastructure/Checks/{Database,Cache,Queue,Storage,Mail,AIProvider,Api}HealthCheck` — one per dependency. `StorageHealthCheck`/`MailHealthCheck`/`AIProviderHealthCheck` reuse the existing `StorageProviderRegistry`/`MailProviderRegistry`/`AIGateway\ProviderRegistry` rather than re-implementing availability logic.
- `Application/HealthCheckManager::runReadiness()` — runs only critical internal checks listed in `health.readiness_checks`. `runAll()` retains the complete diagnostic set. Both aggregate via `overallStatus()`.
- `Console/PlatformDiagnoseCommand` — read-only, secret-safe deployment diagnostics with meaningful success/failure exit codes.

**Allowed dependencies**: `Core\Storage`, `Core\Mail`, `Core\AIGateway` (read-only, via their registries). Never a module.

**Routes**:
- `GET /up` — public dependency-free liveness.
- `GET /api/v1/health` and `GET /api/v1/ready` — public readiness with safe component names/statuses, HTTP 503 when a critical dependency is unhealthy.
- `GET /api/v1/health/detailed` (permission `core.health.view`) — full per-check breakdown with messages and metadata.

**Future usage**: adding a new check means implementing `HealthCheckInterface` and adding it to `config/health.php` — `HealthCheckManager` needs no changes.
