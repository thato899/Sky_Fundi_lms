<?php

declare(strict_types=1);

namespace Core\Storage\Exceptions;

use Exception;

/**
 * Thrown when Core\Storage\Application\StorageManager is asked for a
 * driver that isn't wired up yet (S3, Azure, Google Cloud — see
 * core/Storage/README.md). Local is always available.
 */
final class StorageDriverNotAvailableException extends Exception
{
    public static function forDriver(string $driver): self
    {
        return new self("Storage driver \"{$driver}\" is not yet available. See core/Storage/README.md for planned drivers.");
    }
}
