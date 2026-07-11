<?php

declare(strict_types=1);

namespace Core\Settings\Application;

use Core\AuditLogs\Application\AuditLogService;
use Core\Settings\Events\SettingsUpdated;
use Core\Settings\Infrastructure\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * The single read/write path for platform-level configuration. Every
 * Core service and future module reads its runtime configuration
 * through this service rather than config()/env() so it stays
 * database-driven and changeable without a redeploy — see
 * core/Settings/README.md ("Every setting should be database-driven.
 * No hardcoded configuration.").
 */
final class SettingsService
{
    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember(
            "settings:{$key}",
            self::CACHE_TTL_SECONDS,
            fn () => Setting::query()->where('key', $key)->first(),
        );

        if ($setting === null) {
            return $default;
        }

        $value = $setting->value;

        if ($setting->is_encrypted && is_string($value)) {
            $value = Crypt::decryptString($value);
        }

        return $value;
    }

    public function all(?string $group = null): array
    {
        return Setting::query()
            ->when($group, fn ($q) => $q->where('group', $group))
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [
                $setting->key => $setting->is_encrypted && is_string($setting->value)
                    ? Crypt::decryptString($setting->value)
                    : $setting->value,
            ])
            ->all();
    }

    public function set(string $key, mixed $value, string $group = 'general', bool $encrypted = false): void
    {
        $storedValue = $encrypted && is_string($value) ? Crypt::encryptString($value) : $value;

        $before = Setting::query()->where('key', $key)->first()?->value;

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => $storedValue, 'is_encrypted' => $encrypted],
        );

        Cache::forget("settings:{$key}");

        event(new SettingsUpdated($key, $group));

        $this->auditLog->record(
            action: 'settings.updated',
            before: $encrypted ? null : ['key' => $key, 'value' => $before],
            after: $encrypted ? ['key' => $key, 'value' => '[encrypted]'] : ['key' => $key, 'value' => $value],
        );
    }

    /**
     * Bulk-set convenience for admin "save all settings in a group" UIs.
     */
    public function setMany(array $values, string $group, array $encryptedKeys = []): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $group, in_array($key, $encryptedKeys, true));
        }
    }

    public function isMaintenanceMode(): bool
    {
        return (bool) $this->get('maintenance_mode', false);
    }
}
