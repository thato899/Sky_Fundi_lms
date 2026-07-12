<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Application;

use Core\FeatureFlags\Domain\Enums\FeatureFlagScope;
use Core\FeatureFlags\Events\FeatureFlagToggled;
use Core\FeatureFlags\Infrastructure\Models\FeatureFlag;
use Core\FeatureFlags\Infrastructure\Models\FeatureFlagOverride;
use Illuminate\Support\Facades\Cache;

/**
 * Resolution order (most specific wins): an explicit scope override
 * (organization/user/module) -> the flag's global setting -> false if
 * the flag doesn't exist at all. This lets a flag be globally off but
 * turned on for one pilot organization/user, or globally on but
 * turned off for one module — see core/FeatureFlags/README.md.
 */
final class FeatureFlagService
{
    private const CACHE_TTL_SECONDS = 60;

    public function isEnabled(string $key, ?FeatureFlagScope $scopeType = null, ?string $scopeId = null): bool
    {
        $flag = $this->findFlag($key);

        if ($flag === null) {
            return false;
        }

        if ($scopeType !== null && $scopeId !== null) {
            $override = $flag->overrides->firstWhere(
                fn (FeatureFlagOverride $o) => $o->scope_type === $scopeType->value && $o->scope_id === $scopeId,
            );

            if ($override !== null) {
                return $override->is_enabled;
            }
        }

        return $flag->is_enabled_globally;
    }

    public function define(string $key, string $name, ?string $description = null): FeatureFlag
    {
        return FeatureFlag::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $name, 'description' => $description, 'is_enabled_globally' => false],
        );
    }

    public function setGlobal(FeatureFlag $flag, bool $enabled): FeatureFlag
    {
        $flag->update(['is_enabled_globally' => $enabled]);

        event(new FeatureFlagToggled($flag, null, null, $enabled));
        $this->forgetCache($flag->key);

        return $flag->fresh();
    }

    public function setForScope(FeatureFlag $flag, FeatureFlagScope $scopeType, string $scopeId, bool $enabled): FeatureFlagOverride
    {
        $override = FeatureFlagOverride::query()->updateOrCreate(
            ['feature_flag_id' => $flag->id, 'scope_type' => $scopeType->value, 'scope_id' => $scopeId],
            ['is_enabled' => $enabled],
        );

        event(new FeatureFlagToggled($flag, $scopeType->value, $scopeId, $enabled));
        $this->forgetCache($flag->key);

        return $override;
    }

    private function findFlag(string $key): ?FeatureFlag
    {
        return Cache::remember(
            "feature-flag:{$key}",
            self::CACHE_TTL_SECONDS,
            fn () => FeatureFlag::query()->with('overrides')->where('key', $key)->first(),
        );
    }

    private function forgetCache(string $key): void
    {
        Cache::forget("feature-flag:{$key}");
    }
}
