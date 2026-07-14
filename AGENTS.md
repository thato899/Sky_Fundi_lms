# Sky Fundi Codex Instructions

## Purpose and repository overview

Sky Fundi is a Laravel 12, PHP 8.3 modular education platform. Platform-wide capabilities live in `core/`, educational and operational bounded contexts live in `modules/`, application bootstrapping lives in `app/` and `bootstrap/`, and platform-wide tests live in `tests/`.

This file is the default operating manual for every AI-assisted change. Read it before acting. Then inspect the relevant module/Core README, neighboring implementation, migrations, tests, `composer.json`, `bootstrap/providers.php`, and the applicable documents under `docs/`. The repository's executable implementation is the source of truth when older prose is aspirational or stale; do not silently rewrite architecture to match a document.

## Required working method

1. Before edits, run `pwd`, `git branch --show-current`, `git status --short`, and `git log -1 --oneline`.
2. Read all applicable `AGENTS.md` files from the repository root down to the working directory.
3. Preserve pre-existing worktree changes. Never overwrite, revert, format, or include unrelated user changes.
4. Inspect analogous code before choosing paths, names, abstractions, schema types, or test style.
5. Restate scope and identify risky assumptions. Ask only when a choice cannot be safely inferred.
6. Implement the smallest cohesive change. Do not add speculative abstractions or adjacent features.
7. Test narrowly first, then run the proportional repository checks described below.
8. Review `git diff --check`, `git status --short`, and `git diff --stat` before reporting.

## Scope control

- Treat the user's explicit objective, exclusions, and file list as hard boundaries. Do not turn a focused task into cleanup, refactoring, dependency upgrades, or a neighboring feature.
- Read-only inspection may cross the scope boundary when needed to understand conventions. Writes may not.
- If the worktree mixes milestones, stage explicit paths only. Preserve unrelated work in place or, with user authorization, in a named stash.
- Stop and report when safe completion requires new authority, a destructive action, or a materially different architecture.

## Architecture principles

- Preserve the existing modular, clean-architecture direction. Core contains platform-wide concerns; educational concepts belong in modules.
- Keep controllers thin: Form Request validation, application/service invocation, then Resource/response shaping.
- Business workflows belong in Application services. Eloquent models are Infrastructure and contain persistence details, relationships, casts, scopes, and small accessors—not workflow rules.
- Domain code should remain framework-independent where the existing bounded context follows that pattern.
- Use repositories/interfaces where existing complexity warrants them; do not create ceremonial repositories for simple persistence.
- Use dependency injection in Application/Domain code. Facades are acceptable in framework-facing Infrastructure and provider code.
- Cross-cutting functionality uses Core facilities. All AI-provider access must go through `core/AIGateway`; modules never call provider SDKs directly.
- Prefer domain events for decoupled reactions. Do not create circular dependencies or let two modules write the same table.
- Existing, explicitly declared module relationships may be followed when the local code requires them. Do not introduce a new hard cross-module dependency without documenting and testing the contract.

## Module and Core conventions

- Namespaces map directly through Composer: `Core\<Service>\...` to `core/<Service>/...`, `Modules\<Module>\...` to `modules/<Module>/...`.
- Module folders use PascalCase. A module normally has `README.md`, `module.json`, a provider, and only the layer folders it actually needs. Never create empty placeholder folders or files.
- Common folders are `Domain/Enums`, `Application`, `Events`, `Http/Requests`, `Http/Resources`, `Infrastructure/Models`, `Infrastructure/Repositories`, `Providers`, `database/migrations`, `database/seeders`, `routes`, and `tests/{Unit,Feature}`.
- Eloquent models live under `Infrastructure/Models`. Services live under `Application`. HTTP validation and serialization live in `Http/Requests` and `Http/Resources`.
- Module manifests use lower-case machine names, a display name, semantic version, description, dependencies, and provided permissions/events matching existing manifests.
- Providers register bindings in `register()` and migrations, routes, listeners, middleware aliases, or commands in `boot()`. Add providers to `bootstrap/providers.php` in dependency-safe order. Do not add routes to a module that has no route surface.
- API routes currently use the `api` middleware and `/api/v1` prefix through providers. Preserve existing response, authorization, and versioning conventions.
- Events are final classes, use Laravel `Dispatchable` and `SerializesModels` when carrying models, expose constructor-promoted readonly data, and implement `Core\Support\Contracts\Auditable` when the action requires audit capture.

## Tenant isolation and organization ownership

- The implemented shared-database ownership boundary is the organization. Tenant-owned rows use `organization_id`, and requests use the organization context in `core/Identity`.
- Never trust an organization identifier without authorization and resolved context. Scope reads, writes, uniqueness constraints, cache keys, jobs, exports, and tests to the organization.
- Never expose, infer, update, or delete another organization's data. Platform-wide exceptions require an explicit Core service, platform permission, and audit trail.
- Relationships between organization-owned rows must not permit cross-organization association. Validate this at the service/request boundary and test isolation.
- Jobs and commands operating on organization data must carry/require explicit organization context; workers have no implicit request context.
- New organization-owned migrations include an indexed `organization_id` foreign key using the exact type of `organizations.id`. Use composite organization-scoped uniqueness where identity is local to an organization.
- Do not mechanically replace current `organization_id` code with generic `tenant_id`; some older docs use that aspirational term.

## PHP and coding conventions

- Every PHP file begins with `<?php`, a blank line, and `declare(strict_types=1);`.
- Follow PSR-12 and Laravel Pint. Use PascalCase classes/enums, camelCase methods/variables, snake_case database fields, and PascalCase backed-enum cases with lower snake-case values where applicable.
- Prefer `final` for concrete classes consistent with neighboring code. Type parameters, return values, promoted properties, and collections as precisely as practical.
- Use constructor injection, early returns, clear names, and small methods. Comments explain decisions and constraints, not syntax.
- Use `config()` outside config files; never call `env()` from application code.
- Never log secrets, tokens, passwords, provider credentials, private learner/staff data, or entire request payloads containing personal data.
- Use mass-assignment strategy, UUID behavior, casts, soft deletes, and table naming consistent with neighboring models. Platform models currently use UUID primary keys, commonly via `Core\Support\Traits\HasUuidPrimaryKey`.

## Database and migration rules

- Inspect referenced table definitions before selecting ID/foreign-key types. Use database-enforced foreign keys and explicit delete behavior.
- Module/Core migrations live with their owner under `database/migrations`; a migration must not modify another owner's table unless an already-established Core integration explicitly requires it.
- Use timestamped, action-based migration names. Never edit a migration already deployed outside an explicitly approved pre-release workflow; add a new migration.
- Migrations are additive by default, include indexes justified by access/constraint patterns, and implement a complete `down()` in reverse dependency order.
- Use soft deletes for restorable/auditable real-world records. Make nullable fields intentional. Use JSON only for genuinely flexible metadata.
- Seeders are idempotent, contain no production secrets, and are not test-fixture substitutes.
- Verify both forward migration and rollback. Use `make migrate-check`, which uses an isolated temporary MySQL database and must not destroy the developer database.
- Destructive commands such as `migrate:fresh`, volume removal, or production data changes require explicit user approval unless the user specifically requested that exact operation.

## Factories and tests

- Factories create valid minimal records, generate unique values suitable for parallel/repeated tests, and avoid silently creating unrelated aggregates. Add small named states for meaningful variants.
- Unit tests live beside their module/Core service when scoped there. Module HTTP/database behavior uses Feature tests. Root `tests/` is for platform-wide behavior.
- Use PHPUnit with Laravel's existing `Tests\TestCase`, `RefreshDatabase` for database tests, factories for data, and fakes/mocks at external boundaries.
- Every behavioral change needs happy-path and relevant failure/authorization/isolation coverage. Migration changes need direct schema/constraint/rollback coverage when practical.
- Run the smallest relevant test path first: `docker compose exec app php artisan test <path>`. Then run `make test` before handoff unless scope or environment makes it disproportionate.
- Tests must not depend on execution order, external networks, wall-clock races, or a real tenant database.

## Composer and static analysis

- Keep PSR-4 mappings aligned with physical case-sensitive paths. Modify Composer autoload only when the existing root mappings do not cover the class.
- Do not run `composer update` or alter locked dependency versions unless explicitly requested. Prefer locked installation through the `init` service.
- Run `composer validate --strict` after Composer changes and `composer dump-autoload` only when autoload metadata changed.
- Run `make analyse`, which checks changed production PHP paths. Use `ANALYSE_ALL=1 make analyse` only for an explicit repository-wide audit. Existing PHPStan debt may be reported but must not be “fixed” outside scope; new or changed production code should not add errors.

## Docker and WSL workflow

- Docker Compose is the canonical development environment. Use `make init`, then `make up`. The app is at `http://localhost:8000`, Mailpit at `http://localhost:8025`, and MySQL host port is `3307`.
- Run PHP, Artisan, PHPUnit, Pint, PHPStan, and Composer through the `app` container via Make targets/scripts unless diagnosing a container bootstrap failure.
- In WSL, run commands from the Linux filesystem checkout, ensure Docker Engine/Compose v2 is reachable, and do not assume Docker Desktop or `host.docker.internal` reaches Windows services.
- Never commit `.env`. The init service creates it only when absent. Do not overwrite an existing `.env` or expose its values.
- `make down` preserves volumes. `docker compose down -v` is destructive and always requires explicit approval.

## Git and pull-request conventions

- Never switch branches, create branches, commit, push, reset, clean, rebase, merge, tag, or open/update a PR unless the user explicitly requests it.
- Never implement directly on `main`. Confirm a task-specific branch before editing; if currently on `main`, obtain authorization to create/switch to an appropriate branch.
- Never use destructive Git commands to handle a dirty worktree. Treat unknown changes as user-owned.
- Branch convention is documented in `docs/development/git-workflow.md`; inspect the current branch rather than assuming it.
- Keep changes focused and commits atomic when commits are authorized. Commit format: `<type>(<scope>): <summary>`.
- Use `.github/PULL_REQUEST_TEMPLATE.md`, target the branch explicitly requested for the task, describe migrations/rollback and verification exactly, disclose risks, and never claim unrun checks passed.
- Stage and review an exact scope before publishing. `scripts/publish-draft-pr.sh --commit-message "..." --pr-title "..."` commits only staged files, pushes the current non-`main` branch, and opens a draft PR into `main`.
- The publishing script never merges. A human normally reviews and merges a draft PR. Codex must never merge automatically unless the user explicitly authorizes that merge in the current task, and must never force or bypass required checks.

## Documentation conventions

- Update documentation with behavior and architecture changes. Module READMEs describe current scope, ownership, dependencies, public contracts, and explicit non-goals.
- Write concise operational docs with copyable repository-root commands. Distinguish verified current behavior from future intent.
- Do not create empty READMEs for folders that do not need to exist. Link to canonical documents rather than duplicating long policy text where possible.

## Security rules and prohibited actions

Codex must never:

- bypass authentication, authorization, organization context, validation, rate limits, audit requirements, or encryption;
- introduce cross-organization data access, insecure direct object references, raw credential storage, or sensitive logging;
- weaken tests or security controls merely to make verification pass;
- call an AI/vendor provider directly from a module when a Core gateway exists;
- add product features, dependencies, routes, APIs, schema, or abstractions outside the requested scope;
- fabricate command output, test totals, file state, source citations, or completion;
- modify generated/vendor files, `.env`, secrets, or production configuration without explicit scope;
- delete data, remove volumes, rewrite Git history, discard user changes, commit, push, or publish externally without explicit authorization.

## Verification checklist

Choose checks proportional to the change, and run `make verify` for a complete handoff when the environment supports it:

- [ ] Scope reviewed; no unrelated files changed.
- [ ] `composer validate --strict` passes.
- [ ] `make migrate-check` passes when migrations/providers changed.
- [ ] Targeted tests pass with exact totals recorded.
- [ ] `make test` passes.
- [ ] `make pint` passes.
- [ ] `make analyse` passes for changed production PHP. Any explicit full-audit findings are reported precisely.
- [ ] `git diff --check` passes.
- [ ] Security, authorization, tenant isolation, rollback, and backward compatibility were considered.
- [ ] No secrets, debug output, placeholders, unintended routes, or generated artifacts were added.

## Final reporting checklist

Every implementation handoff states:

1. Outcome and scope completed.
2. Files created and modified.
3. Verification commands executed, exact test/assertion totals, and results.
4. Migration/rollback and formatting/static-analysis results where applicable.
5. Unresolved issues, skipped checks, environmental blockers, and commands needing manual approval.
6. `git status --short` and `git diff --stat` when files changed.
7. Explicit confirmation that no commit or push occurred unless the user authorized them.

See `docs/development/CODEX_WORKFLOW.md` for the operational workflow and recovery/publishing procedures.
