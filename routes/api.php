<?php

declare(strict_types=1);

use Core\Users\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Every Core service and module registers its own routes/api.php inside
| its own folder (see docs/architecture/module-system.md#module-anatomy)
| via that service's ServiceProvider, keeping this file as a thin,
| version-scoped mount point rather than a growing monolith.
|
| See docs/api/conventions.md for URL structure and versioning rules.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/me', fn () => new UserResource(Auth::user()))
        ->middleware(['auth:sanctum', 'account.not-locked'])
        ->name('me');
});
