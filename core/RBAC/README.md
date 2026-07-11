# core/RBAC

**Purpose**: permission-first role-based access control — the single source of truth for every authorization decision on the platform. Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**Responsibilities**:
- `Infrastructure/Models/{Role,Permission}` — data-driven roles and permissions; no role's behaviour is ever hardcoded in application code.
- `Application/PermissionService` — resolves whether a user holds a permission (via role membership or a direct override), registered against Laravel's `Gate::before()` so every `$user->can(...)` / `@can` / route-level check resolves consistently. Cached per user for 5 minutes, invalidated on role/permission change.
- `Application/RoleService` — role CRUD, permission syncing, assigning/revoking roles, all audited.
- `Http/Middleware/EnsurePermission` — route middleware: `->middleware('permission:core.users.manage')`.
- `database/seeders` (at the repository root, `database/seeders/PermissionSeeder.php` and `RoleSeeder.php`) seed the Core permission catalog from `config/permissions.php` and the four system roles (Super Admin, Platform Administrator, Support, Developer) — no educational roles.

**Allowed dependencies**: `Core\Users` (roles/permissions attach to users), `Core\AuditLogs`. Never a module — module permissions are registered separately from each module's manifest, per [Module System](../../docs/architecture/module-system.md#module-manifest-modulejson).

**Routes**: `GET/POST /api/v1/roles`, `PUT /api/v1/roles/{role}/permissions`, `DELETE /api/v1/roles/{role}`, `GET /api/v1/permissions`, `POST/DELETE /api/v1/users/{user}/roles[/{role}]`.
