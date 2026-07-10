# Environment Variables

## Principle

All configuration that varies by environment or contains a secret lives in `.env`, never hardcoded, and is git-ignored. `.env.example` at the repository root documents every required key with a safe placeholder/empty value, kept in sync as Core and modules introduce new configuration.

## Categories (to be populated as Core is implemented)

| Category | Examples (illustrative) |
|---|---|
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_URL`, `APP_DEBUG` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Redis / Cache / Queue | `REDIS_HOST`, `CACHE_DRIVER`, `QUEUE_CONNECTION` |
| Auth | Token lifetime settings, 2FA defaults (see [Security Policies](security/policies.md)) |
| AI Gateway | Per-provider credentials (e.g. `AI_OPENAI_API_KEY`, `AI_CLAUDE_API_KEY`, `AI_OLLAMA_BASE_URL`) — see [AI Gateway](ai/ai-gateway.md). Provider selection/routing config is tenant-level data, not just env config, once Core's AI Gateway is implemented. |
| Storage | Filesystem driver config for `core/Storage` (local vs cloud object storage) |
| Notifications | Mail/push provider credentials for `core/Notifications` |
| Billing | Payment provider credentials for `core/Billing` |
| Multi-Tenancy | Default isolation strategy for new tenant provisioning (see [Multi-Tenancy](architecture/multi-tenancy.md)) |

## Rules

1. Never commit a real value for a secret-bearing key, in any branch, at any time — including in tests or seeders.
2. Every new key added to `.env.example` must include a one-line comment explaining what it's for and, where relevant, a link to the doc that governs it.
3. Config files (`config/*.php`) read from `env()`; application code reads from `config()`, never `env()` directly (see [Coding Standards](development/coding-standards.md#laravel-conventions)).
4. Production values are supplied via the hosting platform's secret management, not a plain `.env` file on a shared server — see [Security — Secrets Management](security/policies.md#secrets-management).
