<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Learners\Http\Controllers\Web\GuardianWebController;
use Modules\Learners\Http\Controllers\Web\LearnerWebController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->prefix('learners')->name('learners.')->group(function (): void {
    Route::get('/', [LearnerWebController::class, 'index'])->name('index');
    Route::get('/create', [LearnerWebController::class, 'create'])->name('create');
    Route::post('/', [LearnerWebController::class, 'store'])->name('store');
    Route::middleware('learner.context')->group(function (): void {
        Route::get('/{learner}', [LearnerWebController::class, 'show'])->name('show');
        Route::get('/{learner}/edit', [LearnerWebController::class, 'edit'])->name('edit');
        Route::put('/{learner}', [LearnerWebController::class, 'update'])->name('update');
        Route::get('/{learner}/academic-placement/edit', [LearnerWebController::class, 'editAcademicPlacement'])->name('academic-placement.edit');
        Route::put('/{learner}/academic-placement', [LearnerWebController::class, 'updateAcademicPlacement'])->name('academic-placement.update');
        Route::post('/{learner}/status', [LearnerWebController::class, 'status'])->name('status');
        Route::post('/{learner}/archive', [LearnerWebController::class, 'archive'])->name('archive');
        Route::post('/{learner}/restore', [LearnerWebController::class, 'restore'])->name('restore');
    });
});

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->prefix('guardians')->name('guardians.')->group(function (): void {
    Route::get('/', [GuardianWebController::class, 'index'])->name('index');
    Route::get('/create', [GuardianWebController::class, 'create'])->name('create');
    Route::post('/', [GuardianWebController::class, 'store'])->name('store');
    Route::middleware('guardian.context')->group(function (): void {
        Route::get('/{guardian}', [GuardianWebController::class, 'show'])->name('show');
        Route::get('/{guardian}/edit', [GuardianWebController::class, 'edit'])->name('edit');
        Route::put('/{guardian}', [GuardianWebController::class, 'update'])->name('update');
        Route::post('/{guardian}/archive', [GuardianWebController::class, 'archive'])->name('archive');
    });
});

Route::middleware(['auth', 'account.not-locked', 'organization.context', 'learner.context'])->prefix('learners/{learner}/guardians')->name('learners.guardians.')->group(function (): void {
    Route::post('/', [GuardianWebController::class, 'link'])->name('store');
    Route::put('/{relationship}', [GuardianWebController::class, 'updateRelationship'])->name('update');
    Route::delete('/{relationship}', [GuardianWebController::class, 'unlink'])->name('destroy');
    Route::post('/consents', [GuardianWebController::class, 'recordConsent'])->name('consents.store');
});
