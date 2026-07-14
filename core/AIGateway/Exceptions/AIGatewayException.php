<?php

declare(strict_types=1);

namespace Core\AIGateway\Exceptions;

use Exception;

/**
 * A provider was available but the request itself failed (network
 * error, non-2xx response, malformed payload) — distinct from
 * ProviderNotAvailableException, which means no attempt was made.
 */
final class AIGatewayException extends Exception {}
