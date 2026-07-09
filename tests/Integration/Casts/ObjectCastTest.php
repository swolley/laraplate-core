<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\ObjectCast;

it('decodes json objects and normalizes array payloads to objects', function (): void {
    $cast = new ObjectCast();
    $model = new class extends Model
    {
        protected $table = 'object_cast_models';
    };

    $from_std_class = new stdClass();
    $from_std_class->key = 'value';

    expect($cast->get($model, 'options', $from_std_class, []))->toBe($from_std_class);
    expect($cast->get($model, 'options', ['nested' => 'value'], []))->toEqual((object) ['nested' => 'value']);
    expect($cast->get($model, 'options', '{"foo":"bar"}', []))->toEqual((object) ['foo' => 'bar']);
    expect($cast->get($model, 'options', '[]', []))->toEqual(new stdClass());
    expect($cast->get($model, 'options', 'null', []))->toEqual(new stdClass());
    expect($cast->get($model, 'options', '42', []))->toEqual(new stdClass());
});

it('encodes values for storage', function (): void {
    $cast = new ObjectCast();
    $model = new class extends Model
    {
        protected $table = 'object_cast_models';
    };

    expect($cast->set($model, 'options', ['a' => 1], []))->toBe('{"a":1}');
});
