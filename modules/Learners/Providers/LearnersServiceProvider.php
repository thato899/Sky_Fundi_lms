<?php

declare(strict_types=1);

namespace Modules\Learners\Providers;

use Illuminate\Support\ServiceProvider;

final class LearnersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
