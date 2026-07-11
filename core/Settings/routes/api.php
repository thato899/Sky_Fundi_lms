<?php

declare(strict_types=1);

use Core\Settings\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by SettingsServiceProvider. See
| core/Settings/README.md.
*/

Route::middleware(['auth:sanctum'])->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/', [SettingsController::class, 'index'])
        ->middleware('permission:core.settings.manage')
        ->name('index');

    Route::put('/', [SettingsController::class, 'update'])
        ->middleware('permission:core.settings.manage')
        ->name('update');
});
