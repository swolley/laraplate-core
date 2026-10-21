<?php

declare(strict_types=1);

use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Services\Crud\Aggregate\AggregateLimits;

beforeEach(function (): void {
    config(['core.aggregate' => (require dirname(__DIR__, 4) . '/config/config.php')['aggregate']]);
});

it('reads the defaults from the Core config', function (): void {
    $limits = AggregateLimits::fromConfig();

    expect($limits->maxMeasures)->toBe(3)
        ->and($limits->maxGroups)->toBe(50)
        ->and($limits->maxPoints)->toBe(400)
        ->and($limits->maxPeriodDays)->toBe(1096)
        ->and($limits->maxObjectsPerMessage)->toBe(6)
        ->and($limits->ratePerMinute)->toBe(60);
});

it('reads an override', function (mixed $configured, int $expected): void {
    config(['core.aggregate.max_groups' => $configured]);

    expect(AggregateLimits::fromConfig()->maxGroups)->toBe($expected);
})->with([
    'an integer' => [10, 10],
    'a numeric string, as env() gives it' => ['25', 25],
]);

it('falls back to the default for a value below 1 or not numeric', function (mixed $configured): void {
    config(['core.aggregate' => array_fill_keys([
        'max_measures',
        'max_groups',
        'max_points',
        'max_period_days',
        'max_objects_per_message',
        'rate_per_minute',
    ], $configured)]);

    $limits = AggregateLimits::fromConfig();

    expect($limits->maxMeasures)->toBe(3)
        ->and($limits->maxGroups)->toBe(50)
        ->and($limits->maxPoints)->toBe(400)
        ->and($limits->maxPeriodDays)->toBe(1096)
        ->and($limits->maxObjectsPerMessage)->toBe(6)
        ->and($limits->ratePerMinute)->toBe(60);
})->with([
    'zero' => [0],
    'a negative number' => [-3],
    'text' => ['x'],
    'a number followed by text' => ['12abc'],
    'nothing' => [null],
]);

it('publishes the four limits of the capabilities', function (): void {
    expect(AggregateLimits::fromConfig()->toArray())->toBe([
        'max_measures' => 3,
        'max_groups' => 50,
        'max_points' => 400,
        'max_period_days' => 1096,
    ]);
});

it('serializes an AggregateException with its code, message and field', function (): void {
    $exception = new AggregateException(AggregateErrorCode::InvalidField, 'Unknown field.', 'measures.0.field');

    expect($exception->toArray())->toBe([
        'code' => 'INVALID_FIELD',
        'message' => 'Unknown field.',
        'field' => 'measures.0.field',
    ])
        ->and($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->getMessage())->toBe('Unknown field.');
});

it('omits the field from a serialized AggregateException when it is null', function (): void {
    $exception = new AggregateException(AggregateErrorCode::TooManyGroups, 'Too many groups.');

    expect($exception->toArray())->toBe([
        'code' => 'TOO_MANY_GROUPS',
        'message' => 'Too many groups.',
    ]);
});

it('exposes the eight codes of the contract', function (): void {
    expect(array_map(static fn (AggregateErrorCode $code): string => $code->value, AggregateErrorCode::cases()))->toBe([
        'INVALID_FIELD',
        'UNREADABLE_FIELD',
        'NOT_MEASURABLE',
        'UNSUPPORTED_FUNCTION',
        'TOO_MANY_GROUPS',
        'PERIOD_TOO_LONG',
        'TIMEOUT',
        'MIXED_CURRENCY',
    ]);
});
