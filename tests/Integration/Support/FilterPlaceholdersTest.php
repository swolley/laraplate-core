<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Core\Models\User;
use Modules\Core\Support\FilterPlaceholders;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('substitutes @now with the current time', function (): void {
    Carbon::setTestNow('2026-10-14 10:30:00');

    $resolved = FilterPlaceholders::resolve('@now');

    expect($resolved)->toBeInstanceOf(Carbon::class)
        ->and($resolved->toDateTimeString())->toBe('2026-10-14 10:30:00');
});

it('substitutes @today with the start of the day', function (): void {
    Carbon::setTestNow('2026-10-14 10:30:00');

    expect(FilterPlaceholders::resolve('@today')->toDateTimeString())->toBe('2026-10-14 00:00:00');
});

it('leaves other values untouched and resolves arrays element by element', function (): void {
    Carbon::setTestNow('2026-10-14 10:30:00');

    expect(FilterPlaceholders::resolve('plain'))->toBe('plain')
        ->and(FilterPlaceholders::resolve('@unknown'))->toBe('@unknown')
        ->and(FilterPlaceholders::resolve(42))->toBe(42)
        ->and(FilterPlaceholders::resolve(null))->toBeNull()
        ->and(FilterPlaceholders::resolve(['a', '@today'])[1]->toDateTimeString())->toBe('2026-10-14 00:00:00');
});

it('substitutes @user.<attribute> with a scalar attribute of the acting user', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(FilterPlaceholders::resolve('@user.id'))->toBe($user->id);
});

it('resolves to null what the acting user cannot give', function (string $placeholder): void {
    $this->actingAs(User::factory()->create());

    expect(FilterPlaceholders::resolve($placeholder))->toBeNull();
})->with([
    'a hidden attribute' => '@user.password',
    'an attribute that does not exist' => '@user.no_such_attribute',
    'no attribute at all' => '@user.',
]);

it('resolves @user.<attribute> to null when nobody is acting', function (): void {
    expect(FilterPlaceholders::resolve('@user.id'))->toBeNull();
});

it('leaves @user.<attribute> alone when the user is not to be read', function (): void {
    $this->actingAs(User::factory()->create());

    expect(FilterPlaceholders::resolve('@user.id', withUser: false))->toBe('@user.id');
});
