# API Conventions

## Versioning

All API routes are versioned in the URL: `/api/v1/...`. Breaking changes require a new version (`/api/v2/...`); additive changes (new optional fields, new endpoints) do not. See [`../versioning.md`](../versioning.md) for the platform-wide versioning policy.

## URL Structure

```
/api/v1/{module}/{resource}[/{id}][/{sub-resource}]
```

Examples (illustrative — these modules do not exist yet):
```
GET  /api/v1/academics/subjects
GET  /api/v1/academics/subjects/42
POST /api/v1/attendance/registers/17/entries
```

Core, non-module endpoints live under `/api/v1/core/...` or well-known top-level paths where conventional (`/api/v1/auth/login`, `/api/v1/me`).

## Resource Naming

- Plural, kebab-case for multi-word resources: `/report-cards`, not `/reportCards` or `/report_card`.
- Nouns, not verbs, in URLs. Actions are expressed via HTTP method, not a verb in the path (`POST /enrollments`, not `/enroll-learner`).

## HTTP Methods

| Method | Use |
|---|---|
| GET | Retrieve a resource or collection. No side effects. |
| POST | Create a resource, or trigger a non-idempotent action. |
| PUT | Full replace of a resource. |
| PATCH | Partial update of a resource. |
| DELETE | Remove a resource (soft-delete by default; see Database Conventions). |

## Request/Response Shape

All responses are JSON. Successful single-resource responses:

```json
{
  "data": { "id": 42, "type": "subject", "attributes": { "...": "..." } }
}
```

Collections are paginated by default:

```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 25, "total": 130 },
  "links": { "next": "...", "prev": null }
}
```

## Filtering, Sorting, Pagination

- Filtering: `?filter[status]=active`
- Sorting: `?sort=-created_at` (leading `-` = descending)
- Pagination: `?page=2&per_page=25` (default `per_page` and max are set per-endpoint and documented in the module's own API doc)

## Authentication

See [`authentication.md`](authentication.md).

## Errors

See [`error-handling.md`](error-handling.md).

## Field Naming

`snake_case` for all JSON field names, matching database column naming (see [Database Conventions](../database/conventions.md)), for consistency between API payloads and persistence.

## Idempotency

State-changing endpoints that may be retried by mobile clients on flaky connections (e.g. attendance submission) should support an `Idempotency-Key` header once Core's API layer implements idempotency support. Document this per-endpoint as modules are built.

## Rate Limiting

Every request on the `api` middleware group is throttled by the `api-default` named rate limiter (120 requests/minute, keyed by authenticated user id or IP for guests) — see `Core\Api\Providers\ApiServiceProvider`. Routes handling sensitive, low-frequency actions (password reset, AI provider testing) additionally apply a stricter, route-specific `throttle:N,M` on top — see `Core\Auth`'s login endpoint for the pattern. A `429 Too Many Requests` response follows the standard error shape in [error-handling.md](error-handling.md).

## Request Logging & Metrics

Every API request is logged once (method, path, status, duration) via `Core\Logging\Application\PlatformLogger`'s `application` channel, and recorded once as an `AnalyticsMetric::ApiRequest` event via `Core\Analytics` — both applied globally by `Core\Api\Http\Middleware\LogApiRequests`. Neither can fail the response: logging/metrics errors are swallowed. See [`core/Api/README.md`](../../core/Api/README.md) and [`core/Analytics/README.md`](../../core/Analytics/README.md).

## Reusable Filtering/Sorting/Pagination

`Core\Api\Support\ApiQueryBuilder::filter()/sort()/paginate()` implements the filtering, sorting, and pagination conventions above against an explicit field whitelist, for controllers that want to opt into a shared helper rather than hand-rolling the same `->when()` chain. Opt-in — existing controllers built before this helper existed are not required to adopt it.

