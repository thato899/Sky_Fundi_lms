<?php

declare(strict_types=1);

use Core\Mail\Http\Controllers\Api\V1\MailProviderController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by MailServiceProvider — see
| core/Mail/README.md.
*/

Route::middleware(['auth:sanctum', 'permission:core.settings.manage'])
    ->get('/mail/providers', [MailProviderController::class, 'index'])
    ->name('mail.providers');
