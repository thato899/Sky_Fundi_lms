<?php

declare(strict_types=1);

namespace Core\Health\Console;

use Core\Health\Application\HealthCheckManager;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Throwable;

final class PlatformDiagnoseCommand extends Command
{
    protected $signature = 'platform:diagnose';

    protected $description = 'Run read-only, secret-safe deployment diagnostics.';

    public function handle(HealthCheckManager $manager, Migrator $migrator): int
    {
        $production = app()->environment('production');
        $configurationSafe = ! $production || (! config('app.debug') && filled(config('app.key')));

        $this->components->twoColumnDetail('Environment', (string) config('app.env'));
        $this->components->twoColumnDetail('Debug mode', config('app.debug') ? '<fg=yellow>enabled</>' : '<fg=green>disabled</>');
        $this->components->twoColumnDetail('Application key', filled(config('app.key')) ? '<fg=green>configured</>' : '<fg=red>missing</>');

        $migrationsHealthy = $this->reportMigrationStatus($migrator);

        $results = $manager->runReadiness();
        $overall = $manager->overallStatus($results);

        foreach ($results as $result) {
            $this->components->twoColumnDetail("Readiness: {$result->name}", $result->status->value);
        }

        return $configurationSafe && $migrationsHealthy && $overall->value !== 'unhealthy'
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function reportMigrationStatus(Migrator $migrator): bool
    {
        try {
            if (! $migrator->repositoryExists()) {
                $this->components->twoColumnDetail('Migration repository', '<fg=red>unavailable</>');
                $this->components->twoColumnDetail('Pending migrations', '<fg=red>unknown</>');

                return false;
            }

            $this->components->twoColumnDetail('Migration repository', '<fg=green>available</>');

            $paths = array_merge($migrator->paths(), [database_path('migrations')]);
            $migrationNames = array_keys($migrator->getMigrationFiles($paths));
            $pendingCount = count(array_diff($migrationNames, $migrator->getRepository()->getRan()));

            $this->components->twoColumnDetail(
                'Pending migrations',
                $pendingCount === 0 ? '<fg=green>none</>' : "<fg=red>{$pendingCount}</>",
            );

            return $pendingCount === 0;
        } catch (Throwable) {
            $this->components->twoColumnDetail('Migration repository', '<fg=red>unavailable</>');
            $this->components->twoColumnDetail('Pending migrations', '<fg=red>unknown</>');

            return false;
        }
    }
}
