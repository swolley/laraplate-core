<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\Locking\VersionedLockableTestModel;

beforeEach(function (): void {
    Schema::dropIfExists('versioned_lockable_test_models');
    Schema::create('versioned_lockable_test_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->unsignedBigInteger('lock_version')->nullable();
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('locked_user_id')->nullable();
        $table->timestamp('locked_until')->nullable();
        $table->timestamps();
    });
});

it('leaves lock_version and updated_at untouched when a lock is taken', function (): void {
    $user = User::factory()->create();
    $model = VersionedLockableTestModel::query()->create(['name' => 'doc']);

    $version_before = $model->fresh()->lock_version;
    $updated_before = $model->fresh()->updated_at;

    // Backdate the row so a stray touch would be unmistakable.
    $this->travelTo(now()->addMinutes(5));

    $model->lockBy($user, now()->addMinutes(15));

    $fresh = $model->fresh();

    expect($fresh->lock_version)->toBe($version_before)
        ->and($fresh->updated_at->equalTo($updated_before))->toBeTrue()
        ->and($fresh->locked_user_id)->toBe($user->id)
        ->and($fresh->locked_at)->not->toBeNull()
        ->and($fresh->locked_until)->not->toBeNull();
});

it('leaves lock_version and updated_at untouched when a lock is released', function (): void {
    $user = User::factory()->create();
    Auth::login($user);

    $model = VersionedLockableTestModel::query()->create(['name' => 'doc']);
    $model->lockBy($user, now()->addMinutes(15));

    $version_before = $model->fresh()->lock_version;
    $updated_before = $model->fresh()->updated_at;

    $this->travelTo(now()->addMinutes(5));

    $model->unlock();

    $fresh = $model->fresh();

    expect($fresh->lock_version)->toBe($version_before)
        ->and($fresh->updated_at->equalTo($updated_before))->toBeTrue()
        ->and($fresh->locked_at)->toBeNull()
        ->and($fresh->locked_user_id)->toBeNull()
        ->and($fresh->locked_until)->toBeNull();
});

it('keeps the instance in step with the row so a later save does not rewrite the lock', function (): void {
    $user = User::factory()->create();

    // The holder is the one editing: the lock guard is on by default and would otherwise refuse the
    // save, which is exactly what it is there for.
    Auth::login($user);

    $model = VersionedLockableTestModel::query()->create(['name' => 'doc']);

    $model->lockBy($user, now()->addMinutes(15));

    // The direct write must have been synced onto the instance: nothing is left dirty, so an
    // ordinary save of the record does not carry the lock columns along with it.
    expect($model->isDirty())->toBeFalse()
        ->and($model->isDirty('locked_at'))->toBeFalse()
        ->and($model->isDirty('locked_user_id'))->toBeFalse()
        ->and($model->isDirty('locked_until'))->toBeFalse()
        ->and($model->isLockedBy($user))->toBeTrue();

    $model->name = 'edited';
    $model->save();

    $fresh = $model->fresh();

    expect($fresh->name)->toBe('edited')
        ->and($fresh->isLockedBy($user))->toBeTrue()
        ->and($fresh->lock_version)->toBe(2);
});

it('does not fire model events for a lock write', function (): void {
    $user = User::factory()->create();
    $model = VersionedLockableTestModel::query()->create(['name' => 'doc']);

    $saved = 0;
    VersionedLockableTestModel::saving(function () use (&$saved): void {
        $saved++;
    });

    $model->lockBy($user, now()->addMinutes(15));

    expect($saved)->toBe(0);

    VersionedLockableTestModel::flushEventListeners();
});
