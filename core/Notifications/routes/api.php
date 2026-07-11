<?php

declare(strict_types=1);

use Core\Notifications\Http\Controllers\Api\V1\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by NotificationsServiceProvider. Self-service —
| see core/Notifications/README.md.
*/

Route::middleware(['auth:sanctum'])->prefix('notifications/preferences')->name('notifications.preferences.')->group(function (): void {
    Route::get('/', [NotificationPreferenceController::class, 'index'])->name('index');
    Route::put('/', [NotificationPreferenceController::class, 'update'])->name('update');
});
