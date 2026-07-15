<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\Api\V1\AttendanceController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->group(function (): void {
    Route::get('/learners/{learner}/attendance', [AttendanceController::class, 'learnerHistory']);
    Route::prefix('attendance')->name('api.attendance.')->group(function (): void {
        Route::get('/reports/summary', [AttendanceController::class, 'summary']);
        Route::get('/sessions', [AttendanceController::class, 'index']);
        Route::post('/sessions', [AttendanceController::class, 'store']);
        Route::middleware('attendance.context')->group(function (): void {
            Route::get('/sessions/{session}', [AttendanceController::class, 'show']);
            Route::patch('/sessions/{session}', [AttendanceController::class, 'update']);
            Route::post('/sessions/{session}/register', [AttendanceController::class, 'register']);
            Route::post('/sessions/{session}/finalize', [AttendanceController::class, 'finalize']);
            Route::post('/sessions/{session}/reopen', [AttendanceController::class, 'reopen']);
            Route::post('/sessions/{session}/cancel', [AttendanceController::class, 'cancel']);
            Route::get('/sessions/{session}/export', [AttendanceController::class, 'export']);
        });
    });
});
