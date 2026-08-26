<?php

declare(strict_types=1);

namespace Core\Billing\Application;

use Core\Billing\Contracts\PaymentGatewayInterface;
use Core\Billing\Exceptions\GatewayUnavailableException;

/**
 * The single entry point every Billing application service uses for
 * gateway capability — mirrors Core\AIGateway\Application\AIManager.
 * Never inject a gateway class directly; always depend on
 * GatewayManager.
 */
final class GatewayManager
{
    public function __construct(private readonly GatewayFactory $factory) {}

    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name ??= (string) config('billing.default_gateway');
        $gateway = $this->factory->make($name);

        if (! $gateway->isAvailable()) {
            throw GatewayUnavailableException::notConfigured($name);
        }

        return $gateway;
    }
}
