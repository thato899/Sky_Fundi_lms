<?php

declare(strict_types=1);

use Core\Analytics\Http\Controllers\Api\V1\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by AnalyticsServiceProvider. Infrastructure
| only — no dashboards, see core/Analytics/README.md.
*/

Route::middleware(['auth:sanctum', 'permission:core.analytics.view'])
    ->prefix('analytics')->name('analytics.')->group(function (): void {
        Route::get('/metrics', [AnalyticsController::class, 'metrics'])->name('metrics');
        Route::get('/summary', [AnalyticsController::class, 'summary'])->name('summary');
    });
