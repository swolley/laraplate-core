<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CrudController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::name('crud.')->prefix('/crud')->group(function (): void {
    require __DIR__ . '/crud.php';

    require __DIR__ . '/graph.php';

    Route::controller(CrudController::class)->group(function (): void {
        Route::patch('/lock/{module}/{entity}', 'lock')->name('lock');
        Route::patch('/unlock/{module}/{entity}', 'unlock')->name('unlock');
        Route::patch('/approve/{module}/{entity}', 'approve')->name('approve');
        Route::patch('/disapprove/{module}/{entity}', 'disapprove')->name('disapprove');
        Route::get('/pending-approvals/{module}/{entity}', 'pendingApprovals')->name('pending-approvals');
        Route::get('/latest-disapproval/{module}/{entity}', 'latestDisapproval')->name('latest-disapproval');
        Route::patch('/activate/{module}/{entity}', 'activate')->name('activate');
        Route::patch('/inactivate/{module}/{entity}', 'inactivate')->name('inactivate');
        Route::delete('/cache-clear/{module}/{entity}', 'clearModelCache')->name('cache-clear');
    });

    // Generic media endpoints for any media-enabled entity. Registered before the
    // domain-action catch-all below; the extra {id} segment avoids any collision.
    require __DIR__ . '/media.php';

    // Generic bulk-import endpoints. Registered before the domain-action catch-all;
    // the literal `imports` prefix keeps them from being read as a domain verb.
    require __DIR__ . '/imports.php';

    // Last in the group on purpose. This is the catch-all for module-registered
    // domain verbs, and Laravel matches in registration order with no notion of
    // specificity, so every literal verb above must be tried first. The 
    // graph group carry an extra path segment and never reach it.
    Route::post('/{action}/{module}/{entity}', [CrudController::class, 'domainAction'])
        ->name('domain-action');
});

// In-app notification tray (user-scoped, outside the /crud group): `/app/notifications`.
require __DIR__ . '/notifications.php';
