<?php

declare(strict_types=1);

namespace Core\Backup\Providers;

use Core\Backup\Console\RunBackupCommand;
use Illuminate\Support\ServiceProvider;

final class BackupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RunBackupCommand::class]);
        }
    }
}
