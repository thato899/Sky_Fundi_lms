# core/Events

**Purpose**: documents the platform's event taxonomy. There is no new dispatch mechanism here — Laravel's own event dispatcher (`event()`, `Event::listen()`, `Event::subscribe()`) is used directly everywhere, per [Module System — Cross-Module Communication](../../docs/architecture/module-system.md#cross-module-communication) and the existing `Core\Support\Contracts\Auditable` pattern (see [`core/AuditLogs`](../AuditLogs/README.md)). This folder exists so the categories below have one place to be defined, instead of being implicit.

## Categories

| Category | Examples | Lives in |
|---|---|---|
| **Domain events** | `UserCreated`, `RoleAssigned`, `LicenseActivated`, `SubscriptionRenewed` | Each owning Core service's own `Events/` folder |
| **Application events** | `SettingsUpdated`, `BrandingChanged`, `FeatureFlagToggled` | Same — state changes to platform configuration |
| **Queue events** | Laravel's own `JobProcessing`/`JobFailed` (not wrapped — see [`core/Queue`](../Queue/README.md) for the queue *naming* layer instead) | Framework-provided |
| **Notification events** | Laravel's own `NotificationSent`/`NotificationFailed` (not wrapped) | Framework-provided |
| **Audit events** | Any event implementing `Core\Support\Contracts\Auditable` | Automatically captured by `Core\AuditLogs\Listeners\AuditableEventSubscriber` — see that class's docblock |
| **Module events** | `ModuleInstalled`, `ModuleEnabled`, `ModuleDisabled` | [`core/Modules`](../Modules/README.md) |
| **AI events** | none dispatched yet — `Core\AIGateway\Application\AIManager` logs via `Core\Logging`'s `ai` channel and records an `AnalyticsMetric::AIUsage` event instead of a domain event, since every AI call isn't a "state change" worth an audit entry | [`core/AIGateway`](../AIGateway/README.md) |

## The one rule that matters

Any event that represents a **security- or state-sensitive action** should implement `Core\Support\Contracts\Auditable` (see that interface's docblock for the full rationale and the four Core services intentionally excluded from this pattern). Everything else is a plain Laravel event — no interface required.

## Cross-service listening

A service subscribing to another service's event (e.g. `Core\Security\Listeners\DetectNewDeviceLogin` listening to `Core\Auth\Events\UserLoggedIn`) registers that listener in its **own** `Providers\*ServiceProvider::boot()` via `Event::listen()` — never inside the event-owning service. This keeps the dependency direction visible: the listening service imports the event class, never the reverse. See `Core\Security\Providers\SecurityServiceProvider` for the pattern.
