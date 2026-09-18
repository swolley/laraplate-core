<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\Stubs\LongLivedValidityStubModel;
use Modules\Core\Tests\Stubs\ValidityStubModel;

beforeEach(function (): void {
    Schema::create('validity_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamp('valid_from')->nullable();
        $table->timestamp('valid_to')->nullable();
        $table->timestamps();
    });
});

it('returns valid from and valid to column names', function (): void {
    expect(ValidityStubModel::validFromKey())->toBe('valid_from')
        ->and(ValidityStubModel::validToKey())->toBe('valid_to');
});

it('isValid returns true when date is within range', function (): void {
    $from = now()->subDay();
    $to = now()->addDay();
    $model = ValidityStubModel::create(['name' => 'x', 'valid_from' => $from, 'valid_to' => $to]);
    expect($model->isValid(now()))->toBeTrue();
});

it('isValid accepts CarbonImmutable as the evaluation date', function (): void {
    $from = now()->subDay();
    $to = now()->addDay();
    $model = ValidityStubModel::create(['name' => 'x-immutable', 'valid_from' => $from, 'valid_to' => $to]);

    expect($model->isValid(now()))->toBeTrue();
});

it('isValid returns false when date is before valid_from', function (): void {
    $from = now()->addDay();
    $to = now()->addDays(2);
    $model = ValidityStubModel::create(['name' => 'y', 'valid_from' => $from, 'valid_to' => $to]);
    expect($model->isValid(now()))->toBeFalse();
});

it('isDraft returns true when valid_from is null', function (): void {
    $model = ValidityStubModel::create(['name' => 'z', 'valid_from' => null, 'valid_to' => null]);
    expect($model->isDraft())->toBeTrue();
});

it('isExpired returns true when valid_to is in the past', function (): void {
    $model = ValidityStubModel::create(['name' => 'w', 'valid_from' => now()->subDays(2), 'valid_to' => now()->subDay()]);
    expect($model->isExpired())->toBeTrue();
});

it('publish sets valid_from and valid_to in memory', function (): void {
    $model = ValidityStubModel::create(['name' => 'p', 'valid_from' => null, 'valid_to' => null]);
    $model->publish();
    expect($model->valid_from)->not->toBeNull()
        ->and($model->valid_to)->toBeNull();
    $model->save();
    $model->refresh();
    expect($model->valid_from)->not->toBeNull()
        ->and($model->valid_to)->toBeNull();
});

it('unpublish clears valid_from and valid_to in memory and persists when saved', function (): void {
    $model = ValidityStubModel::create(['name' => 'u', 'valid_from' => now(), 'valid_to' => null]);
    $model->unpublish();
    expect($model->valid_from)->toBeNull()
        ->and($model->valid_to)->toBeNull();
    $model->save();
    $model->refresh();
    expect($model->valid_from)->toBeNull()
        ->and($model->valid_to)->toBeNull();
});

it('expiring returns only records whose validity ends inside the window', function (): void {
    $soon = ValidityStubModel::create(['name' => 'soon', 'valid_from' => now()->subDay(), 'valid_to' => now()->addHours(5)]);
    ValidityStubModel::create(['name' => 'later', 'valid_from' => now()->subDay(), 'valid_to' => now()->addDays(10)]);
    ValidityStubModel::create(['name' => 'perpetual', 'valid_from' => now()->subDay(), 'valid_to' => null]);
    ValidityStubModel::create(['name' => 'already-expired', 'valid_from' => now()->subDays(5), 'valid_to' => now()->subDay()]);
    ValidityStubModel::create(['name' => 'not-started', 'valid_from' => now()->addDay(), 'valid_to' => now()->addHours(30)]);

    expect(ValidityStubModel::expiring()->pluck('id')->all())->toBe([$soon->id]);
});

it('expiring widens to the window the caller asks for', function (): void {
    ValidityStubModel::create(['name' => 'soon', 'valid_from' => now()->subDay(), 'valid_to' => now()->addHours(5)]);
    ValidityStubModel::create(['name' => 'next-week', 'valid_from' => now()->subDay(), 'valid_to' => now()->addDays(6)]);

    expect(ValidityStubModel::expiring()->count())->toBe(1)
        ->and(ValidityStubModel::expiring(24 * 7)->count())->toBe(2);
});

it('expiring falls back to the configured window and rejects a window under an hour', function (): void {
    config(['core.validity.expiring_within_hours' => 2]);
    ValidityStubModel::create(['name' => 'in-one-hour', 'valid_from' => now()->subDay(), 'valid_to' => now()->addHour()]);
    ValidityStubModel::create(['name' => 'in-three-hours', 'valid_from' => now()->subDay(), 'valid_to' => now()->addHours(3)]);

    expect(ValidityStubModel::expiringWithinHours())->toBe(2)
        ->and(ValidityStubModel::expiring()->count())->toBe(1);

    expect(fn (): mixed => ValidityStubModel::expiring(0)->count())
        ->toThrow(InvalidArgumentException::class);
});

it('expiring lets a model declare its own window', function (): void {
    ValidityStubModel::create(['name' => 'in-ten-days', 'valid_from' => now()->subDay(), 'valid_to' => now()->addDays(10)]);

    expect(LongLivedValidityStubModel::expiringWithinHours())->toBe(720)
        ->and(ValidityStubModel::expiringWithinHours())->toBe(48)
        ->and(LongLivedValidityStubModel::expiring()->count())->toBe(1)
        ->and(ValidityStubModel::expiring()->count())->toBe(0);
});

it('validAt is callable as a scope with the date now() returns', function (): void {
    ValidityStubModel::create(['name' => 'past', 'valid_from' => now()->subDays(10), 'valid_to' => now()->subDays(5)]);
    ValidityStubModel::create(['name' => 'current', 'valid_from' => now()->subDay(), 'valid_to' => now()->addDay()]);

    // Two defects met on this line: withValidityFilter, which validAt delegates to,
    // carried no #[Scope] and so threw BadMethodCallException, and the date was typed
    // Illuminate\Support\Carbon while now() returns a CarbonImmutable here.
    expect(ValidityStubModel::validAt(now())->pluck('name')->all())->toBe(['current'])
        ->and(ValidityStubModel::validAt(now()->subDays(7))->pluck('name')->all())->toBe(['past']);
});
