<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Leaderboards\Http\Controllers\Web\LeaderboardController;
use Modules\Leaderboards\Http\Controllers\Web\SportspersonController;
use Modules\Leaderboards\Http\Controllers\Web\SportsRecordController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->prefix('leaderboards')->name('leaderboards.')->group(function (): void {
    Route::get('/', [LeaderboardController::class, 'index'])->name('index');
    Route::post('/academic', [LeaderboardController::class, 'storeAcademic'])->name('academic.store');
    Route::post('/sports', [LeaderboardController::class, 'storeSports'])->name('sports.store');
    // Must be registered before the {leaderboard} wildcard below, or
    // "mine" would be parsed as a leaderboard UUID.
    Route::get('/mine', [LeaderboardController::class, 'mine'])->name('mine');

    Route::prefix('sports-records')->name('sports-records.')->group(function (): void {
        Route::get('/', [SportsRecordController::class, 'index'])->name('index');
        Route::post('/', [SportsRecordController::class, 'store'])->name('store');
    });

    Route::prefix('sportsperson-of-the-week')->name('sportsperson.')->group(function (): void {
        Route::get('/', [SportspersonController::class, 'index'])->name('index');
        Route::post('/', [SportspersonController::class, 'store'])->name('store');
        Route::post('/{post}/publish', [SportspersonController::class, 'publish'])->name('publish');
    });

    Route::get('/{leaderboard}', [LeaderboardController::class, 'show'])->name('show');
    Route::post('/{leaderboard}/publish', [LeaderboardController::class, 'publish'])->name('publish');
    Route::post('/{leaderboard}/unpublish', [LeaderboardController::class, 'unpublish'])->name('unpublish');
});
