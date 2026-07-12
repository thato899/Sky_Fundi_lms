# core/Support

**Purpose**: Developer Tools — cross-cutting traits, contracts, and exceptions shared by every Core service, plus a scaffolding generator. Not itself a bounded service; nothing here has business logic or a database table. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Contracts/ProviderInterface` — the shape every provider-registry pattern in the platform follows: `name()/isAvailable()`. `Core\Mail\Contracts\MailProviderInterface` extends it directly; `Core\AIGateway\Contracts\AIProviderInterface` and `Core\Storage\Contracts\FileStorageInterface` predate it and define the same two methods independently rather than retrofitting an `extends` onto an already-shipped public contract — new provider-style integrations should extend this interface directly instead.
- `Contracts/Auditable` — implement this on a domain event to get it recorded to the audit trail automatically via `Core\AuditLogs\Listeners\AuditableEventSubscriber`, with no explicit `AuditLogService::record()` call needed at the call site. See that interface's own docblock for the full rationale, including which four Core services (Auth, Users, RBAC, Settings/Branding, Modules) intentionally predate and don't use this pattern.
- `Traits/HasUuidPrimaryKey` — the `HasUuids` + non-incrementing-key boilerplate every UUID-keyed model in the platform repeats, in one place.
- `Traits/HasMetadata` — a `metadata` JSON column accessor/mutator pattern used by models that need arbitrary, ad hoc key-value storage without a schema change (e.g. `Core\Deployment\DeploymentProfile`).
- `Exceptions/DomainException` — a base class for business-rule violations (as opposed to infrastructure failures), so `catch (Core\Support\Exceptions\DomainException)` can distinguish the two categories at a call site that wants to.
- `Exceptions/ProviderNotAvailableException` — the shared "this provider/driver isn't available" exception used by any service following the placeholder-provider pattern (mirrors `Core\AIGateway`'s own, provider-family-agnostic).
- `Console/MakeCoreServiceCommand` (`php artisan make:core-service {Name}`) — scaffolds a new `core/<Name>` folder with the standard `Application/`, `Infrastructure/`, `Http/`, `Providers/`, `routes/`, `database/migrations/` layout and a starter `README.md`, so a new Core service starts from the platform's actual established shape instead of a blank folder.

**Allowed dependencies**: none — this is the one Core "service" every other Core service is allowed to depend on freely, since it has no state and no business rules of its own.
