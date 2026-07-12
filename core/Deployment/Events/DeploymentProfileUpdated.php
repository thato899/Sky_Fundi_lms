<?php

declare(strict_types=1);

namespace Core\Deployment\Events;

use Core\Deployment\Infrastructure\Models\DeploymentProfile;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DeploymentProfileUpdated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DeploymentProfile $profile,
        public readonly array $changes,
    ) {}

    public function auditAction(): string
    {
        return 'deployment_profile.updated';
    }

    public function auditTarget(): ?Model
    {
        return $this->profile;
    }

    public function auditContext(): array
    {
        return ['after' => ['changed_fields' => $this->changes]];
    }
}
