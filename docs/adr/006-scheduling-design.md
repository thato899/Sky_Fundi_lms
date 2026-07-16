# ADR-006: Scheduling design

Status: Accepted

## Decision

Keep academic periods/calendar in Academics and own rooms, weekly templates, concrete UTC lessons, staff assignment, conflicts, materialization, and immutable change history in Scheduling. Use organization timezones for input/presentation and half-open overlap checks.

## Consequences

Calendar concepts are not duplicated, materialization is bounded/idempotent, and overrides are permissioned/audited. Optimization, public booking, exam generation, and AI scheduling remain out of scope.
