<?php

declare(strict_types=1);

use Core\AIGateway\Http\Controllers\Api\V1\AIProviderController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by AIGatewayServiceProvider. Admin-only — see
| docs/ai/ai-gateway.md.
*/

Route::middleware(['auth:sanctum'])->prefix('ai/providers')->name('ai.providers.')->group(function (): void {
    Route::get('/', [AIProviderController::class, 'index'])
        ->middleware('permission:core.ai.manage')
        ->name('index');

    Route::post('/test', [AIProviderController::class, 'test'])
        ->middleware(['permission:core.ai.manage', 'throttle:10,1'])
        ->name('test');
});
