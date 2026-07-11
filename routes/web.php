<?php

declare(strict_types=1);

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

Route::view('/', 'welcome')->name('home');
