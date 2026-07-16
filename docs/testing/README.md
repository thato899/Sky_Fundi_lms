# Testing and verification

PHPUnit 11 is configured in `phpunit.xml`. Platform tests live in `tests/`; bounded-context tests live in `modules/<Name>/tests`. `Unit` suites exercise isolated services/models, while `Feature` suites boot Laravel and cover HTTP, database, authorization, organization isolation, migrations, and cross-capability regressions. There is no separately named integration suite: integration behavior is covered by Feature tests.

The default test environment uses in-memory SQLite, array cache/session/mail, and the synchronous queue. Tests use `Tests\TestCase`, `RefreshDatabase` where persistence is involved, factories for valid records, and fakes/mocks at external boundaries. They must not use external networks, execution order, real tenant databases, or wall-clock races.

## Commands

Run from the repository root:

```bash
docker compose exec app php artisan test modules/Learners/tests
make test-learners
make test
make migrate-check
make pint
make analyse
make verify
```

`make migrate-check` creates and removes a uniquely named temporary MySQL database and checks migrate, seed, rollback, and re-migrate. `make analyse` targets changed production PHP; `ANALYSE_ALL=1 make analyse` is an explicit full audit. `make verify` validates Composer, migrations, tests, formatting, changed-code PHPStan, and whitespace. Always finish with `git diff --check`, `git status --short`, and `git diff --stat`.

## Coverage expectations

Behavior changes require happy-path plus relevant validation, authentication, permission, lifecycle, and cross-organization cases. Migrations need constraint/rollback coverage where practical. Security tests verify that foreign UUIDs do not disclose records, payload ownership is ignored/rejected, and permissions cannot be bypassed.

## CI status

The repository currently contains issue/PR templates but no `.github/workflows` CI workflow. Local Docker verification is therefore the executable quality gate; do not describe hosted CI as present until a workflow is committed.
