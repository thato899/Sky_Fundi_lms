<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\Api\V1\ReportController;
use Modules\Reports\Http\Controllers\Web\ReportWebController;

Route::middleware(['auth', 'account.not-locked', 'organization.context'])->group(function (): void {
    Route::get('/reports', [ReportWebController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/directory', [ReportWebController::class, 'directory'])->name('reports.index');
    Route::get('/reports/generate', [ReportWebController::class, 'generateForm'])->name('reports.generate.form');
    Route::post('/reports/generate', [ReportWebController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/grading-scales', [ReportWebController::class, 'scales'])->name('reports.scales');
    Route::post('/reports/grading-scales', [ReportWebController::class, 'saveScale'])->name('reports.scales.store');
    Route::get('/reports/reporting-periods', [ReportWebController::class, 'periods'])->name('reports.periods');
    Route::post('/reports/reporting-periods', [ReportWebController::class, 'savePeriod'])->name('reports.periods.store');
    Route::get('/reports/templates', [ReportWebController::class, 'templates'])->name('reports.templates');
    Route::post('/reports/templates', [ReportWebController::class, 'saveTemplate'])->name('reports.templates.store');
    Route::get('/my/report-cards', [ReportWebController::class, 'myReportCards'])->name('reports.my');
    Route::get('/learners/{learner}/report-cards', [ReportWebController::class, 'learnerHistory'])->middleware('learner.context')->name('learners.report-cards');
    Route::middleware('report-resource.context')->group(function (): void {
        Route::patch('/reports/grading-scales/{scale}', [ReportWebController::class, 'saveScale'])->name('reports.scales.update');
        foreach (['activate', 'deactivate', 'default'] as $a) {
            Route::post('/reports/grading-scales/{scale}/'.$a, fn ($scale, ReportWebController $c, Request $r) => $c->scaleState($r, $scale, $a))->name('reports.scales.'.$a);
        }
        Route::patch('/reports/reporting-periods/{period}', [ReportWebController::class, 'savePeriod'])->name('reports.periods.update');
        foreach (['open', 'close', 'archive'] as $a) {
            Route::post('/reports/reporting-periods/{period}/'.$a, fn ($period, ReportWebController $c, Request $r) => $c->periodState($r, $period, $a))->name('reports.periods.'.$a);
        }
        Route::patch('/reports/templates/{template}', [ReportWebController::class, 'saveTemplate'])->name('reports.templates.update');
        Route::post('/reports/templates/{template}/default', [ReportWebController::class, 'defaultTemplate'])->name('reports.templates.default');
        Route::get('/reports/{reportCard}', [ReportWebController::class, 'show'])->name('reports.show');
        Route::post('/reports/{reportCard}/comments', [ReportWebController::class, 'comments'])->name('reports.comments');
        Route::post('/reports/{reportCard}/regenerate', [ReportWebController::class, 'regenerate'])->name('reports.regenerate');
        Route::get('/reports/{reportCard}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        foreach (['review', 'approve', 'publish', 'withdraw'] as $a) {
            Route::post('/reports/{reportCard}/'.$a, fn ($reportCard, ReportWebController $c, Request $r) => $c->lifecycle($r, $reportCard, $a))->name('reports.'.$a);
        }
    });
    Route::get('/reports-export.csv', [ReportController::class, 'export'])->name('reports.csv');
});
