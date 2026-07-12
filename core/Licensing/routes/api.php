<?php

declare(strict_types=1);

use Core\Licensing\Http\Controllers\Api\V1\LicenseController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by LicensingServiceProvider. All actions
| require core.licenses.manage — see core/Licensing/README.md.
*/

Route::middleware(['auth:sanctum', 'permission:core.licenses.manage'])
    ->prefix('licenses')->name('licenses.')->group(function (): void {
        Route::get('/', [LicenseController::class, 'index'])->name('index');
        Route::post('/', [LicenseController::class, 'store'])->name('store');
        Route::get('/{license}', [LicenseController::class, 'show'])->name('show');
        Route::post('/{license}/activate', [LicenseController::class, 'activate'])->name('activate');
        Route::post('/{license}/suspend', [LicenseController::class, 'suspend'])->name('suspend');
        Route::post('/{license}/cancel', [LicenseController::class, 'cancel'])->name('cancel');
        Route::post('/{license}/renew', [LicenseController::class, 'renew'])->name('renew');
    });
