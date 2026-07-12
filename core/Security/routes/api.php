<?php

declare(strict_types=1);

use Core\Security\Http\Controllers\Api\V1\IpRestrictionController;
use Core\Security\Http\Controllers\Api\V1\SessionController;
use Core\Security\Http\Controllers\Api\V1\TrustedDeviceController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by SecurityServiceProvider — see
| core/Security/README.md.
*/

Route::middleware(['auth:sanctum', 'account.not-locked'])->group(function (): void {
    Route::prefix('security/trusted-devices')->name('security.trusted-devices.')->group(function (): void {
        Route::get('/', [TrustedDeviceController::class, 'index'])->name('index');
        Route::post('/', [TrustedDeviceController::class, 'store'])->name('store');
        Route::delete('/{trustedDevice}', [TrustedDeviceController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('security/sessions')->name('security.sessions.')->group(function (): void {
        Route::get('/', [SessionController::class, 'index'])->name('index');
        Route::delete('/others', [SessionController::class, 'destroyOthers'])->name('destroy-others');
        Route::delete('/{token}', [SessionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('security/ip-restrictions')->name('security.ip-restrictions.')->middleware('permission:core.security.manage')->group(function (): void {
        Route::get('/', [IpRestrictionController::class, 'index'])->name('index');
        Route::post('/', [IpRestrictionController::class, 'store'])->name('store');
        Route::delete('/{ipRestriction}', [IpRestrictionController::class, 'destroy'])->name('destroy');
    });
});
