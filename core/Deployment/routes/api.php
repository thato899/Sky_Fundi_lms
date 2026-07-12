<?php

declare(strict_types=1);

use Core\Deployment\Http\Controllers\Api\V1\DeploymentProfileController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by DeploymentServiceProvider — see
| core/Deployment/README.md.
*/

Route::middleware(['auth:sanctum', 'permission:core.deployment.manage'])
    ->prefix('deployment-profiles')->name('deployment-profiles.')->group(function (): void {
        Route::get('/', [DeploymentProfileController::class, 'index'])->name('index');
        Route::post('/', [DeploymentProfileController::class, 'store'])->name('store');
        Route::get('/{deploymentProfile}', [DeploymentProfileController::class, 'show'])->name('show');
        Route::put('/{deploymentProfile}', [DeploymentProfileController::class, 'update'])->name('update');
    });
