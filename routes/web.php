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

    Route::controller(CrudController::class)->group(function (): void {
        Route::patch('/lock/{module}/{entity}', 'lock')->name('lock');
        Route::patch('/unlock/{module}/{entity}', 'unlock')->name('unlock');
        Route::patch('/approve/{module}/{entity}', 'approve')->name('approve');
        Route::patch('/disapprove/{module}/{entity}', 'disapprove')->name('disapprove');
        Route::patch('/activate/{module}/{entity}', 'activate')->name('activate');
        Route::patch('/inactivate/{module}/{entity}', 'inactivate')->name('inactivate');
        Route::delete('/cache-clear/{module}/{entity}', 'clearModelCache')->name('cache-clear');
    });

    Route::controller(GridsController::class)->prefix('grid')->group(function (): void {
        Route::get('/configs/{module}/{entity?}', 'getGridsConfigs')->name('grids.getGridsConfigs');
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
});
