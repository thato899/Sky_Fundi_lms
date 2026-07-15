<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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
