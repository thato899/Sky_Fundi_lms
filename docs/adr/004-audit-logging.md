# ADR-004: Audit logging

Status: Accepted

## Decision

Capture domain events implementing `Auditable` with a central subscriber and use `AuditLogService` for sensitive mutations without such events. Expose audit logs read-only.

## Consequences

Audit behavior is consistent and decoupled, but each new workflow must deliberately emit an auditable event or record an explicit entry and must exclude secrets/personal payloads.
