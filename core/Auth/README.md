# core/Auth

**Purpose**: platform authentication — login, logout, password reset, email verification, and account-status enforcement. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Application/AuthService` — shared credential and account-state validation, API login token issuance, login/logout auditing, password reset, and bulk token revocation. The web-session controller reuses `authenticate()` and never creates an API token.
- Sanctum-based bearer tokens for the API (see [API Authentication](../../docs/api/authentication.md)); session guard reserved for Blade.
- `Http/Controllers/Api/V1` — Login, Logout, ForgotPassword, ResetPassword, EmailVerification controllers, all thin per [Clean Architecture](../../docs/architecture/clean-architecture.md).
- `Http/Middleware/CheckAccountLocked` — defence-in-depth guard so a token issued before a lock/suspend can't keep working.
- `Listeners/RevokeTokensOnAccountLock` — proactively revokes tokens when `Core\Users` fires `UserLocked`/`UserSuspended`.
- Password reset uses Laravel's built-in password broker (`config/auth.php`); email verification uses the `MustVerifyEmail` contract already on `Core\Users\Infrastructure\Models\User`.

**Allowed dependencies**: `Core\Users` (the user model and `UserService` for lockout bookkeeping), `Core\AuditLogs` (every auth event is audited). Never a module.

**Routes**: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `POST /api/v1/auth/forgot-password`, `POST /api/v1/auth/reset-password`, `POST /api/v1/auth/email/verify/{id}/{hash}`, `POST /api/v1/auth/email/resend`.

**Web entry**: `GET /login`, `POST /login`, and CSRF-protected `POST /logout` use Laravel's encrypted server-side session guard. Successful login regenerates the session; logout invalidates it and regenerates the CSRF token. Destination resolution uses platform permissions and active organization memberships. Eligible organization users reach the permission-protected `GET /dashboard`; multiple memberships require trusted server-side selection first. Users without usable organization access receive a factual access page.

**Future usage**: 2FA (TOTP) enforcement per [Security Policies](../../docs/security/policies.md) and device trust are data-model-ready (see `Core\Users`) but not yet enforced in the login flow — planned for a later v1.x iteration.
