<?php

declare(strict_types=1);

namespace Core\Modules\Domain\Enums;

/**
 * See docs/architecture/module-system.md#module-lifecycle. "Discovered"
 * is deliberately not a persisted status — a module on disk with no
 * registry row is simply not yet Installed.
 */
enum ModuleStatus: string
{
    case Installed = 'installed';
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Installed => 'Installed',
            self::Enabled => 'Enabled',
            self::Disabled => 'Disabled',
        };
    }
}
