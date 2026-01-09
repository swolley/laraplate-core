<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\DocsController;

Route::controller(DocsController::class)->name('docs.')->group(function (): void {
    if (App::isLocal()) {
        Route::get('/phpinfo', 'phpinfo')->name('phpinfo');
    }

    Route::get('swagger/{filename}', 'mergeDocs')->name('swaggerDocs')->where('filename', 'v\d+');
});
