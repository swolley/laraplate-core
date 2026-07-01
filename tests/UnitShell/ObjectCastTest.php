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

it('returns value already provided as stdClass', function (): void {
    $cast = new ObjectCast();
    $model = Mockery::mock(Model::class);
    $value = new stdClass();
    $value->enabled = true;

    $result = $cast->get($model, 'options', $value, []);

    expect($result)->toBe($value);
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

it('encodes values when setting the cast attribute', function (): void {
    $cast = new ObjectCast();
    $model = Mockery::mock(Model::class);

    expect($cast->set($model, 'options', ['max' => 5], []))->toBe('{"max":5}');
});
