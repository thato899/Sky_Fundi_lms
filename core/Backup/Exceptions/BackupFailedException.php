<?php

declare(strict_types=1);

namespace Core\Backup\Exceptions;

use Exception;

final class BackupFailedException extends Exception
{
    public static function forTarget(string $target, string $reason): self
    {
        return new self("Backup target \"{$target}\" failed: {$reason}");
    }
}
