<?php

declare(strict_types=1);

namespace Core\Support\Exceptions;

use Exception;

/**
 * Base class for business-rule violations raised from an Application
 * or Domain layer service (see docs/architecture/clean-architecture.md)
 * — as opposed to infrastructure failures (network errors, driver
 * exceptions). Mapped to a 422 JSON response by
 * Core\Api\Exceptions\ApiExceptionHandler unless a more specific
 * status is warranted, in which case override httpStatus().
 */
class DomainException extends Exception
{
    public function httpStatus(): int
    {
        return 422;
    }
}
