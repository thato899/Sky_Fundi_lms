<?php

declare(strict_types=1);

use Core\Auth\Http\Controllers\Api\V1\EmailVerificationController;
use Core\Auth\Http\Controllers\Api\V1\ForgotPasswordController;
use Core\Auth\Http\Controllers\Api\V1\LoginController;
use Core\Auth\Http\Controllers\Api\V1\LogoutController;
use Core\Auth\Http\Controllers\Api\V1\ResetPasswordController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1/auth by CoreAuthServiceProvider. See
| docs/api/authentication.md.
*/

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('forgot-password');

    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('reset-password');

    Route::middleware(['auth:sanctum', 'account.not-locked'])->group(function (): void {
        Route::post('/logout', [LogoutController::class, 'store'])->name('logout');

        Route::post('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');

        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');
    });
});
