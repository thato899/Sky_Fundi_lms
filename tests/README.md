# /tests

**Purpose**: platform-wide and cross-cutting tests that intentionally span more than one module or Core service.

**Responsibilities**: integration-level tests (e.g. "a module's declared Core dependencies actually resolve," "tenant isolation holds across Auth + RBAC + a sample module"). Module- and Core-service-specific Unit/Feature tests live alongside that code instead (`modules/<Name>/tests`, `core/<Service>/tests`) — see [Testing Strategy](../docs/development/testing-strategy.md).

**Allowed dependencies**: may reference Core and modules freely (tests are the one place cross-boundary reference is expected), since the point is verifying the boundaries hold.

**Future usage**: `tests/Integration/` and shared test support (base TestCase classes, factories shared across modules) will live here once implemented.
