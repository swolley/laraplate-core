<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\GraphController;

Route::controller(GraphController::class)->prefix('graph')->name('graph.')->group(function (): void {
    Route::get('/search/{module}/{entity}', 'search')->name('search');
    Route::get('/stats/{module}/{entity}/{id}', 'stats')->name('stats');
    Route::get('/expand/{module}/{entity}/{id}', 'expand')->name('expand');
});
