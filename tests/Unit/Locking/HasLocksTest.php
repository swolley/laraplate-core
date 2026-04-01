<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Locking\LockableTestModel;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('lockable_test_models');
    Schema::create('lockable_test_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->unsignedBigInteger('lock_version')->nullable();
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('locked_user_id')->nullable();
    });
});

it('initializes guarded and hidden lock columns', function (): void {
    $model = new LockableTestModel;

    expect($model->getGuarded())->toContain('is_locked')
        ->and($model->getHidden())->toContain('is_locked')
        ->and($model->getHidden())->toContain('locked_at')
        ->and($model->getHidden())->toContain('locked_user_id');
});

it('applies saving hooks for lock_version and removes virtual is_locked attribute', function (): void {
    request()->merge(['lock_version' => 7]);

    $model = new LockableTestModel;
    $model->name = 'first';
    $model->is_locked = true;
    $model->save();

    $fresh = LockableTestModel::query()->findOrFail($model->id);
    expect($fresh->lock_version)->toBe(7);
});

it('locks and unlocks model for authenticated user', function (): void {
    config()->set('core.locking.unlock_allowed', true);
    $user = User::factory()->create();
    Auth::login($user);

    $model = LockableTestModel::query()->create(['name' => 'doc']);
    $model->lockBy($user);

    expect($model->isLocked())->toBeTrue()
        ->and($model->isLockedBy($user))->toBeTrue();

    $model->unlock();

    expect($model->isUnlocked())->toBeTrue()
        ->and($model->isNotLocked())->toBeTrue()
        ->and($model->isUnlockedBy($user))->toBeTrue()
        ->and($model->isNotUnlocked())->toBeFalse()
        ->and($model->isNotUnlockedBy($user))->toBeFalse();
});

it('prevents unlock when locked by another user', function (): void {
    config()->set('core.locking.unlock_allowed', true);
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $model = LockableTestModel::query()->create(['name' => 'doc']);
    $model->lockBy($owner);

    Auth::login($other);

    expect(fn () => $model->unlock())->toThrow(CannotUnlockException::class);
});

it('prevents unlock when unlock policy does not allow model class', function (): void {
    config()->set('core.locking.unlock_allowed', false);
    config()->set('core.locking.can_be_unlocked', []);

    $user = User::factory()->create();
    Auth::login($user);

    $model = LockableTestModel::query()->create(['name' => 'doc']);
    $model->lockBy($user);

    expect(fn () => $model->unlock())->toThrow(CannotUnlockException::class);
});

it('toggles lock state with and without explicit user', function (): void {
    config()->set('core.locking.unlock_allowed', true);
    $user = User::factory()->create();
    Auth::login($user);

    $model = LockableTestModel::query()->create(['name' => 'doc']);
    $model->toggleLockBy();
    expect($model->isLocked())->toBeTrue();

    $model->toggleLock();
    expect($model->isNotLocked())->toBeTrue();

    $model->toggleLock();
    expect($model->isLocked())->toBeTrue();

    $model->toggleLockBy($user);
    expect($model->isNotLocked())->toBeTrue();
});

it('tracks original lock state helpers', function (): void {
    $user = User::factory()->create();
    $model = LockableTestModel::query()->create(['name' => 'doc']);

    expect($model->wasUnlocked())->toBeTrue()
        ->and($model->wasLocked())->toBeFalse();

    $model->lockBy($user);
    $reloaded = LockableTestModel::query()->findOrFail($model->id);

    expect($reloaded->wasLocked())->toBeTrue()
        ->and($reloaded->wasLockedBy($user))->toBeTrue()
        ->and($reloaded->wasUnlockedBy($user))->toBeFalse()
        ->and($reloaded->isNotLockedBy($user))->toBeFalse();
});

it('supports locked and unlocked scopes', function (): void {
    $user = User::factory()->create();
    $locked = LockableTestModel::query()->create(['name' => 'locked']);
    $unlocked = LockableTestModel::query()->create(['name' => 'open']);
    $locked->lockBy($user);

    expect(LockableTestModel::query()->locked()->pluck('id')->all())->toContain($locked->id)
        ->and(LockableTestModel::query()->lockedBy($user)->pluck('id')->all())->toContain($locked->id)
        ->and(LockableTestModel::query()->unlocked()->pluck('id')->all())->toContain($unlocked->id)
        ->and(LockableTestModel::query()->unlockedBy($user)->pluck('id')->all())->not->toContain($locked->id);
});
