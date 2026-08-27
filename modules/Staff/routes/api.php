<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Api\V1\OrganizationTeachingAssignmentController;
use Modules\Staff\Http\Controllers\Api\V1\StaffController;
use Modules\Staff\Http\Controllers\Api\V1\TeachingAssignmentController;
use Modules\Staff\Infrastructure\Models\StaffProfile;

// Organization-scoped, so authorized via PermissionResolver (membership roles)
// inside each controller/request rather than the platform-wide `permission:`
// middleware — see Core\Identity\Application\PermissionResolver. Previously
// used `permission:staff.*` here, which checks Core\RBAC direct user-to-role
// assignment instead of organization Membership roles: an ordinary org-scoped
// Organization Administrator could not actually call these routes. Fixed;
// see docs/roadmap.md "In progress" for the discovery note.
Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->prefix('staff')->group(function (): void {
    Route::get('/', [StaffController::class, 'index']);
    Route::post('/', [StaffController::class, 'store']);
    Route::get('/{staff}/teaching-assignments', [TeachingAssignmentController::class, 'index']);
    Route::post('/{staff}/teaching-assignments', [TeachingAssignmentController::class, 'store']);
    Route::post('/{staff}/teaching-assignments/{assignment}/end', [TeachingAssignmentController::class, 'end']);
    Route::patch('/{staff}', [StaffController::class, 'update']);
    foreach (['activate', 'suspend', 'archive', 'restore'] as $status) {
        Route::post('/{staff}/'.$status, fn (StaffController $c, Request $r, StaffProfile $staff) => $c->status($r, $staff, $status));
    }
});

// Organization-scoped, so authorized via PermissionResolver (membership roles)
// inside the controller/request rather than the platform-wide `permission:`
// middleware used above — see Core\Identity\Application\PermissionResolver.
Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->prefix('teaching-assignments')->group(function (): void {
    Route::get('/', [OrganizationTeachingAssignmentController::class, 'index']);
    Route::post('/bulk', [OrganizationTeachingAssignmentController::class, 'bulkStore']);
});
