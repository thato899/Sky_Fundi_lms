# /core

The Sky Fundi Platform Core — infrastructure and cross-cutting services every module relies on.

**Purpose**: provide the stable, non-educational foundation the whole platform is built on: identity, access control, tenant/platform administration, and shared infrastructure concerns.

**Responsibilities**: Core contains, and only contains:

- **Auth** — authentication, sessions/tokens, 2FA-ready ([implemented](Auth/README.md))
- **RBAC** — roles, permissions, policy enforcement ([implemented](RBAC/README.md); see [RBAC](../docs/security/rbac.md))
- **Users** — user identity, profile, account security ([implemented](Users/README.md))
- **Branding** — platform branding (logo, theme) ([implemented](Branding/README.md))
- **Settings** — database-driven platform configuration ([implemented](Settings/README.md))
- **Notifications** — provider-agnostic notification dispatch (email, database; push/SMS planned) ([implemented](Notifications/README.md))
- **AuditLogs** — immutable audit trail ([implemented](AuditLogs/README.md); see [Security](../docs/security/README.md))
- **Storage** — abstracted file storage ([implemented](Storage/README.md))
- **AIGateway** — the single AI abstraction layer ([implemented](AIGateway/README.md); see [AI Gateway](../docs/ai/ai-gateway.md))
- **Modules** — the module manager/registry ([implemented](Modules/README.md); see [Module System](../docs/architecture/module-system.md)). Added in v1.0 per the note in that document ("The concrete module loader/registry... will live under core/... documented in core/README.md at that time.") — not part of the original 14-service list, but the natural home for it.
- **Logging** — structured, channel-based application logging ([implemented](Logging/README.md))
- **Api** — shared API infrastructure: base controller, response shaping, global exception handling ([implemented](Api/README.md); see [API Conventions](../docs/api/conventions.md))
- **Billing** — tenant billing/subscription (not yet implemented — outside v1.0 Core scope, see [Roadmap](../docs/roadmap.md))
- **Licensing** — module entitlement per tenant (not yet implemented — outside v1.0 Core scope, see [Roadmap](../docs/roadmap.md))
- **FileManagement** — shared file handling utilities beyond raw storage, e.g. upload validation, virus scanning hooks (not yet implemented — `Storage` covers file persistence today)

**Rule**: nothing academic or institution-specific lives here. If a concept is specific to "a School" or "a Subject" or "an Assessment," it belongs in a module, not Core. See [Module System](../docs/architecture/module-system.md).

**Allowed dependencies**: none upward — Core never depends on a module. Core services depend only on each other where explicitly documented (e.g. Auth depends on Users and AuditLogs; RBAC depends on Users; Branding depends on Settings), and on the Domain/Application/Infrastructure layering described in [Clean Architecture](../docs/architecture/clean-architecture.md).

**Status**: v1.0 Core implementation — Authentication, RBAC, Users, Audit Logs, Settings, Branding, Module Manager, Notification framework, Storage abstraction, and the AI Gateway (Ollama + DeepSeek live, OpenAI/Claude/Gemini as registered placeholders) are implemented. No educational features exist anywhere in this repository. Billing, Licensing, and FileManagement remain unimplemented pending later work.
