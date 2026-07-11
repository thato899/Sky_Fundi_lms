<?php

declare(strict_types=1);

use Core\AuditLogs\Http\Controllers\Api\V1\AuditLogController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by AuditLogsServiceProvider. Read-only — see
| docs/security/README.md#audit-logs.
*/

Route::middleware(['auth:sanctum'])->prefix('audit-logs')->name('audit-logs.')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index'])
        ->middleware('permission:core.logs.view')
        ->name('index');
});
