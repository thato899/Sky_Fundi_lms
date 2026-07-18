<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Learners\Http\Controllers\Api\V1\GuardianController;
use Modules\Learners\Http\Controllers\Api\V1\GuardianInvitationController;
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

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])
    ->prefix('guardians')->name('api.guardians.')->group(function (): void {
        Route::get('/', [GuardianController::class, 'index'])->name('index');
        Route::post('/', [GuardianController::class, 'store'])->name('store');
        Route::middleware('guardian.context')->group(function (): void {
            Route::get('/{guardian}', [GuardianController::class, 'show'])->name('show');
            Route::patch('/{guardian}', [GuardianController::class, 'update'])->name('update');
            Route::post('/{guardian}/archive', [GuardianController::class, 'archive'])->name('archive');
            Route::get('/{guardian}/invitations', [GuardianInvitationController::class, 'index'])->name('invitations.index');
            Route::post('/{guardian}/invitations', [GuardianInvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitations.store');
            Route::post('/{guardian}/invitations/{invitation}/resend', [GuardianInvitationController::class, 'resend'])->middleware('throttle:3,1')->name('invitations.resend');
            Route::post('/{guardian}/invitations/{invitation}/revoke', [GuardianInvitationController::class, 'revoke'])->name('invitations.revoke');
        });
    });

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context', 'learner.context'])
    ->prefix('learners/{learner}/guardians')->name('api.learners.guardians.')->group(function (): void {
        Route::get('/', [GuardianController::class, 'learnerGuardians'])->name('index');
        Route::post('/', [GuardianController::class, 'link'])->name('store');
        Route::patch('/{relationship}', [GuardianController::class, 'updateRelationship'])->name('update');
        Route::delete('/{relationship}', [GuardianController::class, 'unlink'])->name('destroy');
        Route::post('/consents', [GuardianController::class, 'recordConsent'])->name('consents.store');
    });
