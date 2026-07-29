<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Tests\Stubs\Place\PlaceAffinityModel;

afterEach(function (): void {
    DB::purge('place-affinity');
});

it('checks spatial support on the model connection', function (): void {
    config()->set('database.connections.place-affinity', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'database' => 'unused',
        'username' => 'unused',
        'password' => 'unused',
    ]);

    $model = (new PlaceAffinityModel)->setConnection('place-affinity');

    expect($model->persistsSpatialGeometry())->toBeTrue();
});
