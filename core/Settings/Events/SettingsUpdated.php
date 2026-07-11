<?php

declare(strict_types=1);

namespace Core\Settings\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever one or more settings change. Carries only the key and
 * group (never the value, which may be sensitive) — subscribers that
 * need the new value read it back through SettingsService, per
 * docs/security/policies.md#secrets-management.
 */
final class SettingsUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly string $group,
    ) {}
}
