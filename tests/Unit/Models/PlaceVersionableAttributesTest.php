<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\Core\Models\Place;
use Overtrue\LaravelVersionable\VersionStrategy;

it('excludes geolocation from versionable snapshot attributes', function (): void {
    $place = Place::query()->create([
        'address' => '1 Test Rd',
        'city' => 'City',
        'province' => 'PR',
        'country' => 'IT',
        'postcode' => '12345',
        'latitude' => 45.4,
        'longitude' => 9.2,
    ]);

    $snapshot = $place->getVersionableAttributes(VersionStrategy::SNAPSHOT);

    expect($snapshot)->not->toHaveKey('geolocation')
        ->and($snapshot)->toHaveKey('latitude')
        ->and($snapshot)->toHaveKey('longitude')
        ->and($snapshot['country'])->toBe('IT');
});

it('realigns geolocation from latitude and longitude on save when coordinates change', function (): void {
    $place = Place::query()->create([
        'address' => '1 Test Rd',
        'city' => 'City',
        'province' => 'PR',
        'country' => 'IT',
        'postcode' => '12345',
        'latitude' => 1.0,
        'longitude' => 2.0,
    ]);

    $place->latitude = 10.5;
    $place->longitude = 20.25;
    $place->save();
    $place->refresh();

    expect($place->latitude)->toBe(10.5)
        ->and($place->longitude)->toBe(20.25);

    if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
        expect($place->geolocation)->toBeInstanceOf(Point::class)
            ->and($place->geolocation->latitude)->toBe(10.5)
            ->and($place->geolocation->longitude)->toBe(20.25);
    }
});
