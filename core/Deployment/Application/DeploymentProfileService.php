<?php

declare(strict_types=1);

namespace Core\Deployment\Application;

use Core\Deployment\Events\DeploymentProfileCreated;
use Core\Deployment\Events\DeploymentProfileUpdated;
use Core\Deployment\Infrastructure\Models\DeploymentProfile;

/**
 * Structured deployment configuration only — see
 * core/Deployment/README.md. This service never provisions
 * infrastructure, runs migrations against a remote database, or
 * touches a cloud provider API; it records what a deployment *should*
 * look like for a later, separate provisioning tool to read.
 */
final class DeploymentProfileService
{
    public function create(array $attributes): DeploymentProfile
    {
        $profile = DeploymentProfile::create($attributes);

        event(new DeploymentProfileCreated($profile));

        return $profile;
    }

    public function update(DeploymentProfile $profile, array $attributes): DeploymentProfile
    {
        $profile->update($attributes);

        event(new DeploymentProfileUpdated($profile, array_keys($attributes)));

        return $profile->fresh();
    }
}
