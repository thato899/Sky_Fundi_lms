<?php

declare(strict_types=1);

use Core\RBAC\Http\Controllers\Api\V1\PermissionController;
use Core\RBAC\Http\Controllers\Api\V1\RoleController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 by RBACServiceProvider. See docs/security/rbac.md.
*/

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:core.roles.manage')->name('index');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:core.roles.manage')->name('store');
        Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:core.roles.manage')->name('show');
        Route::put('/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:core.permissions.manage')->name('sync-permissions');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:core.roles.manage')->name('destroy');
    });

    Route::prefix('permissions')->name('permissions.')->group(function (): void {
        Route::get('/', [PermissionController::class, 'index'])->middleware('permission:core.roles.manage')->name('index');
    });

    Route::prefix('users/{user}/roles')->name('users.roles.')->group(function (): void {
        Route::post('/', [RoleController::class, 'assignToUser'])->middleware('permission:core.roles.manage')->name('assign');
        Route::delete('/{role}', [RoleController::class, 'revokeFromUser'])->middleware('permission:core.roles.manage')->name('revoke');
    });
});
