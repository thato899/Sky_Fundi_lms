<?php

declare(strict_types=1);

namespace Core\Billing\Application;

use Core\Billing\Contracts\PaymentGatewayInterface;
use Core\Billing\Exceptions\GatewayUnavailableException;

/**
 * Instantiates a gateway adapter from config/billing.php's `gateways`
 * map — mirrors Core\AIGateway\Application\ProviderFactory. A new
 * gateway is added purely by config + a class implementing
 * PaymentGatewayInterface — no changes needed here.
 */
final class GatewayFactory
{
    public function make(string $name): PaymentGatewayInterface
    {
        $config = config("billing.gateways.{$name}");

        if ($config === null) {
            throw GatewayUnavailableException::unknown($name);
        }

        /** @var class-string<PaymentGatewayInterface> $driverClass */
        $driverClass = $config['driver'];

        return new $driverClass($config);
    }
}
