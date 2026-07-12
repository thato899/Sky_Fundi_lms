# core/Security

**Purpose**: trusted devices, IP allow/deny lists, active-session management, and non-blocking suspicious-login detection — on top of, never duplicating, `Core\Auth`'s existing account lockout and `Core\RBAC`'s permission enforcement. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/TrustedDevice` — a user-scoped `(ip + user-agent)` fingerprint the user has explicitly chosen to trust ("remember this device"). Opt-in, not automatic.
- `Infrastructure/Models/IpRestriction` — allow/deny CIDR entries, scoped `platform` today (`organization` scope is data-model-ready for when `Core\Organizations`/tenancy exists — see [Multi-Tenancy](../../docs/architecture/multi-tenancy.md)).
- `Application/TrustedDeviceService` — fingerprint, trust, revoke, list.
- `Application/IpRestrictionService::isAllowed()` — deny always wins; if any allow entries exist for a scope the IP must match one (allowlist mode), otherwise open by default.
- `Application/SessionSecurityService` — lists/revokes a user's active Sanctum tokens (deliberately reads `Core\Auth`'s existing `personal_access_tokens` rather than a parallel session table).
- `Listeners/DetectNewDeviceLogin` — subscribes to `Core\Auth`'s existing `UserLoggedIn` event (no changes to `Core\Auth` itself) and raises a non-blocking `Events\SecurityAlertRaised` (`Auditable`) when the login's IP/user-agent isn't a trusted device yet. **Never blocks the login** — see "Future MFA" below.
- `Http/Middleware/EnforceIpRestriction` — opt-in per route (not global) via `->middleware('ip-restriction')`.

**Brute force / failed-login protection**: already implemented in `Core\Users`' account-lockout mechanism (`failed_login_attempts`/`locked_at`, see [Users](../Users/README.md)) — Security Centre does not duplicate it; `SecurityAlertRaised` complements it as a softer, non-blocking signal for anomalies that don't warrant a lockout.

**Allowed dependencies**: `Core\Auth` (listens to its event only), `Core\Users`, `Core\AuditLogs`. Never a module.

**Routes**: `GET/POST/DELETE /api/v1/security/trusted-devices[/...]` (self-service), `GET/DELETE /api/v1/security/sessions[/...]` (self-service), `GET/POST/DELETE /api/v1/security/ip-restrictions[/...]` (permission `core.security.manage`).

**Future MFA**: `SecurityAlertRaised` is the natural hook a TOTP challenge-on-new-device flow would subscribe to — not implemented here, consistent with `Core\Auth`'s "2FA-ready" note.
