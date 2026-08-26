<?php

declare(strict_types=1);

use Core\Subscriptions\Http\Controllers\Api\V1\PlanController;
use Core\Subscriptions\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by SubscriptionsServiceProvider — see
| core/Subscriptions/README.md.
*/

// Read-only, non-sensitive catalog data (plan names/prices/entitlements)
// — any authenticated user may browse it, unlike Subscriptions/Billing
// management below which requires core.billing.manage.
Route::middleware('auth:sanctum')
    ->prefix('plans')->name('plans.')->group(function (): void {
        Route::get('/', [PlanController::class, 'index'])->name('index');
    });

Route::middleware(['auth:sanctum', 'permission:core.billing.manage'])
    ->prefix('subscriptions')->name('subscriptions.')->group(function (): void {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::post('/', [SubscriptionController::class, 'store'])->name('store');
        Route::get('/{subscription}', [SubscriptionController::class, 'show'])->name('show');
        Route::get('/{subscription}/history', [SubscriptionController::class, 'history'])->name('history');
        Route::put('/{subscription}/usage', [SubscriptionController::class, 'recordUsage'])->name('usage');
        Route::post('/{subscription}/suspend', [SubscriptionController::class, 'suspend'])->name('suspend');
        Route::post('/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])->name('reactivate');
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
    });
