<?php

declare(strict_types=1);

namespace Modules\Organizations\Events;

use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Organizations\Infrastructure\Models\Organization;

abstract class OrganizationEvent implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Organization $organization) {}

    public function auditTarget(): ?Model
    {
        return $this->organization;
    }

    public function auditContext(): array
    {
        return ['after' => ['organization_id' => $this->organization->id]];
    }
}
