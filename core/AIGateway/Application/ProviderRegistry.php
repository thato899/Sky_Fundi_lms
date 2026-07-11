<?php

declare(strict_types=1);

namespace Core\AIGateway\Application;

use Core\AIGateway\Contracts\AIProviderInterface;

/**
 * Answers "what providers exist and which are actually usable right
 * now" — used by the admin AI settings screen and by AIManager when
 * resolving a fallback. See docs/ai/ai-gateway.md.
 */
final class ProviderRegistry
{
    public function __construct(
        private readonly ProviderFactory $factory,
    ) {}

    /**
     * @return array<string, AIProviderInterface>
     */
    public function all(): array
    {
        $providers = [];

        foreach (array_keys(config('ai.providers', [])) as $name) {
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
            fn (AIProviderInterface $provider) => $provider->isAvailable(),
        ));
    }
}
