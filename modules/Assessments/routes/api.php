<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Assessments\Http\Controllers\Api\V1\AssessmentCategoryController;
use Modules\Assessments\Http\Controllers\Api\V1\AssessmentController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->group(function (): void {
    Route::get('/learners/{learner}/results', [AssessmentController::class, 'learnerHistory']);
    Route::get('/gradebook', [AssessmentController::class, 'gradebook']);
    Route::get('/assessment-reports/summary', [AssessmentController::class, 'summary']);
    Route::get('/assessment-categories', [AssessmentCategoryController::class, 'index']);
    Route::post('/assessment-categories', [AssessmentCategoryController::class, 'store']);
    Route::post('/assessment-categories/reorder', [AssessmentCategoryController::class, 'reorder']);
    Route::middleware('assessment-category.context')->group(function (): void {
        Route::patch('/assessment-categories/{category}', [AssessmentCategoryController::class, 'update']);
        Route::post('/assessment-categories/{category}/activate', [AssessmentCategoryController::class, 'activate']);
        Route::post('/assessment-categories/{category}/deactivate', [AssessmentCategoryController::class, 'deactivate']);
    });
    Route::get('/assessments', [AssessmentController::class, 'index']);
    Route::post('/assessments', [AssessmentController::class, 'store']);
    Route::middleware('assessment.context')->group(function (): void {
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
        Route::patch('/assessments/{assessment}', [AssessmentController::class, 'update']);
        Route::post('/assessments/{assessment}/marks', [AssessmentController::class, 'marks']);
        Route::post('/assessments/{assessment}/finalize', [AssessmentController::class, 'finalize']);
        Route::post('/assessments/{assessment}/reopen', [AssessmentController::class, 'reopen']);
        Route::post('/assessments/{assessment}/cancel', [AssessmentController::class, 'cancel']);
        Route::post('/assessments/{assessment}/release', [AssessmentController::class, 'release']);
        Route::post('/assessments/{assessment}/withhold', [AssessmentController::class, 'withhold']);
        Route::get('/assessments/{assessment}/export', [AssessmentController::class, 'export']);
    });
});
