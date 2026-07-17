# Platform testing and regression-hardening audit

## Baseline and method

This audit is based on the executable PHPUnit suite and production implementation at commit `2a8b52b`. The clean baseline was **140 passing tests with 688 assertions**. Tests run with PHPUnit 11 through Laravel, in-memory SQLite, fake/in-memory framework services, and the synchronous queue. `make migrate-check` separately validates the complete MySQL migrate, seed, rollback, and remigrate path.

The hardened suite finishes at **144 passing tests with 708 assertions**: four tests and 20 assertions were added across two new test files. No test files were split, no production defects were found, and no production files were changed.

The subsequent Organization API hardening milestone started from that clean **144-test, 708-assertion** baseline and finishes at **158 passing tests with 847 assertions**. The existing Organization feature test was expanded from one creation scenario to eleven focused API scenarios, with separate database-integrity and policy tests added. The new coverage exposed and fixed three production defects: the update request inherited from a final request class, the controller called an unavailable authorization method, and the AI/module endpoints passed unsupported Eloquent models to the API response helper. AI credentials are now also explicitly hidden from serialization while retaining the existing encrypted-at-rest cast.

Platform and cross-cutting tests live in `tests/`. Module-owned tests live in `modules/<Module>/tests`. Most persistence tests use `Tests\TestCase` and `RefreshDatabase`; module factories currently exist for Learners and Assessments, while other suites use explicit valid builders local to their test class.

## Existing protection

- Authentication covers API and web login, generic failures, throttling, lockout, token revocation, password reset, logout, CSRF, and trusted organization selection.
- RBAC covers role-derived and direct permissions, revocation, permission middleware, and organization-dashboard permission boundaries.
- Organizations covers basic API creation and default service state. Web entry tests cover active membership, suspended organizations, forged selection, and scoped branding.
- Academics covers year/term lifecycle, calendar, curricula, grades, classes, subjects, web management, organization-owned schema, scoped uniqueness, foreign relationships, inactive membership, and suspended organizations.
- Learners covers numbering, current placement, directory filtering/sorting/pagination, API and web authorization, lifecycle/history immutability, archive/restore, foreign academics, schema, rollback, and organization-scoped uniqueness.
- Staff covers organization-scoped web listing, create/update/status workflow, permission boundaries, foreign UUIDs and departments, and validation.
- Attendance covers eligible-register creation, atomic recording, finalization/reopen, summaries, learner history, API/web boundaries, CSV safety, and foreign session hiding.
- Assessments covers eligibility, atomic mark sheets, score calculation, non-marked semantics, finalize/reopen/release, gradebook/history/summary, private-note exclusion, CSV safety, and foreign resource/reference rejection.
- Reports covers grading bands, periods/templates, finalized-only calculations, weighting edge cases, attendance snapshots, versioning and snapshot immutability, lifecycle, PDF/CSV safety, private-note exclusion, and foreign resource hiding.
- Scheduling covers owned schema, class/staff/room/closure conflicts, half-open adjacency, cancelled lessons, activation/materialization/idempotency, reschedule/cancel/history, attendance idempotency, API/web authentication, forged ownership, export, and foreign UUIDs.

## Weak and regression-prone areas found

- Attendance (98 lines), Assessments (151), Scheduling (152), and Reports (232) compress many independent contracts into three to seven broad methods. A failure can therefore obscure which lifecycle or boundary regressed. Splitting is valuable only alongside further changes; moving the current methods without changing coverage would create churn.
- Before this milestone there was no root integration test exercising the implemented Academics → Staff/Learners → Scheduling → Attendance → Assessments → Reports workflow as one transactionally realistic scenario.
- No deterministic test asserted Core notification queue selection or the registered scheduler command frequencies, including backup registration without executing a backup.
- Database coverage is uneven. Academics, Learners, Reports, and Scheduling assert selected schema assumptions, while Staff, Attendance, Assessments, and Organizations rely mainly on behavior plus the repository-wide MySQL migration check. Delete-action semantics and composite cross-organization foreign keys need more direct focused tests.
- Direct policy tests are sparse. Most authorization protection is exercised through feature middleware/routes, which is useful observable coverage but leaves individual policy methods less diagnostic.
- API contract coverage is representative rather than systematic. Pagination and invalid sorting are strong in Learners/Staff, but response-shape, forced JSON, and status-code matrices are not uniformly asserted for every implemented module endpoint.
- Audit assertions exist indirectly in service tests and dashboards, but organization ownership and sensitive-data exclusion are not uniformly checked for every audited workflow.

## Tests added in this milestone

- `tests/Feature/Integration/PlatformEducationWorkflowTest.php` exercises the complete implemented education workflow, verifies finalized attendance and assessment facts in a report snapshot, checks audit ownership, rejects foreign staff linkage, and hides a foreign learner report route.
- `tests/Feature/Infrastructure/SchedulerAndQueueContractTest.php` verifies hourly/daily/weekly command registration and notification queue naming without dispatching external work or running backup operations.
- `modules/Organizations/tests/Feature/OrganizationApiTest.php` now verifies CRUD and lifecycle events, update audit ownership, filtered/scoped directory behavior, request validation, settings and branding inheritance, idempotent administrator assignment, cross-organization mutation denial, safe AI audit payloads, sensitive response exclusion, encrypted AI credentials, and module enable/disable delegation.
- `modules/Organizations/tests/Feature/OrganizationDatabaseIntegrityTest.php` verifies SQLite-reliable uniqueness and ownership foreign keys without duplicating MySQL-specific migration coverage.
- `modules/Organizations/tests/Unit/OrganizationPolicyTest.php` verifies assigned-administrator isolation and the explicit global platform permission paths.

No shared test helper was added. The audited module setup methods differ in required modules, permissions, academic entities, and actors; extracting them now would couple otherwise focused suites into a large test framework and would not materially reduce duplication. No existing test file was moved solely for appearance.

## Remaining risks

- Attendance, Assessments, Reports, and Scheduling should be split when their next behavioral additions make a clean boundary apparent; their current broad methods remain harder to diagnose than desired.
- MySQL-specific composite constraint and delete-action behavior is principally protected by `make migrate-check`, not exhaustive PHPUnit constraint tests under SQLite.
- Rate-limit `429` behavior is covered for web login but not repeated across module APIs, avoiding fragile timing-dependent duplication.
- Historical enrollment does not exist. Attendance, assessment, and report accuracy intentionally uses current placement; existing regression tests document rather than conceal that limitation.
- Teacher-to-class/subject assignment does not exist, so Staff/Assessment/Scheduling authorization cannot enforce an unsupported assignment boundary.
