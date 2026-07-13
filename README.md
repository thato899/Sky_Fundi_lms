# Sky Fundi Platform

**Sky Fundi** is a modular, multi-tenant education platform built to serve individual tutors, tutoring centres, primary schools, secondary schools, colleges, training centres, and future education institutions from a single, extensible codebase.

This repository is the **foundation** of the platform. It contains no application/business logic yet — it establishes the architecture, conventions, and documentation that all future development (by any contributor, at any point in the platform's lifetime) must follow.

> Status: 🏗️ Foundation stage — Core and modules are not yet implemented. See [`docs/roadmap.md`](docs/roadmap.md).

---

## What is Sky Fundi?

Sky Fundi replaces a legacy, tightly-coupled system with a clean, modular architecture where **no educational feature lives in the platform core**. Everything domain-specific — academics, attendance, homework, assessments, library, transport, finance, and so on — is built as an independently installable, enableable, and removable **module**.

The platform is designed to be:

- **Modular** — features are self-contained modules with explicit, controlled dependencies
- **Multi-tenant ready** — architecture supports schools, tutoring centres, colleges, and training academies as distinct tenant types, even where deployments are single-database-per-school
- **API-first** — every capability is exposed via a versioned REST API before any UI consumes it
- **Mobile ready** — the API is designed for web, Flutter, and native Android/iOS consumption from day one
- **AI-ready** — all AI capability flows through a single **AI Gateway** abstraction; no module talks to an AI provider directly
- **Secure by default** — RBAC, audit logging, and defensive defaults are part of the Core, not bolted on later

## Tech Stack

| Layer | Technology |
|---|---|
| Language / Runtime | PHP 8.3+ |
| Framework | Laravel (latest stable) |
| Database | MySQL |
| Cache / Queue backing | Redis (optional) |
| Background jobs | Laravel Queue |
| API | REST, versioned |
| Initial frontend | Blade |
| Future frontends | React, Flutter, Android |

## Repository Structure

```
Sky_Fundi_lms/
├── core/           # Platform core — Auth, RBAC, Users, Settings, AI Gateway, etc. Nothing academic.
├── modules/        # Self-contained, pluggable feature modules (Academics, Attendance, Library, ...)
├── docs/           # All architecture, API, security, and developer documentation
├── config/         # Application/platform configuration
├── resources/      # Shared views, lang files, front-end assets
├── public/         # Web server entry point and public assets
├── storage/        # Runtime storage (logs, cache, uploaded files)
├── tests/          # Automated test suites
├── .github/        # Issue templates, PR template, CI workflows
```

Every top-level and module-level folder contains its own `README.md` describing its purpose, responsibilities, and allowed dependencies. Start with [`docs/architecture/overview.md`](docs/architecture/overview.md) for the full picture.

## Documentation Index

| Topic | Location |
|---|---|
| Architecture overview | [`docs/architecture/overview.md`](docs/architecture/overview.md) |
| Clean Architecture layers | [`docs/architecture/clean-architecture.md`](docs/architecture/clean-architecture.md) |
| Module system | [`docs/architecture/module-system.md`](docs/architecture/module-system.md) |
| Multi-tenancy | [`docs/architecture/multi-tenancy.md`](docs/architecture/multi-tenancy.md) |
| Module development guide | [`docs/modules/module-development-guide.md`](docs/modules/module-development-guide.md) |
| Organization management | [`docs/modules/organizations.md`](docs/modules/organizations.md) |
| API conventions | [`docs/api/conventions.md`](docs/api/conventions.md) |
| Database standards | [`docs/database/conventions.md`](docs/database/conventions.md) |
| Security | [`docs/security/README.md`](docs/security/README.md) |
| AI Gateway | [`docs/ai/ai-gateway.md`](docs/ai/ai-gateway.md) |
| Deployment | [`docs/deployment/README.md`](docs/deployment/README.md) |
| Developer onboarding | [`docs/development/onboarding.md`](docs/development/onboarding.md) |
| Coding standards | [`docs/development/coding-standards.md`](docs/development/coding-standards.md) |
| Git workflow | [`docs/development/git-workflow.md`](docs/development/git-workflow.md) |
| Testing strategy | [`docs/development/testing-strategy.md`](docs/development/testing-strategy.md) |
| Naming conventions | [`docs/naming-conventions.md`](docs/naming-conventions.md) |
| Environment variables | [`docs/environment-variables.md`](docs/environment-variables.md) |
| Versioning | [`docs/versioning.md`](docs/versioning.md) |
| Roadmap | [`docs/roadmap.md`](docs/roadmap.md) |

## Getting Started

This repository does not yet contain a runnable application — it is the scaffolding and rulebook the application will be built against. Once Core implementation begins, this section will be replaced with real installation steps. In the meantime see [`docs/development/onboarding.md`](docs/development/onboarding.md) for how to set up a working environment ahead of first code.

## Contributing

Please read [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`docs/development/git-workflow.md`](docs/development/git-workflow.md) before opening a pull request. All contributions must respect the module boundaries described in [`docs/architecture/module-system.md`](docs/architecture/module-system.md).

## License

See [`LICENSE`](LICENSE).
