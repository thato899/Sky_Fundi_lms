# /core

The Sky Fundi Platform Core — infrastructure and cross-cutting services every module relies on.

**Purpose**: provide the stable, non-educational foundation the whole platform is built on: identity, access control, platform administration, and shared infrastructure concerns.

**Responsibilities**: Core contains, and only contains, the following services. Nothing academic or institution-specific lives here — if a concept is specific to "a School" or "a Subject" or "an Assessment," it belongs in a module, not Core. See [Module System](../docs/architecture/module-system.md).

### Identity & Access
- **Auth** — authentication, sessions/tokens, 2FA-ready ([Auth/README.md](Auth/README.md))
- **RBAC** — roles, permissions, policy enforcement ([RBAC/README.md](RBAC/README.md); see [RBAC](../docs/security/rbac.md))
- **Users** — user identity, profile, account security ([Users/README.md](Users/README.md))
- **Security** — trusted devices, IP allow/deny lists, session management, non-blocking suspicious-login detection ([Security/README.md](Security/README.md))

### Platform Configuration
- **Settings** — database-driven platform configuration ([Settings/README.md](Settings/README.md))
- **Branding** — platform branding, stored as a Settings group ([Branding/README.md](Branding/README.md))
- **FeatureFlags** — platform/organization/user/module-scoped capability toggles ([FeatureFlags/README.md](FeatureFlags/README.md))

### Commercial
- **Licensing** — enterprise license tiers, entitlements, and lifecycle (Trial → Starter → Professional → Enterprise → Government → Custom) ([Licensing/README.md](Licensing/README.md))
- **Subscriptions** — billing cycles, grace periods, renewal/suspension/reactivation, usage tracking ([Subscriptions/README.md](Subscriptions/README.md))

### Deployment & Operations
- **Deployment** — deployment profile metadata (single server, dedicated server, cloud, Docker, future Kubernetes) — configuration storage only, no automation ([Deployment/README.md](Deployment/README.md))
- **Installer** — first-run installation workflow (`php artisan platform:install`) ([Installer/README.md](Installer/README.md))
- **Health** — system health checks (database, cache, queue, storage, mail, AI provider) ([Health/README.md](Health/README.md))
- **Backup** — database/storage/configuration/logs backup (restore is future work) ([Backup/README.md](Backup/README.md))
- **Scheduler** — wires the platform's recurring maintenance commands into Laravel's scheduler ([Scheduler/README.md](Scheduler/README.md))
- **Queue** — the platform's named-queue taxonomy ([Queue/README.md](Queue/README.md))

### Infrastructure Abstractions
- **Storage** — abstracted file storage: Local and S3 live, Azure/GCS registered placeholders ([Storage/README.md](Storage/README.md))
- **Mail** — mail provider selection on top of Laravel Mail: SMTP/SES/Mailgun live, Microsoft 365/Google Workspace registered placeholders ([Mail/README.md](Mail/README.md))
- **AIGateway** — the single AI abstraction layer: Ollama/DeepSeek live, OpenAI/Claude/Gemini registered placeholders ([AIGateway/README.md](AIGateway/README.md); see [AI Gateway](../docs/ai/ai-gateway.md))
- **Notifications** — provider-agnostic notification dispatch: database/mail live, SMS/WhatsApp/push registered placeholders ([Notifications/README.md](Notifications/README.md))

### Cross-Cutting
- **Api** — shared API infrastructure: base controller, response shaping, rate limiting, request logging, query helpers, global exception handling ([Api/README.md](Api/README.md); see [API Conventions](../docs/api/conventions.md))
- **AuditLogs** — immutable, searchable audit trail, automatically populated for any event implementing `Core\Support\Contracts\Auditable` ([AuditLogs/README.md](AuditLogs/README.md); see [Security](../docs/security/README.md))
- **Analytics** — infrastructure-only platform analytics event stream; no dashboards ([Analytics/README.md](Analytics/README.md))
- **Logging** — structured, channel-based application logging ([Logging/README.md](Logging/README.md))
- **Events** — documents the platform's event taxonomy (no new dispatch mechanism — Laravel's own event system is used directly) ([Events/README.md](Events/README.md))
- **Support** — Developer Tools: shared traits, contracts, exceptions, and the `make:core-service` scaffolding generator ([Support/README.md](Support/README.md))

### Module System
- **Modules** — the module manager/registry ([Modules/README.md](Modules/README.md); see [Module System](../docs/architecture/module-system.md)). Runtime module providers are explicitly registered in `bootstrap/providers.php`.

### Not Yet Implemented
- **FileManagement** — shared upload validation/virus-scanning utilities beyond raw storage (`Storage` covers file persistence today).

**Allowed dependencies**: none upward — Core never depends on a module. Core services depend only on each other where explicitly documented in each service's own README, and on the Domain/Application/Infrastructure layering described in [Clean Architecture](../docs/architecture/clean-architecture.md).

**Status**: v1.0 Core (Auth, RBAC, Users, Identity, Settings, Branding, Notifications, AuditLogs, Storage, AIGateway, Modules, Api, Logging) plus the Enterprise Infrastructure Layer (Licensing, Subscriptions, Deployment, Mail, Health, FeatureFlags, Analytics, Security, Backup, Scheduler, Installer, Queue, Events, Support) are implemented. Educational features live in `modules/`; `FileManagement` remains unimplemented beyond the Storage abstraction.
