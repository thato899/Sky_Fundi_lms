# Sky Fundi Platform

Sky Fundi is a modular, multi-tenant education-platform foundation for tutors, schools, colleges, and training providers. It is a Laravel 12 application, not an empty scaffold.

## Current scope

The repository currently includes the Platform Core; authentication; RBAC and permissions; Organizations; organization identity and membership; Academics; the Staff and Learners profile foundations; an AI Gateway; audit logging; settings and branding; licensing and subscription foundations; and storage, mail, queue, backup, and health foundations. Docker development configuration is included.

This is **not** yet the complete sellable education MVP. Learner workflows, guardians, attendance, assessments, content delivery, billing workflows, portals, and mobile applications are not part of the implemented scope.

## Docker quick start

Prerequisites: Docker Engine (including Docker Engine running directly inside WSL) or Docker Desktop, plus Git. From the repository root:

```bash
docker compose up --build init
docker compose up -d
docker compose exec app php artisan migrate --seed
```

The first command starts MySQL, creates `.env` only when it is absent, installs the locked Composer dependencies, generates a missing application key, and exits successfully. The application then opens at `http://localhost:8000`, with Mailpit at `http://localhost:8025`.

For the full workflow, verification commands, optional Ollama setup, and troubleshooting, see [the local runbook](docs/development/LOCAL_RUNBOOK.md).

## Repository structure

```
app/        Laravel application code
core/       Platform-wide services and cross-cutting concerns
modules/    Self-contained domain modules
database/   Migrations, factories, and seeders
docker/     Development-container bootstrap scripts
docs/       Architecture and developer documentation
tests/      Automated test suites
```

## Documentation

Start with [the architecture overview](docs/architecture/overview.md), then see [Organizations](docs/modules/organizations.md), [identity and membership](core/Identity/README.md), and [the local runbook](docs/development/LOCAL_RUNBOOK.md).

## Contributing

Read [CONTRIBUTING.md](CONTRIBUTING.md) and [the Git workflow](docs/development/git-workflow.md) before opening a pull request. Contributions must preserve the documented module boundaries.

## License

See [LICENSE](LICENSE).
