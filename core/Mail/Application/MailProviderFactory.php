<?php

declare(strict_types=1);

namespace Core\Mail\Application;

use Core\Mail\Contracts\MailProviderInterface;
use Core\Support\Exceptions\ProviderNotAvailableException;

/**
 * Instantiates a mail provider adapter from config/mail_providers.php.
 * Mirrors Core\AIGateway\Application\ProviderFactory and
 * Core\Storage\Application\StorageProviderFactory. See
 * core/Mail/README.md.
 */
final class MailProviderFactory
{
    public function make(string $name): MailProviderInterface
    {
        $config = config("mail_providers.providers.{$name}");

        if ($config === null) {
            throw ProviderNotAvailableException::forProvider('mail', $name);
        }

        /** @var class-string<MailProviderInterface> $driverClass */
        $driverClass = $config['driver'];

        return new $driverClass();
    }
}
