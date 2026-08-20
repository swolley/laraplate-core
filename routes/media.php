<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Generic Media Routes
|--------------------------------------------------------------------------
|
| Session-authenticated media endpoints for any media-enabled owner entity,
| addressed as {module}/{entity}/{id}. They reuse the owner entity's CRUD ACL
| permissions (select for reads, update for writes). Registered inside the
| /crud web group, before the domain-action catch-all: the extra {id} segment
| keeps them from colliding with the 2-param catch-all.
|
*/

Route::controller(MediaController::class)->name('media.')->group(function (): void {
    Route::get('/media/{module}/{entity}/{id}', 'list')->name('list');
    Route::post('/media/{module}/{entity}/{id}', 'upload')->name('upload');
    Route::delete('/media/{module}/{entity}/{id}/{media}', 'delete')->name('delete');
});
