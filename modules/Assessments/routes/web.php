<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Assessments\Http\Controllers\Api\V1\AssessmentController;
use Modules\Assessments\Http\Controllers\Web\AssessmentWebController;
use Modules\Assessments\Http\Controllers\Web\QuizWebController;

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
    Route::prefix('quizzes')->name('quizzes.')->group(function (): void {
        Route::get('/assigned', [QuizWebController::class, 'assigned'])->name('assigned');
        Route::post('/{assessment}/start', [QuizWebController::class, 'start'])->middleware('assessment.context')->name('start');
        Route::get('/attempts/{attempt}', [QuizWebController::class, 'attempt'])->name('attempt');
        Route::post('/attempts/{attempt}/submit', [QuizWebController::class, 'submit'])->name('submit');
        Route::get('/attempts/{attempt}/review', [QuizWebController::class, 'review'])->name('review');
        Route::post('/attempts/{attempt}/review', [QuizWebController::class, 'saveReview'])->name('review.save');
        Route::post('/attempts/{attempt}/answers/{answer}/suggest', [QuizWebController::class, 'suggest'])->name('answers.suggest');
        Route::post('/attempts/{attempt}/study-plan', [QuizWebController::class, 'generatePlan'])->name('study-plan.generate');
        Route::post('/attempts/{attempt}/study-plan/{plan}/approve', [QuizWebController::class, 'approvePlan'])->name('study-plan.approve');
        Route::post('/attempts/{attempt}/study-plan/{plan}/comment', [QuizWebController::class, 'comment'])->name('study-plan.comment');
        Route::post('/attempts/{attempt}/study-plan/{plan}/progress', [QuizWebController::class, 'progress'])->name('study-plan.progress');
        Route::post('/attempts/{attempt}/study-plan/{plan}/retest', [QuizWebController::class, 'retest'])->name('study-plan.retest');
        Route::post('/attempts/{attempt}/release', [QuizWebController::class, 'release'])->name('release');
        Route::get('/study-plans/analytics', [QuizWebController::class, 'analytics'])->name('study-plan.analytics');
        Route::get('/interventions', [QuizWebController::class, 'interventions'])->name('interventions');
        Route::middleware('assessment.context')->group(function (): void {
            Route::get('/{assessment}', [QuizWebController::class, 'show'])->name('show');
            Route::post('/{assessment}/questions', [QuizWebController::class, 'addQuestion'])->name('questions.store');
            Route::post('/{assessment}/publish', [QuizWebController::class, 'publish'])->name('publish');
        });
    });
});
