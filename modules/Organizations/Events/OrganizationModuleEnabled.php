<?php

declare(strict_types=1);

namespace Modules\Organizations\Events;

final class OrganizationModuleEnabled extends OrganizationEvent
{
    public function auditAction(): string
    {
        return 'organizations.module.enabled';
    }
}
