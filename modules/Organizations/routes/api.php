<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Organizations\Http\Controllers\Api\V1\OrganizationController;

Route::middleware(['auth:sanctum', 'account.not-locked'])->prefix('organizations')->name('organizations.')->group(function (): void {
    Route::get('/', [OrganizationController::class, 'index'])->middleware('permission:organizations.view')->name('index');
    Route::post('/', [OrganizationController::class, 'store'])->middleware('permission:organizations.manage')->name('store');
    Route::get('/{organization}', [OrganizationController::class, 'show'])->middleware('permission:organizations.view')->name('show');
    Route::put('/{organization}', [OrganizationController::class, 'update'])->middleware('permission:organizations.manage')->name('update');
    Route::delete('/{organization}', [OrganizationController::class, 'destroy'])->middleware('permission:organizations.manage')->name('destroy');
    Route::post('/{organization}/activate', [OrganizationController::class, 'activate'])->middleware('permission:organizations.manage')->name('activate');
    Route::post('/{organization}/suspend', [OrganizationController::class, 'suspend'])->middleware('permission:organizations.manage')->name('suspend');
    Route::post('/{organization}/administrators', [OrganizationController::class, 'assignAdministrator'])->middleware('permission:organizations.users.manage')->name('administrators.store');
    Route::get('/{organization}/settings', [OrganizationController::class, 'settings'])->middleware('permission:organizations.settings.manage')->name('settings.show');
    Route::put('/{organization}/settings', [OrganizationController::class, 'updateSettings'])->middleware('permission:organizations.settings.manage')->name('settings.update');
    Route::get('/{organization}/branding', [OrganizationController::class, 'branding'])->middleware('permission:organizations.branding.manage')->name('branding.show');
    Route::put('/{organization}/branding', [OrganizationController::class, 'updateBranding'])->middleware('permission:organizations.branding.manage')->name('branding.update');
    Route::put('/{organization}/ai', [OrganizationController::class, 'configureAi'])->middleware('permission:organizations.ai.manage')->name('ai.update');
    Route::put('/{organization}/modules', [OrganizationController::class, 'setModule'])->middleware('permission:organizations.modules.manage')->name('modules.update');
});
