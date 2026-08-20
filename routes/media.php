<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Generic Media Routes
|--------------------------------------------------------------------------
|
| Session-authenticated media endpoints for any media-enabled owner entity.
| Per-id endpoints are addressed as {module}/{entity}/{id} and reuse the owner
| entity's CRUD ACL permissions (select for reads, update for writes), with the
| owner record resolved through the caller's row-level ACL.
|
| The token-keyed pending bucket backs CREATE forms (no owner id yet): its
| literal `pending`/`claim` segments would otherwise be swallowed by the generic
| {module}/{entity}/{id} routes, so they are registered FIRST — Laravel matches
| in registration order with no notion of specificity, so the literal-prefixed
| routes win. Registered inside the /crud web group, before the domain-action
| catch-all: the extra {id} segment avoids that collision too.
|
*/

Route::controller(MediaController::class)->name('media.')->group(function (): void {
    // Pending bucket + claim. Literal-prefixed, registered before the generic {id} routes.
    Route::post('/media/pending/{module}/{entity}', 'uploadPending')->name('pending.upload');
    Route::get('/media/pending/{module}/{entity}', 'listPending')->name('pending.list');
    Route::delete('/media/pending/{module}/{entity}/{media}', 'deletePending')->name('pending.delete');
    Route::post('/media/claim/{module}/{entity}/{id}', 'claim')->name('claim');

    // Per-id owner endpoints. Registered after the literal-prefixed routes above.
    Route::get('/media/{module}/{entity}/{id}', 'list')->name('list');
    Route::post('/media/{module}/{entity}/{id}', 'upload')->name('upload');
    Route::delete('/media/{module}/{entity}/{id}/{media}', 'delete')->name('delete');
});
