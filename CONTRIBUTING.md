# Contributing to Sky Fundi Platform

Thank you for contributing to Sky Fundi. This document describes how to propose changes, the standards your changes must meet, and how review works.

## Before You Start

1. Read [`docs/architecture/overview.md`](docs/architecture/overview.md) and [`docs/architecture/module-system.md`](docs/architecture/module-system.md). Sky Fundi is strictly modular — Core must never contain educational logic, and modules must never reach into each other's internals.
2. Read [`docs/development/coding-standards.md`](docs/development/coding-standards.md).
3. Read [`docs/development/git-workflow.md`](docs/development/git-workflow.md) for branch naming and commit conventions.

## Ground Rules

- **No educational logic in `/core`.** If you find yourself adding a School, Assessment, Homework, or similar concept to Core, it belongs in a module instead.
- **Modules do not depend on each other directly.** Cross-module interaction happens through documented contracts (events, service interfaces) — see [`docs/modules/module-development-guide.md`](docs/modules/module-development-guide.md).
- **All AI calls go through the AI Gateway.** Never call an AI provider SDK directly from a module.
- **Every new folder gets a `README.md`** explaining its purpose, responsibilities, and allowed dependencies, consistent with the rest of the repository.
- **API-first.** New capability is designed as a REST endpoint under `docs/api/conventions.md` before any Blade/React UI is built against it.
- **PSR-12** coding style, strict types, and Laravel conventions are mandatory — see coding standards.

## Workflow

1. Fork or branch from `develop` (see [Git Workflow](docs/development/git-workflow.md)).
2. Create a feature branch: `feature/<module-or-area>/<short-description>`.
3. Make focused, atomic commits with descriptive messages.
4. Add or update tests for anything you change (see [Testing Strategy](docs/development/testing-strategy.md)).
5. Update relevant documentation in the same PR — code and docs must not drift apart.
6. Open a pull request against `develop` using the PR template. Fill in every section.
7. Address review feedback. At least one CODEOWNER approval is required before merge.

## Commit Message Format

```
<type>(<scope>): <short summary>

[optional body]

[optional footer(s)]
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `build`, `ci`.
Scope: the module or area affected, e.g. `core-auth`, `module-academics`, `docs`.

Example: `feat(core-rbac): add permission caching layer`

## Reporting Issues

Use the issue templates under `.github/ISSUE_TEMPLATE`. Provide reproduction steps for bugs, and a clear problem statement plus proposed contract for feature requests or module proposals.

## Code of Conduct

Be respectful, be constructive, assume good intent. Disagreements about architecture should be resolved by referring to the documented principles in `docs/architecture/`, not by personal preference.
