# /core

The Sky Fundi Platform Core — infrastructure and cross-cutting services every module relies on.

**Purpose**: provide the stable, non-educational foundation the whole platform is built on: identity, access control, tenant/platform administration, and shared infrastructure concerns.

**Responsibilities**: Core contains, and only contains:

- **Auth** — authentication, sessions/tokens, 2FA
- **RBAC** — roles, permissions, policy enforcement (see [RBAC](../docs/security/rbac.md))
- **Users** — user identity, profile, tenant membership
- **Branding** — tenant-level branding (logo, theme)
- **Settings** — platform and tenant-level configuration
- **Notifications** — provider-agnostic notification dispatch (email, push, SMS)
- **AuditLogs** — immutable audit trail (see [Security](../docs/security/README.md))
- **Storage** — abstracted file storage
- **Billing** — tenant billing/subscription
- **Licensing** — module entitlement per tenant
- **Api** — shared API infrastructure (routing conventions, request/response shaping, versioning support — see [API Conventions](../docs/api/conventions.md))
- **AIGateway** — the single AI abstraction layer (see [AI Gateway](../docs/ai/ai-gateway.md))
- **Logging** — structured application logging
- **FileManagement** — shared file handling utilities beyond raw storage (e.g. virus scanning hooks, upload validation)

**Rule**: nothing academic or institution-specific lives here. If a concept is specific to "a School" or "a Subject" or "an Assessment," it belongs in a module, not Core. See [Module System](../docs/architecture/module-system.md).

**Allowed dependencies**: none upward — Core never depends on a module. Core services depend only on each other where explicitly documented (e.g. Auth depends on Users), and on the Domain/Application/Infrastructure layering described in [Clean Architecture](../docs/architecture/clean-architecture.md).

**Future usage**: each subfolder below (`Auth/`, `RBAC/`, `Users/`, etc.) will hold that service's own implementation following the same four-layer structure as modules, plus its own `README.md`. Nothing is implemented yet — this is the foundation stage.
