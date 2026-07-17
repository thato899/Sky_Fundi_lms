<?php

declare(strict_types=1);

namespace Core\Health\Application;

use Core\Health\Application\DTOs\HealthCheckResult;
use Core\Health\Contracts\HealthCheckInterface;
use Core\Health\Domain\Enums\HealthStatus;
use Illuminate\Contracts\Container\Container;

/**
 * Runs every registered health check (config('health.checks'), see
 * config/health.php) and rolls the results up into one overall
 * status. Individual checks never throw (see HealthCheckInterface's
 * docblock); this manager additionally guards with a try/catch so a
 * misbehaving check can never break the endpoint for every other
 * check. See core/Health/README.md.
 */
final class HealthCheckManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @return HealthCheckResult[]
     */
    public function runAll(): array
    {
        return $this->run((array) config('health.checks', []));
    }

    /**
     * @return HealthCheckResult[]
     */
    public function runReadiness(): array
    {
        return $this->run((array) config('health.readiness_checks', []));
    }

    /**
     * @param  array<class-string<HealthCheckInterface>>  $checkClasses
     * @return HealthCheckResult[]
     */
    private function run(array $checkClasses): array
    {
        $results = [];

        foreach ($checkClasses as $checkClass) {
            /** @var HealthCheckInterface $check */
            $check = $this->container->make($checkClass);

            try {
                $results[] = $check->check();
            } catch (\Throwable) {
                $results[] = HealthCheckResult::unhealthy($check->name(), 'Health check failed.');
            }
        }

        return $results;
    }

    public function overallStatus(array $results): HealthStatus
    {
        return array_reduce(
            $results,
            fn (HealthStatus $carry, HealthCheckResult $result) => $carry->worseOf($result->status),
            HealthStatus::Healthy,
        );
    }
}
