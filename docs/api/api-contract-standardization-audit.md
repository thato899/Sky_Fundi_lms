# API contract standardization audit

## Scope and canonical contract

This audit records executable `/api/v1` behavior at baseline `ba36e15`, where **158 tests with 847 assertions** passed. Registered routes, middleware, controllers, Form Requests, resources, models, queries, exception rendering, and tests were inspected; executable behavior is authoritative.

After the focused contract coverage and defect corrections, the verified suite contains **166 tests with 922 assertions**.

The least-breaking canonical contract is:

- normal resources use `{"data": {...}}`; unpaginated collections use `{"data": [...]}`;
- paginated JSON Resource collections add `links` and `meta`, including `current_page`, `last_page`, `per_page`, and `total`; new code should not expose raw paginator internals;
- create normally returns `201`, body-returning reads/updates/actions `200`, and intentional empty deletion `204`;
- message-only actions use `{"message": "..."}`; health and aggregate endpoints may keep documented product-specific top-level shapes;
- validation returns `422` with `error.code=validation_failed`, a safe message, field-keyed `error.details`, and the compatibility `errors` object;
- authentication is `401/unauthenticated`, authorization or invalid active context is `403`, and missing or tenant-hidden resources are `404/not_found`;
- domain rules use `domain_rule_violation` and the exception's status (normally `422`); no global distinct `409` conflict type is implemented;
- malformed JSON is `400/malformed_json`; other framework failures currently use `http_error`; throttling remains `429`;
- unexpected non-debug errors are `500/server_error` without traces, exception classes, SQL, secrets, or paths;
- public identifiers are UUID strings using each resource's established `id` or `uuid` name. Dates are normally `Y-m-d`; resource timestamps are normally ISO 8601.

Empty `message` or `meta` members are not part of every established response and were not invented.

## Surface inventory

All protected routes use Sanctum. Most current-user/module routes also enforce account lock. Operational modules require active `organization.context`; foreign organization-owned UUIDs are resolved or guarded inside that context and return `404`.

| Area | Prefix, requirements, and style | Collections and queries | Serialization and deviations |
|---|---|---|---|
| Organizations | `/organizations`; auth/account lock plus platform permission; control plane intentionally has no active-org context. Form Requests, service, `OrganizationResource`, `ApiResponse`. | Search/status/type and, after this milestone, allowlisted sort/direction plus page size 1–100. Canonical resource pagination. | Create `201`, update/actions `200`, delete `204`. Excludes license key, actor fields, AI configuration; credentials are hidden/encrypted. |
| Academics | `/academics`; auth/account lock/context, academic ownership, permissions. Dedicated requests/resources and `ApiResponse`. | Ordered unpaginated catalogs; only implemented type/year filters. | UUID bindings are organization-enforced. Calendar delete is message-only. Platform-global education settings are deliberate. |
| Staff | `/staff`; auth/account lock/context and permissions. `StaffResource` plus `ApiResponse`. | Employee-number search; fixed page size 25. | Create `201`, update/status `200`. Established `id` and raw Laravel `created_at` are compatibility differences. |
| Learners | `/learners`; auth/account lock/context, policies, Form Requests/resources. | Validated search/filters/dates/sort/direction; page size 1–100; canonical paginator metadata. | Foreign UUIDs hidden. Resource omits ownership/internal history and formats dates/timestamps. |
| Attendance | `/attendance` plus learner history; auth/account lock/context. Session resource plus specialized summary/history/export. | Implemented filters, page size clamped 1–100, fixed history pages. | Session create currently returns resource-default `200`. History nests a paginator under `data` with summary fields; changing either is deferred. |
| Assessments | `/assessments`, `/assessment-categories`, `/gradebook`, histories/summaries; auth/account lock/context. Resources coexist with safe raw models/aggregates. | Assessment sort allowlist, normalized direction, clamped pages. | Private notes excluded. History/gradebook nest paginators under `data`; summary is product-specific. Deferred. |
| Reports | `/report-cards`, `/grading-scales`, `/reporting-periods`; auth/account lock/context. Safe raw arrays/models plus PDF/CSV. | Report-card sort allowlist and normalized direction; clamped/fixed pages. | Creates/generation `201`; updates/lifecycle `200`. Snapshot metadata/private notes hidden. Raw-model timestamps/relations are a compatibility inconsistency. |
| Scheduling | `/scheduling`; auth/account lock/context. Safe raw models/arrays plus CSV. | Implemented filters, lesson sort allowlist, clamped pages. | Room `online_url` is hidden where required. Rooms/templates/lessons expose paginator fields at top level; re-enveloping is deferred. |
| Identity/auth | `/auth`, `/me`, `/identity`; public login/reset subset, otherwise auth/account lock; context requires active organization. | Membership collections are not general searchable directories. | Invalid/inactive membership and suspended organization safely deny. Passwords, reset data, and tokens are not serialized except the deliberate login token exchange. |
| Core platform | Other `/api/v1` Core owners: users, RBAC, settings, branding, modules, licensing, subscriptions, providers, security, audit, analytics, health. | Owner-specific allowlists/fixed pagination. | Predominantly resources/explicit arrays. Public health deliberately returns only top-level `status`; details are permission-gated. Secrets and audit internals are excluded. |

Across all areas, Form Requests or controller validation define required/optional, UUID, enum, date/timezone, boolean, array, uniqueness, and relationship rules. Ownership identifiers are prohibited or ignored as selectors, and related organization-owned UUIDs are service/request scoped.

## Error and safety findings

| Condition | Observable contract | Classification |
|---|---|---|
| Invalid fields | `422 validation_failed`, field details | Consistent |
| Missing/revoked auth | `401 unauthenticated` | Consistent |
| Permission/policy denial | `403 forbidden` | Consistent |
| Inactive membership/suspended org | safe `403` | Deliberate product response |
| Missing/foreign resource | safe `404 not_found` | Consistent secrecy boundary |
| Domain/lifecycle failure | normally `422 domain_rule_violation` | Existing behavior |
| Throttle/method failure | `429`/`405`, generic `http_error` | Specialized codes deferred |
| Malformed JSON | `400 malformed_json` | Defect corrected here |
| Unexpected, debug off | generic `500 server_error` | Consistent and safe |

Dedicated resources materially protect Organizations, Academics, Staff, Learners, Attendance sessions, Assessments, and many Core records. Reports, Scheduling, configuration, summaries, and histories also serialize models/paginators directly. Existing visibility/controller logic excludes known passwords, tokens, credentials, license keys, private notes, snapshot/audit internals, and unrelated ownership. No resource class was added solely for appearance.

Routes use UUIDs. Malformed/unknown UUIDs produce JSON `404` or validation `422`, not `500`; foreign UUIDs do not reveal existence. Established `id` versus `uuid` names and raw relationship IDs are inconsistent but cannot be renamed safely in v1.

## Changes made

1. Organizations directory query rules now allowlist sort fields/direction and validate filters/page size. Valid requests are unchanged; unsafe or undefined values deterministically return `422`.
2. The existing JSON middleware now distinguishes syntactically malformed, non-empty JSON from field validation and returns the documented safe `400 malformed_json`.
3. Focused cross-platform tests protect forced JSON, validation, authentication, not found, domain errors, method-not-allowed, malformed JSON, non-debug unexpected errors, and deliberate public-health behavior.

## Deferred breaking inconsistencies

- No mandatory `message: null` or `meta: {}` was added.
- Attendance/Assessment history and gradebook paginator nesting was not changed.
- Scheduling paginator fields and module summary metrics were not re-enveloped.
- Established `id`/`uuid` fields and raw-model timestamps were not renamed/reformatted.
- Attendance creation was not changed from established `200`.
- Generic `http_error` codes for `405`/`429` were retained pending consumer review.
- Raw models were not mechanically replaced where no safety defect was shown.

## Remaining risks

Some broad module tests combine many contracts, and raw-model responses could gain fields if visibility changes later. New work there should prefer explicit safe arrays/resources and canonical pagination. MySQL constraints remain covered by `make migrate-check`; HTTP tests use SQLite. Rate limiting is not made timing-dependent in the focused suite.
