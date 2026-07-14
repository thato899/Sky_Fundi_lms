<?php

declare(strict_types=1);

namespace Modules\Organizations\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Branding\Application\BrandingService;
use Core\Modules\Application\ModuleManager;
use Illuminate\Support\Facades\DB;
use Modules\Organizations\Domain\Enums\OrganizationStatus;
use Modules\Organizations\Events\OrganizationActivated;
use Modules\Organizations\Events\OrganizationAdministratorAssigned;
use Modules\Organizations\Events\OrganizationAIProviderChanged;
use Modules\Organizations\Events\OrganizationBrandingChanged;
use Modules\Organizations\Events\OrganizationCreated;
use Modules\Organizations\Events\OrganizationDeleted;
use Modules\Organizations\Events\OrganizationModuleDisabled;
use Modules\Organizations\Events\OrganizationModuleEnabled;
use Modules\Organizations\Events\OrganizationSettingsUpdated;
use Modules\Organizations\Events\OrganizationSuspended;
use Modules\Organizations\Events\OrganizationUpdated;
use Modules\Organizations\Infrastructure\Models\Organization;
use Modules\Organizations\Infrastructure\Models\OrganizationAiConfiguration;
use Modules\Organizations\Infrastructure\Models\OrganizationModule;
use Modules\Organizations\Infrastructure\Models\OrganizationSetting;

/** Coordinates tenant-owned state while delegating shared concerns to Core services. */
final class OrganizationService
{
    public function __construct(private readonly AuditLogService $auditLog, private readonly ModuleManager $modules) {}

    public function create(array $attributes, ?string $actorId): Organization
    {
        $organization = Organization::create([...$attributes, 'status' => $attributes['status'] ?? OrganizationStatus::Active, 'created_by' => $actorId, 'updated_by' => $actorId]);
        event(new OrganizationCreated($organization));

        return $organization;
    }

    public function update(Organization $organization, array $attributes, ?string $actorId): Organization
    {
        $before = $organization->only(array_keys($attributes));
        $organization->update([...$attributes, 'updated_by' => $actorId]);
        event(new OrganizationUpdated($organization));
        $this->auditLog->record(action: 'organizations.updated', target: $organization, before: $before, after: $attributes);

        return $organization->fresh();
    }

    public function suspend(Organization $organization): Organization
    {
        $organization->update(['status' => OrganizationStatus::Suspended]);
        event(new OrganizationSuspended($organization));

        return $organization->fresh();
    }

    public function activate(Organization $organization): Organization
    {
        $organization->update(['status' => OrganizationStatus::Active]);
        event(new OrganizationActivated($organization));

        return $organization->fresh();
    }

    public function delete(Organization $organization): void
    {
        $organization->delete();
        event(new OrganizationDeleted($organization));
    }

    public function assignAdministrator(Organization $organization, string $userId, ?string $actorId): void
    {
        $organization->administrators()->syncWithoutDetaching([$userId => ['assigned_by' => $actorId, 'assigned_at' => now()]]);
        event(new OrganizationAdministratorAssigned($organization));
    }

    public function updateSettings(Organization $organization, array $settings): array
    {
        foreach ($settings as $group => $values) {
            foreach ($values as $key => $value) {
                OrganizationSetting::query()->updateOrCreate(['organization_id' => $organization->id, 'group' => $group, 'key' => $key], ['value' => $value]);
            }
        }
        event(new OrganizationSettingsUpdated($organization));

        return $this->settings($organization);
    }

    /** Branding values inherit the platform's defaults when no tenant override exists. */
    public function branding(Organization $organization): array
    {
        $platform = app(BrandingService::class)->current();

        return array_replace($platform, $this->settings($organization)['branding'] ?? []);
    }

    public function updateBranding(Organization $organization, array $branding): array
    {
        $this->updateSettings($organization, ['branding' => $branding]);
        event(new OrganizationBrandingChanged($organization));

        return $this->branding($organization);
    }

    public function configureAi(Organization $organization, array $values): OrganizationAiConfiguration
    {
        $configuration = OrganizationAiConfiguration::query()->updateOrCreate(['organization_id' => $organization->id], $values);
        event(new OrganizationAIProviderChanged($organization));

        return $configuration;
    }

    public function setModule(Organization $organization, string $moduleName, bool $enabled, ?string $actorId): OrganizationModule
    {
        return DB::transaction(function () use ($organization, $moduleName, $enabled, $actorId): OrganizationModule {
            $assignment = OrganizationModule::query()->updateOrCreate(['organization_id' => $organization->id, 'module_name' => $moduleName], ['enabled' => $enabled, 'enabled_by' => $actorId]);
            $enabled ? $this->modules->enable($moduleName, $organization->id) : $this->modules->disable($moduleName, $organization->id);
            event($enabled ? new OrganizationModuleEnabled($organization) : new OrganizationModuleDisabled($organization));

            return $assignment;
        });
    }

    public function settings(Organization $organization): array
    {
        $stored = OrganizationSetting::query()->where('organization_id', $organization->id)->get()->groupBy('group')->map(fn ($items) => $items->mapWithKeys(fn ($item) => [$item->key => $item->value])->all())->all();

        return array_replace_recursive(['general' => config('organizations.settings_defaults')], $stored);
    }
}
