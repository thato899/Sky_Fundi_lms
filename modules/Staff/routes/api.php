<?php

declare(strict_types=1);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\Api\V1\OrganizationTeachingAssignmentController;
use Modules\Staff\Http\Controllers\Api\V1\StaffController;
use Modules\Staff\Http\Controllers\Api\V1\TeachingAssignmentController;
use Modules\Staff\Infrastructure\Models\StaffProfile;

Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->prefix('staff')->group(function (): void {
    Route::get('/', [StaffController::class, 'index'])->middleware('permission:staff.view');
    Route::post('/', [StaffController::class, 'store'])->middleware('permission:staff.create');
    Route::get('/{staff}/teaching-assignments', [TeachingAssignmentController::class, 'index'])->middleware('permission:teaching_assignments.view');
    Route::post('/{staff}/teaching-assignments', [TeachingAssignmentController::class, 'store'])->middleware('permission:teaching_assignments.manage');
    Route::post('/{staff}/teaching-assignments/{assignment}/end', [TeachingAssignmentController::class, 'end'])->middleware('permission:teaching_assignments.manage');
    Route::patch('/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.update');
    foreach (['activate', 'suspend', 'archive', 'restore'] as $status) {
        Route::post('/{staff}/'.$status, fn (StaffController $c, Request $r, StaffProfile $staff) => $c->status($r, $staff, $status))->middleware('permission:staff.manage_employment');
    }
});

// Organization-scoped, so authorized via PermissionResolver (membership roles)
// inside the controller/request rather than the platform-wide `permission:`
// middleware used above — see Core\Identity\Application\PermissionResolver.
Route::middleware(['auth:sanctum', 'account.not-locked', 'organization.context'])->prefix('teaching-assignments')->group(function (): void {
    Route::get('/', [OrganizationTeachingAssignmentController::class, 'index']);
    Route::post('/bulk', [OrganizationTeachingAssignmentController::class, 'bulkStore']);
});
