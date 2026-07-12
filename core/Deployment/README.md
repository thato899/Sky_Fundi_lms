# core/Deployment

**Purpose**: structured, database-driven deployment configuration — single server, dedicated server, cloud, Docker, or (future) Kubernetes — for the platform or a future Organization. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**No deployment automation.** This service records intended configuration (database, storage, branding, environment, AI provider, module set, administrator) for a later, separate provisioning tool to read. It never provisions infrastructure, runs remote migrations, or calls a cloud provider API.

**Responsibilities**:
- `Infrastructure/Models/DeploymentProfile` — `subject_type`/`subject_id` are a nullable polymorphic pair (null = the platform's own profile), matching the pattern used by `Core\Licensing` and `Core\Deployment` for forward-compatibility with a future Organization/tenant model — see [Multi-Tenancy](../../docs/architecture/multi-tenancy.md).
- `Domain/Enums/DeploymentStrategy` — Single Server, Dedicated Server, Cloud, Docker, Kubernetes.
- `Application/DeploymentProfileService` — create/update, both audited automatically via `Auditable` events.

**Allowed dependencies**: `Core\AuditLogs`, `Core\Users` (administrator reference). Never a module.

**Routes**: `GET/POST /api/v1/deployment-profiles`, `GET/PUT /api/v1/deployment-profiles/{deploymentProfile}` — gated by `core.deployment.manage`.
