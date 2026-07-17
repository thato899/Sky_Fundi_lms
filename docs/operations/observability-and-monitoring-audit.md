# Observability and operational monitoring audit

## Scope and baseline

This audit covers production visibility, diagnostics, health, dependencies,
queues, scheduling, incident investigation, privacy, and deployment readiness
at baseline `9fdff07`. The clean opening suite passed with **170 tests and 951
assertions**. Final verification passed with **183 tests and 1,018
assertions**; the focused observability suite passed with **12 tests and 57
assertions**.

The repository already had framework logging, daily channel retention, health
checks, database failed-job storage, scheduled maintenance, audit records,
analytics events, backups, and development Compose process definitions. It
does not contain a production monitoring platform, hosted dashboard, alert
delivery, distributed tracing, or production infrastructure-as-code. No
external monitoring dependency or custom metrics database was added.

## Findings

| Classification | Affected component and impact | Current controls and evidence | Correction, tests, remaining risk, deployment action |
|---|---|---|---|
| Confirmed operational defect | Request correlation: API errors could echo an unvalidated inbound ID, while normal responses and logs could receive unrelated IDs. | `ApiExceptionHandler` and `PlatformLogger` independently selected identifiers. | Global middleware now accepts only a bounded UUID or creates a secure UUID, stores it for the request, returns `X-Request-ID`, and adds it to logs, exception context, and API errors. Tests cover generation, acceptance, rejection, response, and error correlation. Proxies should preserve the header. |
| Confirmed observability gap | Request completion/latency: only requests returning a response were logged; thrown requests had no lifecycle event, and route extraction assumed a resolved Laravel route. | Global middleware logs method/path/status/duration and API analytics. | Returned responses produce exactly one `http.request.completed` event. Downstream exceptions produce exactly one secret-safe `http.request.failed` event with exception class and are rethrown unchanged for Laravel to report/render. Route context is best effort: matched routes retain name/template; unresolved or non-Route values use a query-free request path and null route name. Logging failures never replace the response or original exception. |
| Defence-in-depth improvement | Slow queries lacked an operational signal. | Deterministic query-count regression tests cover known paths. | Thresholded `DB::listen` warnings contain duration, connection, request/tenant context, and a SHA-256 signature of normalized parameterized SQL. Raw SQL and bindings are never logged. Database-native monitoring remains required. |
| Confirmed operational defect | Public `/api/v1/health` ran optional checks and a cache mutation, conflating liveness/readiness/diagnostics. | Laravel `/up`; seven reusable checks; detailed permission-gated endpoint. | `/up` remains dependency-free liveness. Preserved `/api/v1/health` and new `/api/v1/ready` are readiness for database, required cache, and required storage only. The cache probe is read-only. Safe component statuses return `200` or `503`. Mail, AI, and queue remain optional detailed/scheduled diagnostics. Tests cover success, failure, secrecy, public access, and absent tenant context. |
| Confirmed operational defect | Raw exception messages could appear in detailed dependency output and AI failure logs. | Detailed health is permission-gated; AI has a separate channel. | Database/cache/queue and manager failures now use generic messages. Public readiness never returns messages/meta. AI failure logs record exception class, not provider message/body. Deployment must still restrict logs and detailed-health permission. |
| Confirmed observability gap | Deployment verification was fragmented, and a successful `migrate:status` invocation did not prove that no migrations were pending. | `make health`, `migrate:status`, scheduled health command. | Read-only `platform:diagnose` uses Laravel's registered migration paths and migration repository to report repository availability and the exact pending count without loading or running migrations. It fails for pending migrations, repository/database failure, unsafe production debug/key state, or unhealthy readiness. Run it in CI/deployment with real configuration. |
| Acceptable current control | Structured logs and retention. | `PlatformLogger`; application/system 14 days, AI 30, security/authentication 90. | Trusted request attributes add request, actor, membership, and organization UUIDs. No names, emails, bodies, headers, cookies, bindings, or credentials are added. Production aggregation, access control, disk alerts, rotation, and retention policy are deployment responsibilities. |
| Acceptable current control | Exception reporting. | Unexpected API exceptions are reported once by Laravel and always return a generic `500`; expected validation/auth/authz/domain/HTTP failures are response conditions. Contract tests exclude traces, paths, SQL, and internal detail even when debug is enabled. | Request ID is additive in error/header/report context. `platform:diagnose` rejects production debug. Sentry or equivalent is deferred. |
| Acceptable current control | Authentication/security signals. | Auth, user, RBAC, organization, settings, session, and module changes create audit events/records; account-lock and tenant tests exist. | Audit/log rates can derive login, lock, denial, and privilege-change signals. Dedicated security-channel coverage is incomplete; alerting is a deployment integration. Public responses remain enumeration-safe. |
| Acceptable current control | Queue and jobs. | Only `CoreNotification` is queued on `notifications`; failed-job storage exists; Compose worker uses three tries; database queue diagnostics count pending/failed; weekly pruning defaults to 30 days. | No payload logging was added. Worker liveness, backlog age, retry storms, job duration, Redis backlog, and per-queue capacity require supervisor/driver metrics. Explicit notification timeout/backoff/failure hooks await a measured delivery contract. |
| Deployment responsibility | Scheduler. | Compose `schedule:work`; health hourly, lifecycle/cleanup daily, queue cleanup/backup weekly. | No persistent heartbeat; scheduled events lack overlap/single-server locks. Production must run exactly one scheduler or shared locks, capture exits, and alert on heartbeat absence. A heartbeat schema was not justified. |
| Acceptable current control | Audit-log usefulness. | Searchable action/category/actor/target/date records cover authentication and state transitions. | Audit records include actor email, user agent, and IP by design; protect access/retention. Audit records are not request logs or a metrics backend. |
| Deployment responsibility | Backup/restore. | Weekly `platform:backup` with per-target results; no automated restore. | Monitor exit and artifact age/size, copy to protected durable encrypted storage, and test restores independently. Neither success is externally attested today. |
| Future enhancement | Dashboards and alerting. | Analytics stores selected counters; structured logs support derived signals. | Recommendations below are not implemented alerts. Select an external backend only after privacy, sampling, cardinality, retention, and ownership review. |
| Deferred architectural integration | Sentry, Prometheus, OpenTelemetry, Horizon, Telescope, Pulse, status page. | No compatible dependencies/configuration were found; `TELESCOPE_ENABLED=false` only appears in tests. | Distributed tracing is not required for request IDs. These remain explicit future integrations. |

## Operational failure model

| Failure class | Detectable in the application | Requires infrastructure/deployment monitoring |
|---|---|---|
| Process unavailable | Nothing when dead; `/up` proves a responding process | Load-balancer/container/process probes |
| Database unavailable/slow | Readiness query, slow-query signatures, scheduled checks | Locks, saturation, storage, replicas, query plans |
| Cache unavailable | Readiness read-only cache lookup | Node health, memory, eviction, latency |
| Queue unavailable/backlogged/failed/retried | Database counts, failed-job table, command exit | Worker heartbeat, Redis/backlog age, retries, per-queue saturation |
| Mail unavailable | Configuration diagnostic and caller failures | Provider delivery/bounce/latency and synthetic delivery |
| Storage unavailable | Readiness adapter availability | Capacity, permissions, object-store durability |
| AI unavailable/latent | Gateway success/failure log; configuration diagnostic | Provider status, quota, latency; never readiness |
| Tenant-specific failure | Request/actor/membership/organization UUID context | Controlled-cardinality per-tenant aggregation |
| Authentication/authorization spike | HTTP status plus auth/audit actions | Rate/baseline alerting |
| Elevated `4xx`/`5xx`; slow requests | Completion logs by route/status/duration | SLO dashboards and external probes |
| Slow queries | Thresholded signatures | Database-native telemetry and plans |
| Disk exhaustion/log growth | Not reliably | Filesystem/inode/log-volume alerting and rotation |
| Scheduler missing | No persistent heartbeat | Process/cron heartbeat and command-exit capture |
| Pending migrations/bad production config | `platform:diagnose` reports repository availability and exact pending count | Release/configuration gate |
| Leaked/verbose diagnostics | Generic errors/readiness and redaction controls | Secret scanning, access review, debug enforcement |
| Backup/restore failure | Backup command result; no restore observer | Artifact freshness/integrity and restore exercises |

## Signals and alert recommendations

Derive request count, status/error rate, latency, login failures, locked-account
denials, authorization and tenant-context denials, readiness/DB/cache failures,
slow-query count, mail/AI/export failures, audit volume, failed jobs, queue
backlog/job duration, scheduler heartbeat, and backup outcome from logs,
framework events, provider metrics, and infrastructure. Never label metrics
with request IDs or tenant names.

Initial recommendations, to tune against production baselines:

- page on two consecutive readiness failures, sustained `5xx` above 2%,
  database connection failure, or backup failure;
- warn on p95 request latency above one second for ten minutes, sustained slow
  queries, cache failures, failed-job growth, or oldest job beyond its SLO;
- alert on abnormal login/lock/authorization denial rates rather than one
  expected denial;
- alert on scheduler heartbeat absence for twice its interval, disk above 80%,
  abnormal log growth, provider failure-rate increases, and stale backup age.

## Production actions and residual risk

Configure production environment/debug/key, TLS, secure cookies/trusted
proxies, durable cache/session/queue/shared storage, process supervision,
central log access/retention, disk monitoring, and exactly one scheduler. Probe
`/up` for liveness and `/api/v1/ready` for readiness, preserve `X-Request-ID`,
keep detailed health permission-gated, and run:

```bash
php artisan platform:diagnose
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
```

The diagnostic exits successfully only when the migration repository is
available and no configured migration files are pending. A positive pending
count or an unavailable repository/database produces a non-zero exit with
generic, secret-safe output; it never migrates, seeds, or rolls back.

Residual risks are no external monitoring/alerts, scheduler or worker
heartbeat, queue-age/job-duration telemetry, delivery telemetry, automated
restore, or externally attested backups; local log retention; and completion
log gaps for failures outside the HTTP middleware path.
