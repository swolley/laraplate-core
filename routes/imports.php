<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ImportSessionController;

/*
|--------------------------------------------------------------------------
| Generic Bulk Import Routes
|--------------------------------------------------------------------------
|
| Session-authenticated endpoints backing the SPA's interactive bulk import:
| list importable entities, upload a file, preview the mapping grid, save the
| mapping, launch the queued job, poll status, and download the failure report.
|
| The literal `/imports/entities` is registered before `/imports/{import}` so
| Laravel — which matches in registration order — does not treat "entities" as a
| session id.
|
*/

Route::controller(ImportSessionController::class)
    ->prefix('imports')
    ->name('imports.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/entities', 'entities')->name('entities');
        Route::post('/', 'store')->name('store');
        Route::get('/{import}', 'show')->name('show');
        Route::get('/{import}/preview', 'preview')->name('preview');
        Route::put('/{import}/mapping', 'saveMapping')->name('mapping');
        Route::post('/{import}/run', 'run')->name('run');
        Route::get('/{import}/errors', 'errors')->name('errors');
    });
