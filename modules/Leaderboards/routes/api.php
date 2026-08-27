<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Leaderboards\Http\Controllers\Api\V1\LeaderboardController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])
    ->prefix('leaderboards')->name('api.leaderboards.')->group(function (): void {
        Route::get('/', [LeaderboardController::class, 'index'])->name('index');
        Route::post('/academic', [LeaderboardController::class, 'storeAcademic'])->name('academic.store');
        Route::post('/sports', [LeaderboardController::class, 'storeSports'])->name('sports.store');
        Route::get('/mine', [LeaderboardController::class, 'mine'])->name('mine');
        Route::get('/{leaderboard}', [LeaderboardController::class, 'show'])->name('show');
        Route::post('/{leaderboard}/publish', [LeaderboardController::class, 'publish'])->name('publish');
        Route::post('/{leaderboard}/unpublish', [LeaderboardController::class, 'unpublish'])->name('unpublish');
    });
