<?php

declare(strict_types=1);

namespace Core\Installer\Providers;

use Core\Installer\Console\InstallCommand;
use Illuminate\Support\ServiceProvider;

final class InstallerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }
}
