<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\Api\V1\ReportController;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->controller(ReportController::class)->group(function (): void {
    Route::get('/grading-scales', 'scales');
    Route::post('/grading-scales', 'saveScale');
    Route::get('/reporting-periods', 'periods');
    Route::post('/reporting-periods', 'savePeriod');
    Route::get('/report-card-templates', 'templates');
    Route::post('/report-card-templates', 'saveTemplate');
    Route::get('/report-cards', 'index');
    Route::post('/report-cards/generate', 'generate');
    Route::get('/report-cards/export', 'export');
    Route::get('/learners/{learner}/report-cards', 'learnerHistory');
    Route::middleware('report-resource.context')->group(function (): void {
        Route::get('/grading-scales/{scale}', 'showScale');
        Route::patch('/grading-scales/{scale}', 'saveScale');
        foreach (['activate', 'deactivate', 'default'] as $a) {
            Route::post('/grading-scales/{scale}/'.$a, fn ($scale, ReportController $c, Request $r) => $c->scaleState($r, $scale, $a));
        }
        Route::get('/reporting-periods/{period}', 'showPeriod');
        Route::patch('/reporting-periods/{period}', 'savePeriod');
        foreach (['open', 'close', 'archive'] as $a) {
            Route::post('/reporting-periods/{period}/'.$a, fn ($period, ReportController $c, Request $r) => $c->periodState($r, $period, $a));
        }
        Route::patch('/report-card-templates/{template}', 'saveTemplate');
        Route::post('/report-card-templates/{template}/default', 'defaultTemplate');
        Route::get('/report-cards/{reportCard}', 'show');
        Route::post('/report-cards/{reportCard}/regenerate', 'regenerate');
        Route::post('/report-cards/{reportCard}/comments', 'comments');
        Route::get('/report-cards/{reportCard}/pdf', 'pdf');
        foreach (['review', 'approve', 'publish', 'withdraw'] as $a) {
            Route::post('/report-cards/{reportCard}/'.$a, fn ($reportCard, ReportController $c, Request $r) => $c->lifecycle($r, $reportCard, $a));
        }
    });
});
