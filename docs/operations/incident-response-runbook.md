# Incident response runbook

## First response

Assign an incident owner, record UTC start time and affected routes/tenants,
and preserve request IDs and timestamps. Never copy bodies, tokens, cookies,
personal records, credentials, or `.env` into tickets.

```bash
docker compose ps
make health
curl -i http://localhost:8000/up
curl -i http://localhost:8000/api/v1/ready
docker compose logs --tail=200 app queue scheduler mysql
docker compose exec -T app php artisan platform:diagnose
docker compose exec -T app php artisan migrate:status
docker compose exec -T app php artisan queue:failed
docker compose exec -T app php artisan schedule:list
```

Production tooling may differ. Do not run `migrate:fresh`, delete volumes,
flush shared queues/caches, expose secrets, rotate the application key, or
restore over live data. Destructive recovery requires an approved tested plan.

## Procedures

| Incident | Symptoms and first checks | Evidence and containment | Recovery, escalation, validation |
|---|---|---|---|
| Application unavailable | `/up` fails; inspect process/container and app logs. | Capture release, restart count, resource state, last healthy time. Remove unhealthy instance from traffic. | Supervisor restart or approved rollback. Validate `/up`, readiness, safe authenticated smoke request, and error rate. |
| Readiness failing | `/up` succeeds, `/ready` is `503`; run diagnose. | Capture request ID, safe component state, dependency signals, config-change time. Stop new traffic to instance. | Restore database/cache/storage or known-good config. Validate consecutive readiness probes. |
| Elevated `5xx` | Group logs by route, exception class, release, request ID. | Preserve sanitized traces and timeline; rate-limit/disable only affected optional integration through approved controls. | Correct/roll back smallest cause; validate route, generic errors, declining rate. Escalate integrity symptoms. |
| Database outage | Readiness DB failure; inspect service and `migrate:status`. | Capture DB logs, availability, locks/capacity, migration list—never connection values. Quiesce writes if integrity uncertain. | DB operator restores service; run only reviewed forward migrations. Validate readiness and scoped reads/writes. |
| Cache outage | Readiness cache failure or cache error spike. | Capture driver/node health, eviction/capacity; do not reflexively flush. | Restore cache/approved fallback. Validate readiness, auth/session, tenant context. |
| Queue backlog/failed jobs | Pending/old jobs grow or workers stop. | Capture queue, job class, attempt, failure time/class, worker logs; never payload. Pause producers only if integrity is threatened. | Restore worker/dependency; retry only reviewed idempotent jobs with `queue:retry <uuid>`. Validate backlog age and no duplicates. |
| Slow requests/queries | Latency warnings/signatures rise. | Group by route/signature/release; capture DB load/locks and sanitized plan. | Remove saturation, roll back, or deploy measured fix. Validate p95 and query regression tests; never enable binding logs globally. |
| Mail failure | Notifications fail while readiness remains healthy. | Capture provider, message class, failure class, queue/status; never recipient content/credentials. | Restore provider/config and retry only safe jobs. Validate with approved synthetic delivery and provider telemetry. |
| AI provider failure | `ai.provider_failed` rises; fallback may activate. | Capture provider/fallback, capability, latency, exception class; never prompts/responses. | Restore provider/quota/network or fallback. Validate a non-sensitive synthetic call; readiness stays independent. |
| Suspected cross-tenant access | Foreign data or tenant anomaly. | Preserve request/audit IDs, actor/membership/organization UUIDs, route/time/response/DB evidence. Restrict affected route/account without altering evidence. | Security/privacy escalation, scoped revocation, isolation correction, notification decision, explicit foreign-tenant regressions. |
| Suspected credential exposure | Secret in response/log/repository or anomalous token use. | Restrict evidence access; identify type/scope/window without redistributing it. | Security owner rotates only affected credential, revokes sessions/tokens, removes unsafe output, validates old rejection. |
| Application-key loss | Decryption/session failures after key loss/change. | Stop dependent writes; preserve configuration history. Never generate a new key over encrypted data. | Immediately restore protected original key with security/platform owners. Validate encrypted organization AI configuration and sessions. |
| Failed deployment/pending migration | Failures start at release; diagnose/migration status fails. | Capture release, lockfile, migration list and config metadata; remove failed instance. | Approved code/config rollback or reviewed forward migration; never `migrate:fresh`. Re-run diagnose/readiness/smoke tests. |
| Log spike/disk pressure | Disk/log-growth alert. | Identify channel/event/route and free space without deleting evidence; reduce noisy traffic/integration. | Correct source, archive/rotate under policy. Validate disk/inodes and normal event rate. |
| Backup failure | Non-zero command or stale/missing/abnormal artifact. | Capture target/time/exit/destination availability/artifact metadata, not contents. Protect last good copy. | Restore destination and rerun approved backup. Validate protected copy, integrity, retention, and separate restore exercise. |

Close only after indicators recover, scoped functional and tenant-isolation
checks pass, evidence is retained, alert coverage is reviewed, and follow-up
owners are assigned.

`platform:diagnose` is read-only. It reports `Migration repository:
available` with `Pending migrations: none` when current, reports the exact
count and exits non-zero when migrations are pending, and reports only generic
unavailable/unknown states when the repository or database cannot be read. It
does not execute, seed, or roll back migrations.

Request lifecycle investigation should correlate `http.request.completed`
events for returned responses with `http.request.failed` events for thrown
requests. Failure events contain safe method, best-effort route/template or
query-free path, duration, request/actor/organization context, and exception
class only. They exclude exception messages, traces, file paths, SQL, bindings,
bodies, headers, and query values. Logging failure never replaces the
application exception, which remains Laravel's responsibility to report and
render.
