<?php

declare(strict_types=1);

namespace Core\Billing\Exceptions;

use Core\Support\Exceptions\DomainException;

/**
 * Maps to a 422 JSON response automatically via
 * Core\Api\Exceptions\ApiExceptionHandler — controllers do not need
 * to catch this themselves, see core/Billing/README.md.
 */
class BillingException extends DomainException {}
