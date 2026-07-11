<?php

declare(strict_types=1);

namespace Core\AIGateway\Application;

use Core\AIGateway\Contracts\AIProviderInterface;
use Core\AIGateway\Exceptions\ProviderNotAvailableException;

/**
 * Instantiates a provider adapter from config/ai.php's `providers` map
 * (the "Configuration Loader" — each entry's `driver` class is
 * constructed with that entry's own config array). New providers are
 * added purely by config + a class implementing AIProviderInterface —
 * no changes needed here. See docs/ai/ai-gateway.md.
 */
final class ProviderFactory
{
    public function make(string $name): AIProviderInterface
    {
        $config = config("ai.providers.{$name}");

        if ($config === null) {
            throw ProviderNotAvailableException::forProvider($name);
        }

        /** @var class-string<AIProviderInterface> $driverClass */
        $driverClass = $config['driver'];

        return new $driverClass($config);
    }
}
