<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Here is where you can register API routes for your application. These
    | routes are loaded by the RouteServiceProvider within a group which
    | is assigned the "api" middleware group. Enjoy building your API!
    |
*/

Route::name('crud.')->prefix('/crud')->group(function (): void {
    require __DIR__ . '/graph.php';
});

require __DIR__ . '/crud.php';
