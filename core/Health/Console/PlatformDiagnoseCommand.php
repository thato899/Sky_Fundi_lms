<?php

declare(strict_types=1);

namespace Core\Health\Console;

use Core\Health\Application\HealthCheckManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class PlatformDiagnoseCommand extends Command
{
    protected $signature = 'platform:diagnose';

    protected $description = 'Run read-only, secret-safe deployment diagnostics.';

    public function handle(HealthCheckManager $manager): int
    {
        $production = app()->environment('production');
        $configurationSafe = ! $production || (! config('app.debug') && filled(config('app.key')));

        $this->components->twoColumnDetail('Environment', (string) config('app.env'));
        $this->components->twoColumnDetail('Debug mode', config('app.debug') ? '<fg=yellow>enabled</>' : '<fg=green>disabled</>');
        $this->components->twoColumnDetail('Application key', filled(config('app.key')) ? '<fg=green>configured</>' : '<fg=red>missing</>');

        $migrationExit = Artisan::call('migrate:status', ['--no-interaction' => true]);
        $this->components->twoColumnDetail('Migration status', $migrationExit === self::SUCCESS ? '<fg=green>available</>' : '<fg=red>unavailable</>');

        $results = $manager->runReadiness();
        $overall = $manager->overallStatus($results);

        foreach ($results as $result) {
            $this->components->twoColumnDetail("Readiness: {$result->name}", $result->status->value);
        }

        return $configurationSafe && $migrationExit === self::SUCCESS && $overall->value !== 'unhealthy'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
