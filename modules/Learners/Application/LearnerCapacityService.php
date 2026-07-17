<?php

declare(strict_types=1);

namespace Modules\Learners\Application;

use Core\Licensing\Domain\Enums\LicenseStatus;
use Core\Licensing\Infrastructure\Models\License;
use Core\Support\Exceptions\DomainException;
use Modules\Learners\Infrastructure\Models\LearnerProfile;
use Modules\Organizations\Infrastructure\Models\Organization;

final class LearnerCapacityService
{
    public function assertAvailable(Organization $organization, int $additional = 1): void
    {
        /** @var License|null $license */
        $license = License::query()
            ->where('licensee_type', Organization::class)
            ->where('licensee_id', $organization->getKey())
            ->where('status', LicenseStatus::Active)
            ->latest('created_at')
            ->lockForUpdate()
            ->first();

        if ($license === null || $license->getAttribute('max_learners') === null) {
            return;
        }

        $used = LearnerProfile::query()
            ->where('organization_id', $organization->getKey())
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->count();

        if ($used + $additional > (int) $license->getAttribute('max_learners')) {
            throw new DomainException('The organization learner licence limit has been reached.');
        }
    }
}
