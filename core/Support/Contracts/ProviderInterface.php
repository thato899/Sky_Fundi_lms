<?php

declare(strict_types=1);

namespace Core\Support\Contracts;

/**
 * The common shape shared by every provider-pattern abstraction on the
 * platform — AI providers, storage providers, mail providers, and so
 * on (see core/AIGateway, core/Storage, core/Mail). Not required to be
 * implemented directly; each concrete *ProviderInterface (e.g.
 * Core\AIGateway\Contracts\AIProviderInterface) extends this so
 * Core\Support\Contracts\ProviderRegistry can work generically across
 * all of them. See docs/development/README.md for the project's
 * provider-pattern convention.
 */
interface ProviderInterface
{
    /**
     * The provider's registry key, e.g. "ollama", "s3", "mailgun".
     */
    public function name(): string;

    /**
     * Whether this provider is currently configured and usable.
     */
    public function isAvailable(): bool;
}
