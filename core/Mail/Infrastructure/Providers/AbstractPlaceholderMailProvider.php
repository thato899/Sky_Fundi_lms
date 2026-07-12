<?php

declare(strict_types=1);

namespace Core\Mail\Infrastructure\Providers;

use Core\Mail\Contracts\MailProviderInterface;
use Core\Support\Exceptions\ProviderNotAvailableException;

/**
 * Base for mail providers documented as "future" but not yet wired up
 * (Microsoft 365, Google Workspace — both OAuth-based, unlike the
 * SMTP-credential-style transports Laravel supports natively). Mirrors
 * Core\AIGateway\Infrastructure\Providers\AbstractPlaceholderProvider.
 * See core/Mail/README.md.
 */
abstract class AbstractPlaceholderMailProvider implements MailProviderInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function mailerName(): string
    {
        throw ProviderNotAvailableException::notImplemented('mail', $this->name());
    }
}
