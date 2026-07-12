<?php

declare(strict_types=1);

namespace Core\Licensing\Domain\Enums;

/**
 * See core/Licensing/README.md. Database-driven entitlements
 * (max_users, max_storage_mb, enabled_modules on the License model
 * itself) are what's actually enforced — the tier is a label for
 * billing/support conversations, not a hardcoded entitlement table.
 */
enum LicenseTier: string
{
    case Trial = 'trial';
    case Starter = 'starter';
    case Professional = 'professional';
    case Enterprise = 'enterprise';
    case Government = 'government';
    case Custom = 'custom';
}
