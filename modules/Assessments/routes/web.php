<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Assessments\Http\Controllers\Api\V1\AssessmentController;
use Modules\Assessments\Http\Controllers\Web\AssessmentWebController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->group(function (): void {
    Route::get('/learners/{learner}/results', [AssessmentWebController::class, 'learnerHistory'])->middleware('learner.context')->name('learners.results');
    Route::get('/gradebook', [AssessmentWebController::class, 'gradebook'])->name('assessments.gradebook');
    Route::get('/assessment-reports', [AssessmentWebController::class, 'reports'])->name('assessments.reports');
    Route::prefix('assessment-categories')->name('assessment-categories.')->group(function (): void {
        Route::get('/', [AssessmentWebController::class, 'categories'])->name('index');
        Route::post('/', [AssessmentWebController::class, 'storeCategory'])->name('store');
        Route::middleware('assessment-category.context')->group(function (): void {
            Route::patch('/{category}', [AssessmentWebController::class, 'updateCategory'])->name('update');
            Route::post('/{category}/activate', fn ($category, AssessmentWebController $c, Request $r) => $c->toggleCategory($r, $category, true))->name('activate');
            Route::post('/{category}/deactivate', fn ($category, AssessmentWebController $c, Request $r) => $c->toggleCategory($r, $category, false))->name('deactivate');
        });
    });
    Route::prefix('assessments')->name('assessments.')->group(function (): void {
        Route::get('/', [AssessmentWebController::class, 'index'])->name('index');
        Route::get('/create', [AssessmentWebController::class, 'create'])->name('create');
        Route::post('/', [AssessmentWebController::class, 'store'])->name('store');
        Route::middleware('assessment.context')->group(function (): void {
            Route::get('/{assessment}', [AssessmentWebController::class, 'show'])->name('show');
            Route::post('/{assessment}/marks', [AssessmentWebController::class, 'marks'])->name('marks');
            foreach (['finalize', 'reopen', 'cancel', 'release', 'withhold'] as $action) {
                Route::post('/{assessment}/'.$action, fn ($assessment, AssessmentWebController $c, Request $r) => $c->action($r, $assessment, $action))->name($action);
            }
            Route::get('/{assessment}/export', [AssessmentController::class, 'export'])->name('export');
        });
    });
});
