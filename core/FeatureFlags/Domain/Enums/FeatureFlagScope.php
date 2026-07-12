<?php

declare(strict_types=1);

namespace Core\FeatureFlags\Domain\Enums;

enum FeatureFlagScope: string
{
    case Platform = 'platform';
    case Organization = 'organization';
    case User = 'user';
    case Module = 'module';
}
