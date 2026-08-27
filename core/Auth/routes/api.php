<?php

declare(strict_types=1);

use Core\Auth\Http\Controllers\Api\V1\EmailVerificationController;
use Core\Auth\Http\Controllers\Api\V1\ForgotPasswordController;
use Core\Auth\Http\Controllers\Api\V1\LoginController;
use Core\Auth\Http\Controllers\Api\V1\LogoutController;
use Core\Auth\Http\Controllers\Api\V1\ResetPasswordController;
use Core\Auth\Http\Controllers\Api\V1\TwoFactorChallengeController;
use Core\Auth\Http\Controllers\Api\V1\TwoFactorController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1/auth by CoreAuthServiceProvider. See
| docs/api/authentication.md.
*/

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login');

    // Second step of a two-factor-gated login (see LoginController) —
    // brute-forcing a 6-digit code is far more feasible than a password,
    // so this is throttled tighter than login itself.
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('two-factor-challenge');

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

        // Self-service two-factor management for the authenticated user's
        // own account only — see TwoFactorController.
        Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
            Route::get('/', [TwoFactorController::class, 'show'])->name('show');
            Route::post('/', [TwoFactorController::class, 'store'])->name('store');
            Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
            Route::delete('/', [TwoFactorController::class, 'destroy'])->name('destroy');
            Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
        });
    });
});
