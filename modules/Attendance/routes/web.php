<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\Api\V1\AttendanceController;
use Modules\Attendance\Http\Controllers\Web\AttendanceWebController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->group(function (): void {
    Route::get('/learners/{learner}/attendance', [AttendanceWebController::class, 'learnerHistory'])->middleware('learner.context')->name('learners.attendance');
    Route::prefix('attendance')->name('attendance.')->group(function (): void {
        Route::get('/', [AttendanceWebController::class, 'index'])->name('index');
        Route::get('/create', [AttendanceWebController::class, 'create'])->name('create');
        Route::post('/', [AttendanceWebController::class, 'store'])->name('store');
        Route::middleware('attendance.context')->group(function (): void {
            Route::get('/{session}', [AttendanceWebController::class, 'show'])->name('register');
            Route::post('/{session}/register', [AttendanceWebController::class, 'register'])->name('register.store');
            Route::post('/{session}/finalize', [AttendanceWebController::class, 'finalize'])->name('finalize');
            Route::post('/{session}/reopen', [AttendanceWebController::class, 'reopen'])->name('reopen');
            Route::post('/{session}/cancel', [AttendanceWebController::class, 'cancel'])->name('cancel');
            Route::get('/{session}/export', [AttendanceController::class, 'export'])->name('export');
        });
    });
});
