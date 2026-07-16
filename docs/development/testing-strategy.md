# Testing strategy

Unit tests cover isolated model/service rules. Feature tests boot Laravel for HTTP, database, migration, authorization, organization-isolation, and integration behavior. Root `tests/` contains platform-wide Core/web tests; module tests live under `modules/<Name>/tests`. A separate `tests/Integration` suite is not currently configured.

PHPUnit uses in-memory SQLite, array cache/session/mail, and synchronous queues by default. Factories produce valid records; seeders are not fixtures. External providers are faked or mocked. Tests must be independent of order, external networks, wall-clock races, and real tenant databases.

Every behavior change covers the happy path and relevant validation, permission, lifecycle, failure, and cross-organization cases. Migrations receive schema/constraint/rollback coverage when practical. No blanket coverage percentage is configured.

See the executable commands and CI status in [Testing](../testing/README.md).
