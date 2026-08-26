<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Materials\Http\Controllers\Api\V1\MaterialController;
use Modules\Materials\Http\Controllers\Api\V1\TutorChatController;

/*
| Mounted under /api/v1 by MaterialsServiceProvider — see
| modules/Materials/README.md.
*/

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])
    ->prefix('materials')->name('materials.')->group(function (): void {
        Route::get('/', [MaterialController::class, 'index'])->name('index');
        Route::post('/', [MaterialController::class, 'store'])->name('store');
        Route::get('/{material}', [MaterialController::class, 'show'])->name('show');
        Route::delete('/{material}', [MaterialController::class, 'destroy'])->name('destroy');
        Route::post('/tutor-chat', [TutorChatController::class, 'ask'])->middleware('throttle:20,1')->name('tutor-chat');
    });
