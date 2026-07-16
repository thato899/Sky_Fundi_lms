# Coding Standards

## PHP

- PSR-12 coding style, enforced by the installed Laravel Pint configuration and `make pint`.
- `declare(strict_types=1);` at the top of every PHP file.
- Type-hint everything: parameters, return types, property types. No untyped `mixed` unless genuinely necessary and commented why.
- PSR-4 autoloading; `App\`, `Core\`, and `Modules\` map directly to `app/`, `core/`, and `modules/`.

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

PHPStan is installed and `make analyse` checks changed production PHP. `ANALYSE_ALL=1 make analyse` performs an explicit repository-wide audit. Hosted CI is not currently configured.

## Formatting Automation

Run `make pint` and `make analyse`, or the aggregate `make verify`, before handoff.
