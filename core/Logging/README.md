# core/Logging

**Purpose**: a professional, channel-separated logging strategy — application, AI, security, authentication, and system logs are distinct and independently retained (see `config/logging.php`). Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Application/PlatformLogger` — the wrapper every Core service and future module should use instead of the `Log` facade directly. Guarantees every log line carries a request/correlation id and (where authenticated) an actor id, so any entry can be traced back to the request/user that produced it, per [Logging Strategy](../../docs/development/README.md#logging-strategy).
- Convenience methods per channel: `application()`, `ai()`, `security()`, `authentication()`, `system()`.

**Allowed dependencies**: none. Never a module.

**Future usage**: tenant-id context will be added to the base context once `Core\Tenancy` exists (see [Multi-Tenancy](../../docs/architecture/multi-tenancy.md)).
