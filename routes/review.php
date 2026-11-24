<?php

use App\Http\Controllers\Review\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM Review Environment routes
|--------------------------------------------------------------------------
|
| Registered from bootstrap/app.php only when config('review.enabled') is true,
| so a production deploy has no /review surface at all. Read-only by design:
| there is no POST route here and none should ever be added.
|
*/

Route::middleware(['auth', 'password.settled'])
    ->prefix('review')
    ->name('review.')
    ->group(function () {
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/health', [ReviewController::class, 'health'])->name('health');
        Route::get('/preview/{key}', [ReviewController::class, 'preview'])->name('preview');
    });
