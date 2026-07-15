<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Learners\Http\Controllers\Api\V1\LearnerController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])
    ->prefix('learners')
    ->name('api.learners.')
    ->group(function (): void {
        Route::get('/', [LearnerController::class, 'index'])->name('index');
        Route::post('/', [LearnerController::class, 'store'])->name('store');

        Route::middleware('learner.context')->group(function (): void {
            Route::get('/{learner}', [LearnerController::class, 'show'])->name('show');
            Route::patch('/{learner}', [LearnerController::class, 'update'])->name('update');
            Route::patch('/{learner}/academic-placement', [LearnerController::class, 'academicPlacement'])->name('academic-placement');
            Route::post('/{learner}/status', [LearnerController::class, 'status'])->name('status');
            Route::post('/{learner}/archive', [LearnerController::class, 'archive'])->name('archive');
            Route::post('/{learner}/restore', [LearnerController::class, 'restore'])->name('restore');
            Route::get('/{learner}/status-history', [LearnerController::class, 'statusHistory'])->name('status-history');
        });
    });
