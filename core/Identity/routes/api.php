<?php

declare(strict_types=1);

use Core\Identity\Http\Controllers\Api\V1\MembershipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'account.not-locked'])->prefix('identity')->name('identity.')->group(function (): void {
    Route::get('/memberships', [MembershipController::class, 'index'])->name('memberships.index');
    Route::post('/memberships/invite', [MembershipController::class, 'invite'])->middleware('permission:organizations.users.manage')->name('memberships.invite');
    Route::post('/memberships/{membership}/accept', [MembershipController::class, 'accept'])->name('memberships.accept');
    Route::post('/memberships/{membership}/reject', [MembershipController::class, 'reject'])->name('memberships.reject');
    Route::post('/memberships/{membership}/switch', [MembershipController::class, 'switch'])->name('memberships.switch');
    Route::get('/context', [MembershipController::class, 'current'])->middleware('organization.context')->name('context');
});
