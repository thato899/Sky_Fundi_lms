<?php

declare(strict_types=1);

namespace Core\Support\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Scaffolds a new Application-layer service class following the
 * platform's established pattern (final class, constructor-injected
 * dependencies, docblock referencing the owning Core service's
 * README) — see docs/development/coding-standards.md and
 * docs/architecture/clean-architecture.md#application--service-layer.
 *
 * Usage: `php artisan sky-fundi:make:service Auth WelcomeService`
 * creates core/Auth/Application/WelcomeService.php.
 */
final class MakeCoreServiceCommand extends Command
{
    protected $signature = 'sky-fundi:make:service {service : The owning Core service, e.g. Auth} {name : The class name, e.g. WelcomeService}';

    protected $description = 'Scaffold a new Application-layer service class inside an existing Core service folder.';

    public function handle(): int
    {
        $service = Str::studly((string) $this->argument('service'));
        $name = Str::studly((string) $this->argument('name'));

        $directory = base_path("core/{$service}/Application");
        $path = "{$directory}/{$name}.php";

        if (! is_dir(base_path("core/{$service}"))) {
            $this->error("core/{$service} does not exist. Only scaffold services inside an existing Core service — see docs/architecture/module-system.md if you're building a new module instead.");

            return self::FAILURE;
        }

        if (file_exists($path)) {
            $this->error("{$path} already exists.");

            return self::FAILURE;
        }

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($path, $this->stub($service, $name));

        $this->info("Created {$path}");
        $this->line('Remember to: add a docblock explaining its responsibility, wire it into the owning ServiceProvider if it needs eager binding, and add a test under tests/Unit.');

        return self::SUCCESS;
    }

    private function stub(string $service, string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Core\\{$service}\\Application;

/**
 * Describe this service's single responsibility here and link back
 * to core/{$service}/README.md once it's wired in.
 */
final class {$name}
{
}

PHP;
    }
}
