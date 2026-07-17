# Organization API testing hardening audit

## Scope and baseline

This milestone audits the implemented Organizations module at base commit `3127e7a` on branch `test/organization-api-hardening`. The clean opening baseline passed with **144 tests and 708 assertions**. The audit inspected the module routes, provider, controller, Form Requests, resource, application service, policy, events, models and casts, repository, migration, permission seeder, manifest, README, and existing tests. It also inspected the connected Core Identity context and permission resolver, RBAC middleware/models, Module Manager, Audit Logs subscriber/model, API response and exception handling, and neighbouring tenant-isolation tests.

The milestone strengthens tests for existing behavior only. It does not add routes, schema, permissions, lifecycle states, or product workflows.

## Implemented API contract and coverage

The Organizations API is the authenticated platform control plane under `/api/v1/organizations`. Global `organizations.*` permissions pass route middleware; object policy checks then allow the relevant global permission or an explicitly assigned organization administrator where supported.

Coverage now verifies:

- authenticated access and JSON authorization/validation failures;
- global permission boundaries and direct policy behavior;
- organization-administrator access to its assigned organization and denial against a foreign organization;
- cross-organization rejection for settings, branding, AI, modules, and administrator assignment, including no owned-row mutation, no partial pivot, no false-success audit record, and unchanged foreign data;
- organization creation, profile update, show, soft deletion, activate/suspend lifecycle, domain events, and update audit ownership;
- directory search/status/type filtering, sorting, pagination metadata, and administrator scoping for non-managers;
- configured setting defaults, organization overrides, and branding inheritance;
- idempotent administrator assignment;
- module enable/disable assignment and delegation to the installed Core module registry;
- AI provider configuration, encrypted credentials at rest, decrypted application access, response exclusion, and credential-free audit payloads;
- response shape and exclusion of license keys, audit actor fields, and AI configuration;
- validation for organization identity, type, country, timezone, quotas, duplicate codes, settings, administrator UUIDs, and module payloads;
- SQLite-reliable unique constraints for organization codes, settings keys, module assignments, and one AI configuration per organization;
- SQLite foreign-key rejection for configuration rows owned by nonexistent organizations.

Fake credentials and a synthetic module registration are used. Tests make no external AI, email, queue-worker, backup, or network calls.

## Production defects found and corrected

Three defects were demonstrated by focused regression tests and checked against the current routes, controller contracts, README, migration, models, and neighbouring controller conventions:

1. `UpdateOrganizationRequest` extended `StoreOrganizationRequest` even though the parent was declared `final`. Loading the update endpoint caused a PHP fatal error. The parent request is now non-final so the existing intentional validation inheritance works.
2. `OrganizationController` invoked `$this->authorize()` without importing Laravel's `AuthorizesRequests` trait, causing every policy-protected item/configuration endpoint to return `500`. The trait is now applied only to this controller.
3. AI and module endpoints passed Eloquent models to `ApiResponse::ok()`, whose declared contract accepts resources, resource collections, or arrays. Both endpoints returned `500`. They now serialize the existing models to arrays. `OrganizationAiConfiguration` also hides `credentials`, ensuring the encrypted cast can be used internally without exposing decrypted secrets in an API response.

No other production behavior was changed.

## Audit-log and sensitive-data findings

Organization events implement the shared `Auditable` contract. Their audit context contains only the organization ID, so AI credentials, provider configuration, settings values, and administrator payloads are not copied into event-generated audit records. The AI regression test checks actor ownership, target ownership, the exact safe `after` payload, and absence of the fake secret.

`OrganizationResource` deliberately excludes `license_key`, `created_by`, `updated_by`, and loaded AI configuration. The AI model additionally hides credentials from all normal serialization. Encrypted-at-rest testing compares the raw database value with the fake secret and separately verifies the model cast can decrypt it.

## Areas not implemented

The following requested focus areas do not have an Organizations API product contract and were not invented:

- Administrator removal has no route, controller action, service method, event, or documented API endpoint. Only idempotent assignment exists.
- Organizations control-plane routes do not use `organization.context`; active membership, enabled-module resolution, and suspended-organization rejection belong to tenant-facing Identity/module routes. The control plane must remain able to inspect and reactivate a suspended organization.
- There is no separate disabled-module middleware. Tenant permission resolution excludes permissions belonging to disabled modules through `Core\Identity\Application\PermissionResolver`; global platform permissions on the Organizations control plane do not use that tenant resolver.
- Lifecycle transitions do not define a transition matrix or reject repeated activate/suspend operations. The implemented operations set the requested status idempotently.
- There is no organization-administrator factory. Focused tests use explicit valid model builders, consistent with the existing module suite.

## Test files

Added:

- `modules/Organizations/tests/Feature/OrganizationDatabaseIntegrityTest.php`
- `modules/Organizations/tests/Unit/OrganizationPolicyTest.php`

Modified:

- `modules/Organizations/tests/Feature/OrganizationApiTest.php`

Production files changed:

- `modules/Organizations/Http/Controllers/Api/V1/OrganizationController.php`
- `modules/Organizations/Http/Requests/StoreOrganizationRequest.php`
- `modules/Organizations/Infrastructure/Models/OrganizationAiConfiguration.php`

## Remaining risks

- MySQL-specific constraint names, delete actions, and full migration rollback behavior remain protected by `make migrate-check`; PHPUnit uses SQLite only for constraints it enforces reliably.
- The list endpoint accepts raw sort/direction/per-page query values without a dedicated index Form Request. Valid filtering is covered, but malformed sort input can depend on the database driver's error behavior; tightening this would be a separate API-contract change.
- Organization event audits intentionally record action and organization ownership rather than full before/after configuration values. This protects secrets but limits forensic detail for non-sensitive settings.
- Administrator removal, explicit lifecycle transition rules, and tenant-aware platform support impersonation remain unimplemented product decisions.

## Verification

The milestone finishes at **158 passing tests with 847 assertions**, up from the clean **144-test, 708-assertion** baseline.

Verified commands:

- `docker compose exec -T app php artisan test modules/Organizations/tests` — 16 tests, 143 assertions;
- `docker compose exec -T app php artisan test tests/Feature` — 48 tests, 172 assertions;
- affected RBAC, Identity context, Academics tenant-isolation, and Learners administration regressions — 27 tests, 132 assertions;
- `make health` — passed;
- `make migrate-check` — isolated MySQL migration, seed, rollback, and re-migration passed;
- `make test` — 158 tests, 847 assertions;
- `make pint` — 657 files passed;
- `make analyse` — three changed production PHP files, no errors;
- `make verify` — passed, including Composer validation, migration lifecycle, full tests, Pint, PHPStan, and diff checking;
- `git diff --check` — passed.

Docker access was unavailable only within the managed sandbox identity. The approved host/WSL execution context confirmed membership in the `docker` group and ran Docker without `sudo`.
