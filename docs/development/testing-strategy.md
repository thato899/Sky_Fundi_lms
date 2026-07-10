# Testing Strategy

## Layers of Testing

| Type | Scope | Location |
|---|---|---|
| Unit | Domain and Application layer classes, in isolation, no database/HTTP | `modules/<Name>/tests/Unit`, `core/<Service>/tests/Unit` |
| Feature | Http layer end-to-end against a real test database (in-memory/SQLite or a disposable MySQL test DB) | `modules/<Name>/tests/Feature`, `core/<Service>/tests/Feature` |
| Integration | Cross-service behavior within Core (e.g. RBAC + Auth together), or a module's declared Core dependencies | `tests/Integration` (platform-level) |

`/tests` at the repository root is reserved for platform-wide/integration tests that intentionally span more than one module or Core service; module- and Core-service-specific tests live alongside that code, per [Module Anatomy](../architecture/module-system.md#module-anatomy).

## Principles

- Domain layer tests require no framework bootstrapping — pure PHPUnit against plain PHP objects. This is a direct payoff of [Clean Architecture](../architecture/clean-architecture.md): Domain has no Laravel dependency, so it's fast and trivial to test.
- Application layer tests fake/mock Core service interfaces (e.g. a fake AI Gateway, a fake Notifications service) rather than hitting real infrastructure.
- Feature tests use module/Core factories and a disposable test database; never run against a real tenant database.
- Multi-tenancy: feature tests must cover that data from one tenant is never visible/writable from another tenant's context (see [Multi-Tenancy](../architecture/multi-tenancy.md)).

## Coverage Expectations

Precise coverage thresholds will be set once CI is introduced alongside first real code; the target is high coverage on Domain/Application (business rules) and meaningful coverage on Http (happy path + auth/validation failure paths), rather than a blanket percentage chased for its own sake.

## Test Data

Factories, not hand-built fixtures, are the default way to construct test data, consistent with Laravel convention. Seeders (see [Migration Standards](../database/migration-standards.md#seeders)) are for local dev demo data, not test fixtures.
