<?php

declare(strict_types=1);

namespace Modules\EnterpriseOperations\Application;

use Core\FeatureFlags\Application\FeatureFlagService;
use Core\FeatureFlags\Domain\Enums\FeatureFlagScope;
use Core\Support\Exceptions\DomainException;
use Modules\EnterpriseOperations\Infrastructure\Models\Campus;
use Modules\Organizations\Infrastructure\Models\Organization;

final class CampusService
{
    public const FLAG = 'enterprise.multi_campus';

    public function __construct(private readonly FeatureFlagService $flags) {}

    public function enabled(Organization $organization): bool
    {
        return $this->flags->isEnabled(self::FLAG, FeatureFlagScope::Organization, (string) $organization->getKey());
    }

    public function create(Organization $organization, array $data): Campus
    {
        if (!$this->enabled($organization)) {
            throw new DomainException('Multi-campus operations are not enabled for this organization.');
        }

        return Campus::query()->create(['organization_id' => $organization->getKey(), ...$data]);
    }
}
