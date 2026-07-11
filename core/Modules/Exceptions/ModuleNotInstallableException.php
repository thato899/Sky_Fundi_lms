<?php

declare(strict_types=1);

namespace Core\Modules\Exceptions;

use Exception;

final class ModuleNotInstallableException extends Exception
{
    public static function manifestMissing(string $moduleName): self
    {
        return new self("No module.json manifest found for module \"{$moduleName}\" under the configured modules path.");
    }

    public static function alreadyInstalled(string $moduleName): self
    {
        return new self("Module \"{$moduleName}\" is already installed.");
    }

    public static function unsupportedTenantType(string $moduleName, string $tenantType): self
    {
        return new self("Module \"{$moduleName}\" does not support tenant type \"{$tenantType}\".");
    }
}
