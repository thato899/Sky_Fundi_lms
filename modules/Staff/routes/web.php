<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Web\StaffController;
use Modules\Staff\Http\Controllers\Web\TeachingAssignmentAdminController;
use Modules\Staff\Http\Controllers\Web\TeachingAssignmentController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->prefix('teaching-assignments')->name('teaching-assignments.')->group(function (): void {
    Route::get('/', [TeachingAssignmentAdminController::class, 'index'])->name('index');
    Route::post('/bulk', [TeachingAssignmentAdminController::class, 'bulkStore'])->name('bulk');
});

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::get('/create', [StaffController::class, 'create'])->name('create');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{staff}/teaching-assignments', [TeachingAssignmentController::class, 'index'])->name('assignments.index');
    Route::post('/{staff}/teaching-assignments', [TeachingAssignmentController::class, 'store'])->name('assignments.store');
    Route::post('/{staff}/teaching-assignments/{assignment}/end', [TeachingAssignmentController::class, 'end'])->name('assignments.end');
    Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
    Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
    Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
    Route::post('/{staff}/suspend', [StaffController::class, 'suspend'])->name('suspend');
    Route::post('/{staff}/activate', [StaffController::class, 'activate'])->name('activate');
});
