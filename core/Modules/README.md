# core/Modules

**Purpose**: the Module Manager — the framework future educational/operational modules install, enable, disable, update, and remove against. Implements the lifecycle contract documented in [Module System](../../docs/architecture/module-system.md) and [Module Lifecycle](../../docs/modules/module-lifecycle.md). Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**No modules are shipped in this repository.** This is only the manager they will plug into.

**Responsibilities**:
- `Infrastructure/Models/ModuleRegistration` — one row per module known to this installation (`modules` database table — distinct from the `/modules` folder on disk), tracking status, declared dependencies, supported tenant types, and which tenants currently have it enabled.
- `Application/ModuleManager::discover()` — scans `config('modules.path')` for `module.json` manifests (see [Module Manifest](../../docs/architecture/module-system.md#module-manifest-modulejson)); found-but-not-installed modules are reported, never auto-installed.
- `install()/enable()/disable()/remove()` — each is audited via `Core\AuditLogs` and fires a corresponding event (`ModuleInstalled`, `ModuleEnabled`, `ModuleDisabled`) for other services/modules to react to.

**Allowed dependencies**: `Core\AuditLogs`. Never a specific module — this service knows nothing about any individual module's contents, only the manifest contract.

**Routes**: `GET/POST /api/v1/modules`, `POST /api/v1/modules/{name}/enable`, `POST /api/v1/modules/{name}/disable`, `DELETE /api/v1/modules/{name}` — all gated by `core.modules.manage`.

**Future usage**: running a module's own migrations on install/enable, and tenant-type-aware enable validation against `Core\Tenancy`, are the next increments once the first real module exists to install.
