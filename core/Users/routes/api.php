<?php

declare(strict_types=1);

use Core\Users\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by UsersServiceProvider. Every write action is
| gated by the 'core.users.manage' permission via the 'permission'
| route middleware — see docs/security/rbac.md.
*/

Route::middleware(['auth:sanctum'])->prefix('users')->name('users.')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:core.users.view')
        ->name('index');

    Route::post('/', [UserController::class, 'store'])
        ->middleware('permission:core.users.manage')
        ->name('store');

    Route::get('/{user}', [UserController::class, 'show'])
        ->middleware('permission:core.users.view')
        ->name('show');

    Route::patch('/{user}', [UserController::class, 'update'])
        ->middleware('permission:core.users.manage')
        ->name('update');

    Route::post('/{user}/suspend', [UserController::class, 'suspend'])
        ->middleware('permission:core.users.manage')
        ->name('suspend');

    Route::post('/{user}/reactivate', [UserController::class, 'reactivate'])
        ->middleware('permission:core.users.manage')
        ->name('reactivate');

    Route::post('/{user}/unlock', [UserController::class, 'unlock'])
        ->middleware('permission:core.users.manage')
        ->name('unlock');

    Route::delete('/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:core.users.manage')
        ->name('destroy');
});
