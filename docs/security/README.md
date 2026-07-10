# Security

Security is a Core concern present from day one, not retrofitted. This folder documents the platform's security model.

- [`rbac.md`](rbac.md) — roles, permissions, how modules register their own permissions
- [`policies.md`](policies.md) — password policy, session policy, device trust, IP restrictions, 2FA, encryption, secrets management, API authentication summary

## Audit Logs

Every security-sensitive action (login, permission change, module enable/disable, tenant data access by platform admins, billing changes) is recorded to `core/AuditLogs`. An audit log entry is immutable once written and records: actor, action, target, tenant, timestamp, and (where relevant) before/after state. Audit logs are never exposed for editing or deletion through the application layer.

## Reporting a Vulnerability

Security issues should not be filed as public GitHub issues. Until a dedicated security contact/process is published, report directly to the repository owner.
