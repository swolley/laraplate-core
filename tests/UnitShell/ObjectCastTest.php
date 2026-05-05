<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\ObjectCast;

uses(Tests\TestCase::class);

afterEach(fn () => Mockery::close());

it('normalizes json array to stdClass', function (): void {
    $cast = new ObjectCast();
    $model = Mockery::mock(Model::class);

    expect($cast->get($model, 'options', '[]', []))->toBeInstanceOf(stdClass::class);
});

it('returns stdClass for json object', function (): void {
    $cast = new ObjectCast();
    $model = Mockery::mock(Model::class);

    $result = $cast->get($model, 'options', '{"max":5}', []);

    expect($result)->toBeInstanceOf(stdClass::class);
    expect($result->max)->toBe(5);
});

it('accepts value already provided as array', function (): void {
    $cast = new ObjectCast();
    $model = Mockery::mock(Model::class);

    $result = $cast->get($model, 'options', ['min' => 1], []);

    expect($result)->toBeInstanceOf(stdClass::class);
    expect($result->min)->toBe(1);
});

it('returns empty stdClass for undecodable or non-object json primitives', function (): void {
    $cast = new ObjectCast();
    $model = Mockery::mock(Model::class);

    expect($cast->get($model, 'options', '', []))->toBeInstanceOf(stdClass::class);
    expect($cast->get($model, 'options', '42', []))->toBeInstanceOf(stdClass::class);
    expect($cast->get($model, 'options', '"text"', []))->toBeInstanceOf(stdClass::class);
});
