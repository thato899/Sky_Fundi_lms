# core/Modules

**Purpose**: the Module Manager — the framework future educational/operational modules install, enable, disable, update, and remove against. Implements the lifecycle contract documented in [Module System](../../docs/architecture/module-system.md) and [Module Lifecycle](../../docs/modules/module-lifecycle.md). Part of the Sky Fundi Platform Core, per [/core/README.md](../README.md).

**One module is shipped in this repository** — [`modules/Academics`](../../modules/Academics/README.md), the reusable academic engine. It is the first real exercise of this manager.

**Responsibilities**:
- `Infrastructure/Models/ModuleRegistration` — one row per module known to this installation (`modules` database table — distinct from the `/modules` folder on disk), tracking status, declared dependencies, supported tenant types, and which tenants currently have it enabled.
- `Application/ModuleManager::discover()` — scans `config('modules.path')` for `module.json` manifests (see [Module Manifest](../../docs/architecture/module-system.md#module-manifest-modulejson)); found-but-not-installed modules are reported, never auto-installed.
- `install()/enable()/disable()/remove()` — each is audited via `Core\AuditLogs` and fires a corresponding event (`ModuleInstalled`, `ModuleEnabled`, `ModuleDisabled`) for other services/modules to react to.

**Registry vs. runtime — an honest gap surfaced by building the first module**: this registry tracks a module's install/enable state as *data*. It does not currently control whether a module's Laravel code actually runs — that's a separate mechanism, each module's own `Providers\<Name>ServiceProvider` registered in `bootstrap/providers.php`, exactly like a Core service (see [`modules/Academics/README.md`](../../modules/Academics/README.md#module-bootstrapping--registry-vs-runtime) for the full explanation). Reconciling the two — so `enable()`/`disable()` actually gate route/migration registration — is future work; today a module with a registered provider is unconditionally active, regardless of what this registry says.

**Allowed dependencies**: `Core\AuditLogs`. Never a specific module — this service knows nothing about any individual module's contents, only the manifest contract.

**Routes**: `GET/POST /api/v1/modules`, `POST /api/v1/modules/{name}/enable`, `POST /api/v1/modules/{name}/disable`, `DELETE /api/v1/modules/{name}` — all gated by `core.modules.manage`.

**Future usage**: running a module's own migrations on install/enable (today, migrations load unconditionally via the module's ServiceProvider — see above), registry/runtime reconciliation, and tenant-type-aware enable validation against a future Organisation/Tenancy concept (see [Multi-Tenancy](../../docs/architecture/multi-tenancy.md)) are the next increments.

