# core/Users

**Purpose**: platform user identity — the one `User` model every other Core service and future module references. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/User` — UUID primary key, soft deletes, profile photo path, timezone, locale, status, failed-login counter, account-lock timestamp, last-login timestamp/IP, password-changed timestamp, Sanctum `HasApiTokens`, `MustVerifyEmail`, `CanResetPassword`.
- `Domain/Enums/UserStatus` — `Active | Suspended | Locked | PendingVerification | Deactivated`.
- `Application/UserService` — create, suspend, reactivate, unlock, record failed/successful logins (including the lockout threshold from `config('services.auth.max_login_attempts')`), and password-expiry detection. Every state change is audited via `Core\AuditLogs` and fires a domain event (`UserCreated`, `UserSuspended`, `UserLocked`) for other services/modules to react to — see `Core\Auth\Listeners\RevokeTokensOnAccountLock` for a consumer.
- `Http/Controllers/Api/V1/UserController` — thin CRUD + suspend/reactivate/unlock actions, all gated by `core.users.view`/`core.users.manage`.

**Allowed dependencies**: `Core\AuditLogs`. Depended on by `Core\Auth`, `Core\RBAC`, `Core\Notifications`, and every future module that needs to reference "a user."

**Routes**: `GET/POST /api/v1/users`, `GET/PATCH/DELETE /api/v1/users/{user}`, `POST /api/v1/users/{user}/{suspend,reactivate,unlock}`.
