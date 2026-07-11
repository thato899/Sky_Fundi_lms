<?php

declare(strict_types=1);

use Core\Modules\Http\Controllers\Api\V1\ModuleController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by ModulesServiceProvider. See
| docs/architecture/module-system.md.
*/

Route::middleware(['auth:sanctum'])->prefix('modules')->name('modules.')->group(function (): void {
    Route::get('/', [ModuleController::class, 'index'])->middleware('permission:core.modules.manage')->name('index');
    Route::post('/', [ModuleController::class, 'install'])->middleware('permission:core.modules.manage')->name('install');
    Route::post('/{name}/enable', [ModuleController::class, 'enable'])->middleware('permission:core.modules.manage')->name('enable');
    Route::post('/{name}/disable', [ModuleController::class, 'disable'])->middleware('permission:core.modules.manage')->name('disable');
    Route::delete('/{name}', [ModuleController::class, 'destroy'])->middleware('permission:core.modules.manage')->name('destroy');
});
