# Security

Security is a Core concern present from day one, not retrofitted. This folder documents the platform's security model.

- [`rbac.md`](rbac.md) — roles, permissions, how modules register their own permissions
- [`policies.md`](policies.md) — password policy, session policy, device trust, IP restrictions, 2FA, encryption, secrets management, API authentication summary

## Audit Logs

Every security-sensitive action (login, permission change, module enable/disable, billing changes, and everything else listed below) is recorded to `core/AuditLogs`. An audit log entry is immutable once written and records: actor, action, target, timestamp, and (where relevant) before/after state. Audit logs are never exposed for editing or deletion through the application layer. Any domain event implementing `Core\Support\Contracts\Auditable` is recorded automatically by `Core\AuditLogs\Listeners\AuditableEventSubscriber` — see that class's docblock — so most Core services don't call `AuditLogService::record()` directly.

## Security Centre — Implementation Status

Device trust, IP restrictions, session management, and non-blocking suspicious-login detection are implemented — see [`core/Security/README.md`](../../core/Security/README.md). Brute-force/lockout protection is implemented in `core/Users` (account-level) — see [`core/Users/README.md`](../../core/Users/README.md) — and is not duplicated by Security Centre. 2FA remains data-model-ready but not enforced in the login flow, per [`core/Auth/README.md`](../../core/Auth/README.md).

## Reporting a Vulnerability

Security issues should not be filed as public GitHub issues. Until a dedicated security contact/process is published, report directly to the repository owner.
