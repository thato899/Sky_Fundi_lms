<?php

declare(strict_types=1);

use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Sky Fundi is API-first (see docs/api/conventions.md). This file only
| carries the minimal Blade entry point(s); all real functionality is
| exposed through routes/api.php and each Core service's own
| routes/api.php, consumed by Blade views via the API — never bypassed.
|
*/

Route::get('/', [WebEntryController::class, 'home'])->name('home');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'create'])->name('login');
    Route::post('/login', [WebAuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', 'account.not-locked'])->group(function (): void {
    Route::post('/logout', [WebAuthController::class, 'destroy'])->name('logout');
    Route::get('/access', [WebEntryController::class, 'access'])->name('access');
    Route::post('/access/organization', [WebEntryController::class, 'selectOrganization'])->name('access.organization');
    Route::get('/dashboard', OrganizationDashboardController::class)
        ->middleware('organization.context')
        ->name('dashboard');
});

Route::middleware(['auth', 'account.not-locked', 'permission:core.roles.manage'])->prefix('super-admin')->name('super-admin.')->group(function (): void {
    Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/organizations', [SuperAdminController::class, 'organizations'])->name('organizations');
    Route::get('/organizations/wizard', [SuperAdminController::class, 'wizard'])->name('organizations.wizard');
    Route::get('/users', [SuperAdminController::class, 'users'])->name('users');
    Route::get('/roles', [SuperAdminController::class, 'roles'])->name('roles');
    Route::get('/modules', [SuperAdminController::class, 'modules'])->name('modules');
    Route::get('/ai', [SuperAdminController::class, 'ai'])->name('ai');
    Route::get('/audit', [SuperAdminController::class, 'audit'])->name('audit');
    Route::get('/health', [SuperAdminController::class, 'health'])->name('health');
});
