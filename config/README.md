# /config

**Purpose**: platform-level Laravel configuration files.

**Responsibilities**: standard framework config (`app.php`, `database.php`, `queue.php`, etc.) plus Core-service-level config where it doesn't clearly belong inside a specific `core/<Service>` folder (e.g. AI Gateway provider routing defaults — see [AI Gateway](../docs/ai/ai-gateway.md)). Module-specific config lives inside that module's own `config/<module>.php` (see [Module Anatomy](../docs/architecture/module-system.md#module-anatomy)), not here.

**Allowed dependencies**: reads from environment variables only (via `env()`); application code elsewhere reads from these config files via `config()`, never `env()` directly — see [Coding Standards](../docs/development/coding-standards.md#laravel-conventions).

**Future usage**: populated as the Laravel application skeleton and each Core service are implemented.
