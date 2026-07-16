<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Scheduling\Http\Controllers\Web\SchedulingWebController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->group(function (): void {
    Route::get('/scheduling', [SchedulingWebController::class, 'dashboard'])->name('scheduling.dashboard');
    Route::get('/scheduling/timetable', [SchedulingWebController::class, 'timetable'])->name('scheduling.timetable');
    Route::get('/scheduling/lessons', [SchedulingWebController::class, 'lessons'])->name('scheduling.lessons');
    Route::get('/scheduling/rooms', [SchedulingWebController::class, 'rooms'])->name('scheduling.rooms');
    Route::get('/scheduling/templates', [SchedulingWebController::class, 'templates'])->name('scheduling.templates');
    Route::get('/scheduling/calendar', fn () => redirect()->route('academics.calendar.index'))->name('scheduling.calendar');
    Route::get('/scheduling/periods', fn () => redirect('/academics/timetable-periods'))->name('scheduling.periods');
});
