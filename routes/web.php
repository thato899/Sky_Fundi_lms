<?php

declare(strict_types=1);

use App\Http\Controllers\BillingController;
use App\Http\Controllers\HackathonSubscriptionController;
use App\Http\Controllers\OrganizationDashboardController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Controllers\TwoFactorController;
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

    // Mid-login second-factor steps — reached only via the pending-login
    // session state WebAuthController sets after a valid password, not
    // via Auth::login(). See TwoFactorChallengeController/TwoFactorController.
    Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
        Route::get('/challenge', [TwoFactorChallengeController::class, 'create'])->name('challenge.create');
        Route::post('/challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:5,1')->name('challenge.store');
        Route::get('/setup', [TwoFactorController::class, 'enroll'])->name('setup.create');
    });
});

// Two-factor enrollment is reachable both mid-login (a forced setup, guest
// above) and from an authenticated user's own security settings (below) —
// TwoFactorController::resolveUser() tells the two apart. confirm() and
// recoveryCodes() sit outside both middleware groups for that reason: they
// must work whichever entry point led here.
Route::post('/two-factor/enroll', [TwoFactorController::class, 'confirm'])
    ->middleware('throttle:10,1')->name('two-factor.enroll.confirm');
Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');

Route::middleware(['auth', 'account.not-locked'])->group(function (): void {
    Route::post('/logout', [WebAuthController::class, 'destroy'])->name('logout');
    Route::get('/access', [WebEntryController::class, 'access'])->name('access');
    Route::post('/access/organization', [WebEntryController::class, 'selectOrganization'])->name('access.organization');
    Route::get('/dashboard', OrganizationDashboardController::class)
        ->middleware('organization.context')
        ->name('dashboard');
    Route::get('/subscription', HackathonSubscriptionController::class)
        ->middleware('organization.context')
        ->name('subscription.dashboard');
    Route::middleware('organization.context')->prefix('billing')->name('billing.')->group(function (): void {
        Route::get('/', [BillingController::class, 'index'])->name('dashboard');
        Route::post('/subscribe', [BillingController::class, 'subscribe'])->name('subscribe');
        Route::post('/invoices/{invoice}/pay', [BillingController::class, 'payInvoice'])->name('invoices.pay');
    });
    Route::prefix('security/two-factor')->name('security.two-factor.')->group(function (): void {
        Route::get('/', [TwoFactorController::class, 'edit'])->name('edit');
        // Voluntary entry point into the same enroll()/confirm() pair the
        // guest, forced-setup path uses — see two-factor.setup.create above.
        Route::get('/enroll', [TwoFactorController::class, 'enroll'])->name('enroll');
        Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
        Route::post('/disable', [TwoFactorController::class, 'disable'])->name('disable');
    });
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
