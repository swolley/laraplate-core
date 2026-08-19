<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CrudController;
use Modules\Core\Http\Controllers\GridsController;

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

    // Grid routes mirror the CRUD verbs above on a different URI prefix, so they need their own
    // name prefix: duplicate route names break `route:cache` serialization, and `route()` keeps
    // resolving to the first route registered under the name, leaving the later one unreachable.
    Route::controller(GridsController::class)->prefix('grid')->name('grids.')->group(function (): void {
        Route::get('/configs/{module}/{entity?}', 'getGridsConfigs')->name('getGridsConfigs');
        // Route::match(['get', 'post', 'patch', 'delete'], '/{entity}', 'grid')->name('grids.grid');
        Route::match(['get', 'post'], '/select/{module}/{entity}', 'grid')->name('select');
        Route::match(['get', 'post'], '/data/{module}/{entity}', 'grid')->name('data');
        Route::get('/check/{module}/{entity}', 'grid')->name('check');
        Route::match(['get', 'post', 'put', 'patch', 'delete'], '/layout/{module}/{entity}', 'grid')->name('layout');
        Route::match(['get', 'post'], '/export/{module}/{entity}', 'grid')->name('export');
        Route::post('/insert/{module}/{entity}', 'grid')->name('insert');
        Route::match(['patch', 'put'], '/update/{module}/{entity}', 'grid')->name('replace');
        Route::match(['delete', 'post'], '/delete/{module}/{entity}', 'grid')->name('delete');
    });

    // Last in the group on purpose. This is the catch-all for module-registered
    // domain verbs, and Laravel matches in registration order with no notion of
    // specificity, so every literal verb above must be tried first. The grid and
    // graph groups carry an extra path segment and never reach it.
    Route::post('/{action}/{module}/{entity}', [CrudController::class, 'domainAction'])
        ->name('domain-action');
});
