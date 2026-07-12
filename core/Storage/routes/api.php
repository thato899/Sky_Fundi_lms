<?php

declare(strict_types=1);

use Core\Storage\Http\Controllers\Api\V1\StorageProviderController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by StorageServiceProvider — see
| core/Storage/README.md.
*/

Route::middleware(['auth:sanctum', 'permission:core.settings.manage'])
    ->get('/storage/disks', [StorageProviderController::class, 'index'])
    ->name('storage.disks');
