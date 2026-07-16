<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Controllers\Api\V1\SchedulingController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->prefix('scheduling')->group(function (): void {
    Route::get('/rooms', [SchedulingController::class, 'rooms']);
    Route::post('/rooms', [SchedulingController::class, 'storeRoom']);
    Route::get('/templates', [SchedulingController::class, 'templateIndex']);
    Route::post('/templates', [SchedulingController::class, 'storeTemplate']);
    Route::get('/lessons', [SchedulingController::class, 'lessonIndex']);
    Route::post('/lessons', [SchedulingController::class, 'storeLesson']);
    Route::get('/conflicts', [SchedulingController::class, 'conflictIndex']);
    Route::get('/export', [SchedulingController::class, 'export']);
    Route::middleware('scheduling.context')->group(function (): void {
        Route::get('/rooms/{room}', [SchedulingController::class, 'showRoom']);
        Route::patch('/rooms/{room}', [SchedulingController::class, 'updateRoom']);
        Route::post('/rooms/{room}/activate', fn (Request $r, $room, SchedulingController $c) => $c->toggleRoom($r, $room, true));
        Route::post('/rooms/{room}/deactivate', fn (Request $r, $room, SchedulingController $c) => $c->toggleRoom($r, $room, false));
        Route::get('/templates/{template}', [SchedulingController::class, 'showTemplate']);
        Route::post('/templates/{template}/entries', [SchedulingController::class, 'addEntry']);
        Route::post('/templates/{template}/activate', [SchedulingController::class, 'activate']);
        Route::post('/templates/{template}/materialize', [SchedulingController::class, 'materialize']);
        Route::get('/lessons/{lesson}', [SchedulingController::class, 'showLesson']);
        Route::post('/lessons/{lesson}/assign-staff', [SchedulingController::class, 'assignStaff']);
        Route::post('/lessons/{lesson}/reschedule', [SchedulingController::class, 'reschedule']);
        Route::post('/lessons/{lesson}/cancel', [SchedulingController::class, 'cancel']);
        Route::post('/lessons/{lesson}/complete', fn (Request $r, $lesson, SchedulingController $c) => $c->complete($r, $lesson));
        Route::post('/lessons/{lesson}/missed', fn (Request $r, $lesson, SchedulingController $c) => $c->complete($r, $lesson, true));
        Route::post('/lessons/{lesson}/attendance-session', [SchedulingController::class, 'attendance']);
    });
});
