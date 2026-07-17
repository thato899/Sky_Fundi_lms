# core/Api

**Purpose**: shared API infrastructure that every other Core service and future module reuses, so `/api/v1` responses are consistent regardless of which service produced them. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Http/Controllers/Controller` — the empty base class every Core/module controller extends.
- `Http/Responses/ApiResponse` (trait) — `ok()/created()/noContent()/message()` success-shape helpers, per [API Conventions](../../docs/api/conventions.md).
- `Http/Middleware/ForceJsonResponse` — forces `Accept: application/json` so Laravel's own error rendering never falls back to an HTML error page on an API route. Prepended globally to the `api` middleware group.
- `Http/Middleware/AssignRequestId` — global request correlation for public, web, and API routes. It preserves bounded UUIDs, replaces unsafe values, and returns the identifier in `X-Request-ID`.
- `Http/Middleware/LogApiRequests` — appended globally. Writes one structured `PlatformLogger` completion line for returned responses or one safe failure line when downstream execution throws, then rethrows the original exception. Route context is best effort: matched routes use their name/template and unresolved routes use a query-free request path. API response analytics remains API-only. Logging/metrics failures never replace a response or application exception.
- `Providers/ApiServiceProvider` — defines the named rate limiters `api-default` (120/min, applied globally via `throttle:api-default`) and `api-sensitive` (10/min, opt-in per route for things like password reset). Individual routes may still layer a stricter, route-specific `throttle:N,M` on top — see `Core\Auth`'s login endpoint.
- `Support/ApiQueryBuilder` — reusable `filter()/sort()/paginate()` helpers implementing [API Conventions' filtering/sorting/pagination rules](../../docs/api/conventions.md#filtering-sorting-pagination) against a field whitelist, so new controllers don't hand-roll the same `->when()` chain. Purely opt-in — existing controllers keep their own inline query logic.
- `Exceptions/ApiExceptionHandler::register()` — the global exception -> JSON mapping wired from `bootstrap/app.php`: validation (422), authentication (401), authorization (403), account-not-active (403), not-found (404), any `HttpExceptionInterface` (its own status, e.g. the 423 `CheckAccountLocked` throws), and a generic catch-all (500) that never returns exception details.

**Allowed dependencies**: `Core\Logging`, `Core\Analytics` (for request logging/metrics only). Depended on by every other Core service and, eventually, every module.
