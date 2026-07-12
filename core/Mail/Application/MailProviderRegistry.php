<?php

declare(strict_types=1);

namespace Core\Mail\Application;

use Core\Mail\Contracts\MailProviderInterface;

/**
 * Answers "what mail providers exist and which are usable right now"
 * — used by the platform Health Centre (see core/Health/README.md)
 * and admin mail settings. Mirrors Core\AIGateway\Application\
 * ProviderRegistry. See core/Mail/README.md.
 */
final class MailProviderRegistry
{
    public function __construct(
        private readonly MailProviderFactory $factory,
    ) {}

    /**
     * @return array<string, MailProviderInterface>
     */
    public function all(): array
    {
        $providers = [];

        foreach (array_keys(config('mail_providers.providers', [])) as $name) {
            $providers[$name] = $this->factory->make($name);
        }

        return $providers;
    }

    /**
     * @return string[]
     */
    public function availableProviderNames(): array
    {
        return array_keys(array_filter(
            $this->all(),
            fn (MailProviderInterface $provider) => $provider->isAvailable(),
        ));
    }
}
