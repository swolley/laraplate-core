<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\License;
use Modules\Core\Models\Notification;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

/**
 * The access tokens and the notifications of a user hang from it by a polymorphic key: a hard delete must
 * remove them, and must not bring the user back on the way.
 */
it('removes the access tokens and the notifications of a user deleted for good', function (): void {
    $user = User::factory()->create();
    $user->createToken('device');
    $user->notifications()->create([
        'id' => (string) Illuminate\Support\Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'hello'],
    ]);

    $user->forceDelete();

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->getKey())->count())->toBe(0)
        ->and(Notification::query()->where('notifiable_id', $user->getKey())->count())->toBe(0);
});

it('does not bring back a user with a license that is deleted for good', function (): void {
    config(['core.auth.licenses.enabled' => true]);
    $user = User::factory()->create(['license_id' => License::factory()->create()->getKey()]);

    $user->forceDelete();

    expect(User::query()->withTrashed()->whereKey($user->getKey())->exists())->toBeFalse();
});

it('removes the import origin of a record deleted for good, and keeps it for one in the bin', function (bool $forGood): void {
    $user = User::factory()->create();
    Modules\Core\Models\RecordOrigin::query()->create([
        'referable_type' => $user->getMorphClass(),
        'referable_id' => $user->getKey(),
        'source_key' => 'fixture',
        'external_id' => '42',
    ]);

    $forGood ? $user->forceDelete() : $user->delete();

    expect(Modules\Core\Models\RecordOrigin::query()->where('referable_id', $user->getKey())->exists())->toBe(! $forGood);
})->with(['deleted for good' => [true], 'moved to the bin' => [false]]);
