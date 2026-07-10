# Coding Standards

## PHP

- PSR-12 coding style, enforced via Laravel Pint (or PHP-CS-Fixer) once tooling is added to the repository.
- `declare(strict_types=1);` at the top of every PHP file.
- Type-hint everything: parameters, return types, property types. No untyped `mixed` unless genuinely necessary and commented why.
- PSR-4 autoloading; namespace mirrors folder structure (`Modules\Academics\Domain\Subject`, `Core\Auth\Domain\User`, etc. — exact root namespace to be fixed when Core's composer setup is committed).

## Laravel Conventions

- Controllers are thin — see [Clean Architecture](../architecture/clean-architecture.md#interface--adapters). No business logic in controllers.
- Form Requests for all input validation; no inline `$request->validate()` in controllers for anything beyond the most trivial single-field case.
- Eloquent models contain relationships, casts, and simple accessors only — no business rules.
- Facades are acceptable in Infrastructure/bootstrapping code; Domain and Application layers use constructor-injected interfaces, never facades, so they remain framework-agnostic and unit-testable without booting Laravel.
- Config values are always accessed via `config()`, never `env()`, outside of the config files themselves (standard Laravel best practice — keeps config cacheable in production).

## Naming

See [`../naming-conventions.md`](../naming-conventions.md) for platform-wide naming rules (files, classes, routes, permissions, events).

## Comments and Documentation

- Code should be self-explanatory through naming; comments explain *why*, not *what*.
- Every public service/class in Application and Domain layers has a docblock summarizing its responsibility, consistent with the "every module/folder has a README explaining purpose" principle applied at the class level.

## Static Analysis

PHPStan (or equivalent) at a meaningful strictness level is expected to be introduced alongside first real Core code, run in CI (see [Git Workflow](git-workflow.md)).

## Formatting Automation

Formatting (Pint) and static analysis are expected to run as pre-commit/CI checks once the Laravel application skeleton exists — not manually enforced by convention alone.
