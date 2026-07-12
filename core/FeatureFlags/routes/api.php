<?php

declare(strict_types=1);

use Core\FeatureFlags\Http\Controllers\Api\V1\FeatureFlagController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by FeatureFlagsServiceProvider — see
| core/FeatureFlags/README.md.
*/

Route::middleware(['auth:sanctum', 'permission:core.feature-flags.manage'])
    ->prefix('feature-flags')->name('feature-flags.')->group(function (): void {
        Route::get('/', [FeatureFlagController::class, 'index'])->name('index');
        Route::post('/', [FeatureFlagController::class, 'store'])->name('store');
        Route::put('/{featureFlag}/toggle', [FeatureFlagController::class, 'toggle'])->name('toggle');
    });
