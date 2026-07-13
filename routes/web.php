<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdminController;

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

Route::view('/', 'welcome')->name('home');
Route::middleware(['auth'])->prefix('super-admin')->name('super-admin.')->group(function (): void {
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
