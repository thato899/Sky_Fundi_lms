<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Web\StaffController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::get('/create', [StaffController::class, 'create'])->name('create');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
    Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
    Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
    Route::post('/{staff}/suspend', [StaffController::class, 'suspend'])->name('suspend');
    Route::post('/{staff}/activate', [StaffController::class, 'activate'])->name('activate');
});
