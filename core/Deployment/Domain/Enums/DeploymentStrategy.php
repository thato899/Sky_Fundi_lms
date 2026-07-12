<?php

declare(strict_types=1);

namespace Core\Deployment\Domain\Enums;

/**
 * See core/Deployment/README.md and docs/architecture/multi-tenancy.md.
 * No deployment automation is implemented for any of these — this
 * enum and the profile it's stored on are structured configuration
 * only, per the brief ("Do not implement deployment automation yet").
 */
enum DeploymentStrategy: string
{
    case SingleServer = 'single_server';
    case DedicatedServer = 'dedicated_server';
    case Cloud = 'cloud';
    case Docker = 'docker';
    case Kubernetes = 'kubernetes';
}
