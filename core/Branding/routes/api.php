<?php

declare(strict_types=1);

use Core\Branding\Http\Controllers\Api\V1\BrandingController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by BrandingServiceProvider. `show` is public —
| see core/Branding/README.md.
*/

Route::prefix('branding')->name('branding.')->group(function (): void {
    Route::get('/', [BrandingController::class, 'show'])->name('show');

    Route::put('/', [BrandingController::class, 'update'])
        ->middleware(['auth:sanctum', 'permission:core.branding.manage'])
        ->name('update');
});
