<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academics\Http\Controllers\Api\V1\AcademicTermController;
use Modules\Academics\Http\Controllers\Api\V1\AcademicYearController;
use Modules\Academics\Http\Controllers\Api\V1\CalendarEntryController;
use Modules\Academics\Http\Controllers\Api\V1\ClassController;
use Modules\Academics\Http\Controllers\Api\V1\CurriculumController;
use Modules\Academics\Http\Controllers\Api\V1\DepartmentController;
use Modules\Academics\Http\Controllers\Api\V1\EducationSettingsController;
use Modules\Academics\Http\Controllers\Api\V1\GradeController;
use Modules\Academics\Http\Controllers\Api\V1\SubjectController;
use Modules\Academics\Http\Controllers\Api\V1\TimetablePeriodController;

/*
| Mounted under /api/v1/academics by AcademicsServiceProvider. Follows
| the same conventions as every Core service route file — see
| docs/api/conventions.md. All routes require authentication;
| individual actions are further gated by the academics.* permissions
| declared in module.json.
*/

Route::middleware(['auth:sanctum', 'account.not-locked'])->prefix('academics')->name('academics.')->group(function (): void {

    Route::prefix('curricula')->name('curricula.')->group(function (): void {
        Route::get('/', [CurriculumController::class, 'index'])->middleware('permission:academics.curriculum.view')->name('index');
        Route::post('/', [CurriculumController::class, 'store'])->middleware('permission:academics.curriculum.manage')->name('store');
        Route::get('/{curriculum}', [CurriculumController::class, 'show'])->middleware('permission:academics.curriculum.view')->name('show');
        Route::put('/{curriculum}', [CurriculumController::class, 'update'])->middleware('permission:academics.curriculum.manage')->name('update');
        Route::post('/{curriculum}/deactivate', [CurriculumController::class, 'deactivate'])->middleware('permission:academics.curriculum.manage')->name('deactivate');
    });

    Route::prefix('departments')->name('departments.')->group(function (): void {
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:academics.departments.view')->name('index');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:academics.departments.manage')->name('store');
        Route::get('/{department}', [DepartmentController::class, 'show'])->middleware('permission:academics.departments.view')->name('show');
        Route::put('/{department}', [DepartmentController::class, 'update'])->middleware('permission:academics.departments.manage')->name('update');
    });

    Route::prefix('academic-years')->name('academic-years.')->group(function (): void {
        Route::get('/', [AcademicYearController::class, 'index'])->middleware('permission:academics.academic-years.view')->name('index');
        Route::post('/', [AcademicYearController::class, 'store'])->middleware('permission:academics.academic-years.manage')->name('store');
        Route::get('/{academicYear}', [AcademicYearController::class, 'show'])->middleware('permission:academics.academic-years.view')->name('show');
        Route::put('/{academicYear}', [AcademicYearController::class, 'update'])->middleware('permission:academics.academic-years.manage')->name('update');
        Route::post('/{academicYear}/set-current', [AcademicYearController::class, 'setCurrent'])->middleware('permission:academics.academic-years.manage')->name('set-current');
        Route::post('/{academicYear}/close', [AcademicYearController::class, 'close'])->middleware('permission:academics.academic-years.manage')->name('close');
        Route::post('/{academicYear}/archive', [AcademicYearController::class, 'archive'])->middleware('permission:academics.academic-years.manage')->name('archive');

        Route::prefix('{academicYear}/terms')->name('terms.')->group(function (): void {
            Route::get('/', [AcademicTermController::class, 'index'])->middleware('permission:academics.terms.view')->name('index');
            Route::post('/', [AcademicTermController::class, 'store'])->middleware('permission:academics.terms.manage')->name('store');
            Route::put('/{term}', [AcademicTermController::class, 'update'])->middleware('permission:academics.terms.manage')->name('update');
            Route::post('/{term}/set-current', [AcademicTermController::class, 'setCurrent'])->middleware('permission:academics.terms.manage')->name('set-current');
        });

        Route::prefix('{academicYear}/calendar-entries')->name('calendar-entries.')->group(function (): void {
            Route::get('/', [CalendarEntryController::class, 'index'])->middleware('permission:academics.calendar.view')->name('index');
            Route::post('/', [CalendarEntryController::class, 'store'])->middleware('permission:academics.calendar.manage')->name('store');
            Route::put('/{entry}', [CalendarEntryController::class, 'update'])->middleware('permission:academics.calendar.manage')->name('update');
            Route::delete('/{entry}', [CalendarEntryController::class, 'destroy'])->middleware('permission:academics.calendar.manage')->name('destroy');
        });
    });

    Route::prefix('grades')->name('grades.')->group(function (): void {
        Route::get('/', [GradeController::class, 'index'])->middleware('permission:academics.grades.view')->name('index');
        Route::post('/', [GradeController::class, 'store'])->middleware('permission:academics.grades.manage')->name('store');
        Route::post('/reorder', [GradeController::class, 'reorder'])->middleware('permission:academics.grades.manage')->name('reorder');
        Route::get('/{grade}', [GradeController::class, 'show'])->middleware('permission:academics.grades.view')->name('show');
        Route::put('/{grade}', [GradeController::class, 'update'])->middleware('permission:academics.grades.manage')->name('update');
        Route::put('/{grade}/curriculum', [GradeController::class, 'assignCurriculum'])->middleware('permission:academics.curriculum.manage')->name('assign-curriculum');
    });

    Route::prefix('classes')->name('classes.')->group(function (): void {
        Route::get('/', [ClassController::class, 'index'])->middleware('permission:academics.classes.view')->name('index');
        Route::post('/', [ClassController::class, 'store'])->middleware('permission:academics.classes.manage')->name('store');
        Route::get('/{class}', [ClassController::class, 'show'])->middleware('permission:academics.classes.view')->name('show');
        Route::put('/{class}', [ClassController::class, 'update'])->middleware('permission:academics.classes.manage')->name('update');
    });

    Route::prefix('subjects')->name('subjects.')->group(function (): void {
        Route::get('/', [SubjectController::class, 'index'])->middleware('permission:academics.subjects.view')->name('index');
        Route::post('/', [SubjectController::class, 'store'])->middleware('permission:academics.subjects.manage')->name('store');
        Route::get('/{subject}', [SubjectController::class, 'show'])->middleware('permission:academics.subjects.view')->name('show');
        Route::put('/{subject}', [SubjectController::class, 'update'])->middleware('permission:academics.subjects.manage')->name('update');
        Route::put('/{subject}/curriculum', [SubjectController::class, 'assignCurriculum'])->middleware('permission:academics.curriculum.manage')->name('assign-curriculum');
        Route::put('/{subject}/department', [SubjectController::class, 'assignDepartment'])->middleware('permission:academics.subjects.manage')->name('assign-department');
    });

    Route::prefix('timetable-periods')->name('timetable-periods.')->group(function (): void {
        Route::get('/', [TimetablePeriodController::class, 'index'])->middleware('permission:academics.timetable.view')->name('index');
        Route::post('/', [TimetablePeriodController::class, 'store'])->middleware('permission:academics.timetable.manage')->name('store');
        Route::put('/{timetablePeriod}', [TimetablePeriodController::class, 'update'])->middleware('permission:academics.timetable.manage')->name('update');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', [EducationSettingsController::class, 'index'])->middleware('permission:academics.academic-years.view')->name('index');
        Route::put('/', [EducationSettingsController::class, 'update'])->middleware('permission:academics.academic-years.manage')->name('update');
    });
});
