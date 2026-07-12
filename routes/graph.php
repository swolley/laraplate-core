<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\GraphController;

Route::controller(GraphController::class)->prefix('graph')->name('graph.')->group(function (): void {
    Route::get('/expand/{module}/{entity}/{id}', 'expand')->name('expand');
});
