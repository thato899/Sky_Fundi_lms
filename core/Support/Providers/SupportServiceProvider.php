<?php

declare(strict_types=1);

namespace Core\Support\Providers;

use Core\Support\Console\MakeCoreServiceCommand;
use Illuminate\Support\ServiceProvider;

final class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakeCoreServiceCommand::class]);
        }
    }
}
