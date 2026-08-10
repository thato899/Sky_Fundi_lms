<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseOperations\Http\Controllers\Api\V1\AcademicYearRolloverController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->prefix('enterprise-operations/academic-year-rollovers')->group(function (): void {
    Route::post('/dry-run', [AcademicYearRolloverController::class, 'dryRun'])->middleware('permission:enterprise_operations.manage');
    Route::get('/{run}', [AcademicYearRolloverController::class, 'show'])->middleware('permission:enterprise_operations.view');
    Route::put('/{run}/overrides/{learnerId}', [AcademicYearRolloverController::class, 'override'])->middleware('permission:enterprise_operations.manage');
    Route::post('/{run}/submit', [AcademicYearRolloverController::class, 'submit'])->middleware('permission:enterprise_operations.manage');
    Route::post('/{run}/approve', [AcademicYearRolloverController::class, 'approve'])->middleware('permission:enterprise_operations.approve');
    Route::post('/{run}/execute', [AcademicYearRolloverController::class, 'execute'])->middleware('permission:enterprise_operations.manage');
    Route::post('/{run}/rollback', [AcademicYearRolloverController::class, 'rollback'])->middleware('permission:enterprise_operations.manage');
});
