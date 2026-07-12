<?php

declare(strict_types=1);

namespace Core\Deployment\Events;

use Core\Deployment\Infrastructure\Models\DeploymentProfile;
use Core\Support\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DeploymentProfileCreated implements Auditable
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DeploymentProfile $profile,
    ) {}

    public function auditAction(): string
    {
        return 'deployment_profile.created';
    }

    public function auditTarget(): ?Model
    {
        return $this->profile;
    }

    public function auditContext(): array
    {
        return ['after' => ['strategy' => $this->profile->strategy->value]];
    }
}
