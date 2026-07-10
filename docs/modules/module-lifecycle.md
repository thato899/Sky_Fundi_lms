# Module Lifecycle — Operational Detail

Complements the lifecycle states defined in [`../architecture/module-system.md`](../architecture/module-system.md#module-lifecycle). This document describes what happens operationally at each transition, for whoever builds the Core module registry.

## Install

- Module code is present in `/modules/<Name>` (via composer package or direct inclusion, TBD when Core package management is implemented).
- Module's migrations are registered but **not run** until Enabled for at least one tenant.
- Module does not yet appear in any tenant's active feature set.

## Enable (per tenant)

- Module's migrations run against the target tenant's database (or shared DB, tenant-scoped, per the tenant's isolation strategy).
- Module's routes register for that tenant's context.
- Module's declared permissions become assignable via RBAC for that tenant.
- Module's scheduled jobs/queue listeners activate for that tenant.
- An audit log entry is recorded (who enabled it, when, for which tenant).

## Disable (per tenant)

- Routes, permissions, and scheduled jobs deactivate for that tenant.
- Data is retained untouched.
- Other modules' event listeners for this module's events must degrade gracefully (skip, not error).
- Audit log entry recorded.

## Update

- New module version's migrations run (additive by default; destructive migrations require an explicit, documented upgrade note).
- Manifest version bumped.
- Changelog entry expected in the module's own README or a `CHANGELOG.md` inside the module folder.

## Remove

- Requires explicit confirmation and elevated (platform-admin) permission.
- Two-step by default: Disable first, then a separate, audited "Remove" action.
- Data removal is opt-in and irreversible — must be logged with who/when/what was deleted.

## Tenant-Type Gating

A module may declare (via `tenantTypes` in its manifest) that it is not applicable to a given tenant type. The registry should prevent enabling a module for an unsupported tenant type rather than allowing a silent no-op.
