<?php

declare(strict_types=1);

use Core\Health\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by HealthServiceProvider. `/health` is public
| by design — see core/Health/README.md.
*/

Route::get('/health', [HealthController::class, 'index'])->name('health');

Route::get('/health/detailed', [HealthController::class, 'detailed'])
    ->middleware(['auth:sanctum', 'permission:core.health.view'])
    ->name('health.detailed');
