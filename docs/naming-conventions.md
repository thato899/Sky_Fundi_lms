# Naming Conventions

Platform-wide naming rules. Layer-specific detail (database, API) lives in their own docs and takes precedence for that layer if anything here seems to conflict.

## PHP

- Classes: `PascalCase` (`EnrollLearnerService`, `Subject`, `AttendanceRegister`).
- Interfaces: `PascalCase`, typically suffixed `Interface` when a concrete implementation of the same concept also exists (`SubjectRepositoryInterface` / `EloquentSubjectRepository`).
- Methods/variables: `camelCase`.
- Constants/enum cases: `SCREAMING_SNAKE_CASE` for class constants; PHP 8.1+ enums use `PascalCase` case names per modern PHP convention.

## Namespaces

- Core: `Core\<Service>\<Layer>\...` (e.g. `Core\Auth\Domain\User`).
- Modules: `Modules\<ModuleName>\<Layer>\...` (e.g. `Modules\Academics\Application\EnrollLearnerService`).
- Exact composer/autoload root to be finalized when the Laravel application skeleton is committed; this pattern is the fixed target.

## Files and Folders

- Module folder names: `PascalCase`, matching the module's manifest `name` in PascalCase (`modules/Academics`, manifest `name: "academics"`).
- Everything else on disk (config files, migration files, Blade view files, route files): standard Laravel/`snake_case`-or-`kebab-case` conventions as applicable per file type.

## Database

See [Database Conventions](database/conventions.md) — table/column naming is documented there in full, not duplicated here.

## API

See [API Conventions](api/conventions.md) — URL/field naming documented there.

## Permissions

`<module>.<resource>.<action>`, all lower-`kebab-case` segments, e.g. `attendance.registers.close`, `core.billing.view`. See [RBAC](security/rbac.md).

## Events

`<module>.<entity>.<past-tense-action>`, e.g. `academics.subject.created`, `attendance.register.closed`. See [Module System — Cross-Module Communication](architecture/module-system.md#cross-module-communication).

## Git

Branches: `feature|fix|docs/<area>/<short-kebab-description>`. See [Git Workflow](development/git-workflow.md).
