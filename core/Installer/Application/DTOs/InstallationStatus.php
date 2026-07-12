<?php

declare(strict_types=1);

namespace Core\Installer\Application\DTOs;

final readonly class InstallationStatus
{
    public function __construct(
        public bool $isInstalled,
        public array $steps,
    ) {}
}
