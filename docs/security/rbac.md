# RBAC — Role-Based Access Control

Attendance declares `attendance.view`, `attendance.create`, `attendance.record`, `attendance.update`, `attendance.finalize`, `attendance.reopen`, `attendance.cancel`, `attendance.export`, and `attendance.view_reports`. Reopening is deliberately separate and requires a reason. Learner and Guardian roles receive no administrative attendance permissions.

## Model

- **Users** belong to one or more **Tenants**.
- Within a tenant, a user is assigned one or more **Roles** (e.g. `school-admin`, `teacher`, `tutor`, `parent`, `learner`, `platform-admin`).
- Roles are collections of **Permissions**.
- Permissions are namespaced strings: `<module>.<resource>.<action>`, e.g. `academics.subjects.manage`, `attendance.registers.close`, `core.billing.view`.

## Permission Registration

Core exposes a permission registry. Each module declares its permissions in its manifest (`module.json`, see [Module System](../architecture/module-system.md#module-manifest-modulejson)) and registers them with Core RBAC when enabled for a tenant. Modules never invent ad-hoc authorization checks outside this registry — every gate/policy check resolves to a registered permission.

## Standard Roles (Core-defined, extensible)

| Role | Scope |
|---|---|
| `platform-admin` | Cross-tenant, Sky Fundi operator staff only |
| `tenant-owner` | Full control within one tenant |
| `tenant-admin` | Administrative control within one tenant, short of ownership actions (billing, deletion) |
| Module-specific roles (`teacher`, `tutor`, `parent`, `learner`, etc.) | Defined as modules that need them are built, always scoped to a tenant |

## Enforcement Points

- **API layer**: Form Requests / Policies check permissions before a controller action executes.
- **Service layer**: services that perform sensitive operations re-check authorization rather than trusting the controller alone, where the service could plausibly be invoked from more than one entry point (API, queued job, console command).
- **UI layer**: Blade/React hide actions a user can't perform, but this is a UX convenience only — enforcement always happens server-side.

## Custom/Tenant-Specific Roles

Tenants may define custom roles composed from the permissions available to their enabled modules, without needing new code. This composition capability lives in `core/RBAC` and is documented in detail there once implemented.
