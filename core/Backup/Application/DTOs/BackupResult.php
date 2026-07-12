<?php

declare(strict_types=1);

namespace Core\Backup\Application\DTOs;

final readonly class BackupResult
{
    public function __construct(
        public string $target,
        public bool $success,
        public ?string $path = null,
        public ?int $sizeBytes = null,
        public ?string $error = null,
    ) {}
}
