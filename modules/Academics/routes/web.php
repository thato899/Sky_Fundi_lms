<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academics\Http\Controllers\Web\AcademicManagementController;

Route::middleware(['auth', 'account.not-locked', 'organization.context', 'academics.organization'])
    ->prefix('academics')->name('academics.web.')->controller(AcademicManagementController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/settings', 'settings')->name('settings');

        Route::get('/academic-years', 'years')->name('years.index');
        Route::get('/academic-years/create', 'yearCreate')->name('years.create');
        Route::post('/academic-years', 'yearStore')->name('years.store');
        Route::get('/academic-years/{year}', 'yearShow')->name('years.show');
        Route::get('/academic-years/{year}/edit', 'yearEdit')->name('years.edit');
        Route::put('/academic-years/{year}', 'yearUpdate')->name('years.update');
        Route::post('/academic-years/{year}/set-current', 'yearCurrent')->name('years.current');
        Route::post('/academic-years/{year}/close', 'yearClose')->name('years.close');
        Route::post('/academic-years/{year}/archive', 'yearArchive')->name('years.archive');

        Route::get('/academic-years/{year}/terms', 'terms')->name('terms.index');
        Route::get('/academic-years/{year}/terms/create', 'termCreate')->name('terms.create');
        Route::post('/academic-years/{year}/terms', 'termStore')->name('terms.store');
        Route::get('/academic-years/{year}/terms/{term}/edit', 'termEdit')->name('terms.edit');
        Route::put('/academic-years/{year}/terms/{term}', 'termUpdate')->name('terms.update');
        Route::post('/academic-years/{year}/terms/{term}/set-current', 'termCurrent')->name('terms.current');

        Route::get('/academic-years/{year}/calendar', 'calendar')->name('calendar.index');
        Route::get('/academic-years/{year}/calendar/create', 'calendarCreate')->name('calendar.create');
        Route::post('/academic-years/{year}/calendar', 'calendarStore')->name('calendar.store');
        Route::get('/academic-years/{year}/calendar/{entry}/edit', 'calendarEdit')->name('calendar.edit');
        Route::put('/academic-years/{year}/calendar/{entry}', 'calendarUpdate')->name('calendar.update');
        Route::delete('/academic-years/{year}/calendar/{entry}', 'calendarDestroy')->name('calendar.destroy');

        foreach (['curricula', 'grades', 'classes', 'departments', 'subjects', 'timetable-periods'] as $area) {
            Route::get("/{$area}", 'catalog')->defaults('area', $area)->name("{$area}.index");
            Route::get("/{$area}/create", 'catalogCreate')->defaults('area', $area)->name("{$area}.create");
            Route::post("/{$area}", 'catalogStore')->defaults('area', $area)->name("{$area}.store");
            Route::get("/{$area}/{record}", 'catalogShow')->defaults('area', $area)->name("{$area}.show");
            Route::get("/{$area}/{record}/edit", 'catalogEdit')->defaults('area', $area)->name("{$area}.edit");
            Route::put("/{$area}/{record}", 'catalogUpdate')->defaults('area', $area)->name("{$area}.update");
        }
        Route::post('/curricula/{record}/deactivate', 'curriculumDeactivate')->name('curricula.deactivate');
        Route::post('/curricula/{record}/reactivate', 'curriculumReactivate')->name('curricula.reactivate');
        Route::post('/grades/reorder', 'gradesReorder')->name('grades.reorder');
    });
