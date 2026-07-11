# core/Api

**Purpose**: shared API infrastructure that every other Core service and future module reuses, so `/api/v1` responses are consistent regardless of which service produced them. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Http/Controllers/Controller` — the empty base class every Core/module controller extends.
- `Http/Responses/ApiResponse` (trait) — `ok()/created()/noContent()/message()` success-shape helpers, per [API Conventions](../../docs/api/conventions.md).
- `Http/Middleware/ForceJsonResponse` — forces `Accept: application/json` so Laravel's own error rendering never falls back to an HTML error page on an API route.
- `Exceptions/ApiExceptionHandler::register()` — the global exception -> JSON mapping wired from `bootstrap/app.php`: validation (422), authentication (401), authorization (403), account-not-active (403), not-found (404), any `HttpExceptionInterface` (its own status, e.g. the 423 `CheckAccountLocked` throws), and a catch-all (500) that only leaks exception detail when `APP_DEBUG` is true.

**Allowed dependencies**: none. Depended on by every other Core service and, eventually, every module.
