<?php

declare(strict_types=1);

namespace Core\Analytics\Domain\Enums;

/**
 * The fixed vocabulary of what the platform tracks, per the brief:
 * Users, Organizations, Storage, Requests, AI Usage, Modules, Logins,
 * Errors, API Requests. Kept as an enum (rather than a free-text
 * string) so every recorded event has a name a future dashboard can
 * rely on, per core/Analytics/README.md.
 */
enum AnalyticsMetric: string
{
    case UserRegistered = 'user.registered';
    case OrganizationCreated = 'organization.created';
    case StorageUsed = 'storage.used';
    case ApiRequest = 'api.request';
    case AIUsage = 'ai.usage';
    case AdaptiveLearning = 'learning.adaptive';
    case ModuleEnabled = 'module.enabled';
    case Login = 'auth.login';
    case Error = 'system.error';
}
