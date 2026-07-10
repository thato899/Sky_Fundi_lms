# Developer Onboarding

## Before Core Exists

This repository is currently at the foundation stage — Core and modules are not yet implemented (see [Roadmap](../roadmap.md)). Onboarding today means understanding the architecture and conventions well enough to build against them correctly:

1. Read [`../architecture/overview.md`](../architecture/overview.md), [`clean-architecture.md`](../architecture/clean-architecture.md), and [`module-system.md`](../architecture/module-system.md) fully before writing any code.
2. Read [`coding-standards.md`](coding-standards.md) and [`git-workflow.md`](git-workflow.md).
3. Read [`../api/conventions.md`](../api/conventions.md) and [`../database/conventions.md`](../database/conventions.md).

## Once Core Exists (target flow)

1. Clone the repository.
2. `composer install`
3. Copy `.env.example` to `.env` and fill in local values (see [Environment Variables](../environment-variables.md)).
4. `php artisan key:generate`
5. `php artisan migrate --seed` (Core tables + demo tenant)
6. `php artisan serve` (or configured local dev server)
7. Run the test suite: `php artisan test`

This section will be replaced with exact, verified commands once Core's Laravel application skeleton is committed.

## Local Tooling Expectations

- PHP 8.3+, Composer
- MySQL (or compatible) locally, or a containerized equivalent
- Redis, optional but recommended locally to match production-like queue/cache behavior
- Node.js/npm, once frontend build tooling is introduced beyond plain Blade

## Where to Ask Questions

Open a GitHub issue using the appropriate template under `.github/ISSUE_TEMPLATE/`, or discuss architectural questions by referencing the relevant doc under `/docs/architecture` so decisions stay traceable.
