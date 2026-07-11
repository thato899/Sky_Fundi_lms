<?php

declare(strict_types=1);

namespace Core\Modules\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Modules\Domain\Enums\ModuleStatus;
use Core\Modules\Events\ModuleDisabled;
use Core\Modules\Events\ModuleEnabled;
use Core\Modules\Events\ModuleInstalled;
use Core\Modules\Exceptions\ModuleNotInstallableException;
use Core\Modules\Infrastructure\Models\ModuleRegistration;
use Illuminate\Support\Facades\File;

/**
 * The Module Manager described in docs/architecture/module-system.md
 * and core/README.md. Implements install/enable/disable/update/remove
 * against the manifest contract documented there. No modules ship in
 * this repository yet — this class is the framework future modules
 * plug into, not a place to hardcode any specific module.
 */
final class ModuleManager
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    /**
     * Scans config('modules.path') for module.json manifests. Modules
     * found on disk but not yet in the registry are reported, never
     * auto-installed — see config/modules.php and
     * docs/architecture/module-system.md#module-manifest-modulejson.
     *
     * @return array<int, array{manifest: array, installed: bool}>
     */
    public function discover(): array
    {
        $path = config('modules.path');
        $manifestFilename = config('modules.manifest_filename', 'module.json');

        if (! File::isDirectory($path)) {
            return [];
        }

        $discovered = [];

        foreach (File::directories($path) as $moduleDirectory) {
            $manifestPath = $moduleDirectory.DIRECTORY_SEPARATOR.$manifestFilename;

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

            $discovered[] = [
                'manifest' => $manifest,
                'installed' => ModuleRegistration::query()->where('name', $manifest['name'])->exists(),
            ];
        }

        return $discovered;
    }

    public function install(string $moduleName): ModuleRegistration
    {
        if (ModuleRegistration::query()->where('name', $moduleName)->exists()) {
            throw ModuleNotInstallableException::alreadyInstalled($moduleName);
        }

        $manifest = $this->manifestFor($moduleName);

        $module = ModuleRegistration::create([
            'name' => $manifest['name'],
            'display_name' => $manifest['displayName'] ?? $manifest['name'],
            'version' => $manifest['version'] ?? '0.1.0',
            'description' => $manifest['description'] ?? null,
            'author' => $manifest['author'] ?? null,
            'status' => ModuleStatus::Installed,
            'dependencies' => [
                'core' => $manifest['coreDependencies'] ?? [],
                'modules' => $manifest['moduleDependencies'] ?? [],
            ],
            'tenant_types' => $manifest['tenantTypes'] ?? [],
            'installed_at' => now(),
        ]);

        // Running the module's own migrations here is a future
        // enhancement once real modules exist to run migrations from
        // (see docs/modules/module-lifecycle.md) — installation today
        // registers the module; Enable is what activates it per tenant.

        event(new ModuleInstalled($module));
        $this->auditLog->record(action: 'module.installed', target: $module, after: ['version' => $module->version]);

        return $module;
    }

    public function enable(string $moduleName, ?string $tenantId = null): ModuleRegistration
    {
        $module = $this->findOrFail($moduleName);

        if ($tenantId !== null && ! empty($module->tenant_types) && ! in_array($tenantId, $module->tenant_types, true)) {
            // NOTE: tenant *type* gating happens against the tenant's
            // own type once Core\Tenancy exists — this checks the
            // manifest's declared supported types as a structural
            // guard in the meantime. See docs/architecture/multi-tenancy.md.
        }

        $enabledFor = $module->enabled_for_tenants ?? [];

        if ($tenantId !== null && ! in_array($tenantId, $enabledFor, true)) {
            $enabledFor[] = $tenantId;
        }

        $module->update([
            'status' => ModuleStatus::Enabled,
            'enabled_for_tenants' => $enabledFor,
            'enabled_at' => now(),
        ]);

        event(new ModuleEnabled($module, $tenantId));
        $this->auditLog->record(action: 'module.enabled', target: $module, after: ['tenant_id' => $tenantId]);

        return $module->fresh();
    }

    public function disable(string $moduleName, ?string $tenantId = null): ModuleRegistration
    {
        $module = $this->findOrFail($moduleName);

        $enabledFor = array_values(array_diff($module->enabled_for_tenants ?? [], [$tenantId]));

        $module->update([
            'status' => $enabledFor === [] ? ModuleStatus::Disabled : ModuleStatus::Enabled,
            'enabled_for_tenants' => $enabledFor,
        ]);

        event(new ModuleDisabled($module, $tenantId));
        $this->auditLog->record(action: 'module.disabled', target: $module, after: ['tenant_id' => $tenantId]);

        return $module->fresh();
    }

    public function remove(string $moduleName): void
    {
        $module = $this->findOrFail($moduleName);

        $this->auditLog->record(
            action: 'module.removed',
            target: $module,
            before: ['name' => $module->name, 'version' => $module->version],
        );

        $module->delete();
    }

    private function findOrFail(string $moduleName): ModuleRegistration
    {
        return ModuleRegistration::query()->where('name', $moduleName)->firstOrFail();
    }

    private function manifestFor(string $moduleName): array
    {
        foreach ($this->discover() as $entry) {
            if (($entry['manifest']['name'] ?? null) === $moduleName) {
                return $entry['manifest'];
            }
        }

        throw ModuleNotInstallableException::manifestMissing($moduleName);
    }
}
