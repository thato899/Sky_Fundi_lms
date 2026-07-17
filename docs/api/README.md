# Implemented HTTP API

The canonical API prefix is `/api/v1`. Run `docker compose exec app php artisan route:list --path=api/v1` for the executable endpoint inventory; route files under each owner are the source of truth. JSON requests use `Accept: application/json`; protected endpoints use `Authorization: Bearer <token>` from Sanctum login.

## Contracts by owner

| Surface | Prefix | Detailed contract/source |
|---|---|---|
| Auth/current user | `/auth`, `/me` | [authentication](authentication.md), `core/Auth/routes/api.php` |
| Users, roles, permissions | `/users`, `/roles`, `/permissions` | owning `core/*/routes/api.php`, Form Requests and Resources |
| Identity | `/identity` | memberships, invite/accept/reject/switch/current context |
| Platform administration | `/branding`, `/settings`, `/modules`, `/licenses`, `/subscriptions`, `/deployment-profiles`, `/feature-flags`, `/analytics`, `/audit-logs` | owning Core route/controller/request/resource |
| Runtime providers | `/ai/providers`, `/mail/providers`, `/storage/disks` | provider discovery/testing; configured adapters only |
| Security/health | `/security`, `/health` | trusted devices, sessions, IP restrictions, minimal/detailed health |
| Organizations | `/organizations` | [Organizations README](../../modules/Organizations/README.md) and route file |
| Academics | `/academics` | [academics](academics.md) |
| Learners | `/learners` | [learners](learners.md) |
| Staff | `/staff` | module route, requests, resource |
| Attendance | `/attendance` | [attendance](attendance.md) |
| Assessments | `/assessments`, `/assessment-categories` | [assessments](assessments.md) |
| Reports | `/reports` | [reports](reports.md) |
| Scheduling | `/scheduling` | [scheduling](scheduling.md) |

## Authentication, authorization, and isolation

Public API routes are login/password reset, public branding, and minimal health. Other routes require Sanctum and commonly `account.not-locked`. Administrative Core routes require named `core.*` permissions. Organization operational routes additionally require active organization context and action-specific permission/policy checks. Resource middleware resolves UUIDs inside the active organization; another organization's UUID returns `404`. `organization_id` in a body is never an ownership selector.

## Requests, validation, and responses

The canonical compatibility-aware contract and per-area deviations are recorded in the [API contract standardization audit](api-contract-standardization-audit.md). The exact body contract for each write endpoint is its referenced `Http/Requests/*Request::rules()`; controller-local validation is authoritative where no Form Request exists.

Normal resources use `{"data": {...}}`; unpaginated collections use `{"data": [...]}`; paginated JSON Resource collections add `links` and `meta`. The platform does not add empty `message` or `meta` fields to every response. Create endpoints normally return `201`, body-returning reads/updates/actions `200`, and intentional empty responses use `204`. Public health and aggregate/summary endpoints retain deliberate product-specific shapes.

Errors use `{"error": {"code": "...", "message": "...", "details": {...}}}`. Validation returns `422` and also includes the Laravel-compatible field-keyed `errors` object; malformed JSON returns `400`; unauthenticated returns `401`; forbidden returns `403`; scoped/not-found returns `404`; throttling returns `429`; domain-state failures use [error handling](error-handling.md). Exports return CSV/PDF rather than JSON.

## Pagination, filtering, and sorting

Only endpoints whose controller/query service implements these features accept them. Typical list responses use Laravel JSON Resource pagination metadata. Learners and Organizations validate sort/direction/page size; other modules use controller allowlists, fixed ordering, or normalized defaults as documented in the audit. Never advertise or pass arbitrary database-column sorting.

## Web routes

Blade routes are not REST endpoints. They use session authentication, CSRF, account lock, organization context, the same policies/services, and scoped resolvers. Inspect `routes/web.php` and `modules/*/routes/web.php` with `artisan route:list` for the complete executable web surface.
