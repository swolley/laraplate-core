<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\Locking\LockableTestModel;

beforeEach(function (): void {
    Schema::dropIfExists('lockable_test_models');
    Schema::create('lockable_test_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->unsignedBigInteger('lock_version')->nullable();
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('locked_user_id')->nullable();
        $table->timestamp('locked_until')->nullable();
    });
});

it('declares locked_user_id with the same type as users.id', function (): void {
    // Asserted through the schema builder on purpose: SQLite ignores declared column types, so a
    // round-trip would happily store an integer in a timestamp column and prove nothing. The
    // declared type is what the migration wrote, and it is what MySQL would enforce.
    $schema = Schema::connection(null);
    $users = new User();

    $lock_by_type = $schema->getColumnType(CoreTables::Taxonomies->value, 'locked_user_id');
    $users_key_type = $schema->getColumnType($users->getTable(), $users->getKeyName());

    expect($lock_by_type)->toBe($users_key_type)
        ->and($lock_by_type)->not->toBeIn(['timestamp', 'datetime']);
});

it('adds locked_until to lockable tables and drops the stored is_locked column', function (): void {
    $schema = Schema::connection(null);

    expect($schema->hasColumn(CoreTables::Taxonomies->value, 'locked_until'))->toBeTrue()
        ->and($schema->hasColumn(CoreTables::Taxonomies->value, 'is_locked'))->toBeFalse();
});

it('treats a lock as free once its deadline has passed, with no sweep having run', function (): void {
    $user = User::factory()->create();

    $model = LockableTestModel::query()->create(['name' => 'doc']);
    $model->forceFill([
        'locked_at' => now()->subHour(),
        'locked_user_id' => $user->id,
        'locked_until' => now()->subMinute(),
    ])->save();

    $fresh = LockableTestModel::query()->findOrFail($model->id);

    // The row still carries every lock column: nothing cleaned it up.
    expect($fresh->locked_at)->not->toBeNull()
        ->and($fresh->locked_user_id)->toBe($user->id)
        ->and($fresh->isLocked())->toBeFalse()
        ->and($fresh->is_locked)->toBeFalse()
        ->and($fresh->isLockedBy($user))->toBeFalse();
});

it('keeps a lock whose deadline is still ahead, and one with no deadline at all', function (): void {
    $user = User::factory()->create();

    $leased = LockableTestModel::query()->create(['name' => 'leased']);
    $leased->forceFill([
        'locked_at' => now(),
        'locked_user_id' => $user->id,
        'locked_until' => now()->addHour(),
    ])->save();

    $frozen = LockableTestModel::query()->create(['name' => 'frozen']);
    $frozen->forceFill(['locked_at' => now(), 'locked_user_id' => null, 'locked_until' => null])->save();

    expect($leased->fresh()->isLocked())->toBeTrue()
        ->and($leased->fresh()->isLockedBy($user))->toBeTrue()
        ->and($frozen->fresh()->isLocked())->toBeTrue();
});

it('keeps the locked and unlocked scopes in step with the model', function (): void {
    $user = User::factory()->create();

    $free = LockableTestModel::query()->create(['name' => 'free']);

    $expired = LockableTestModel::query()->create(['name' => 'expired']);
    $expired->forceFill([
        'locked_at' => now()->subHour(),
        'locked_user_id' => $user->id,
        'locked_until' => now()->subMinute(),
    ])->save();

    $live = LockableTestModel::query()->create(['name' => 'live']);
    $live->forceFill([
        'locked_at' => now(),
        'locked_user_id' => $user->id,
        'locked_until' => now()->addHour(),
    ])->save();

    $frozen = LockableTestModel::query()->create(['name' => 'frozen']);
    $frozen->forceFill(['locked_at' => now(), 'locked_user_id' => null, 'locked_until' => null])->save();

    $locked_ids = LockableTestModel::query()->locked()->pluck('id')->all();
    $unlocked_ids = LockableTestModel::query()->unlocked()->pluck('id')->all();

    expect($locked_ids)->toEqualCanonicalizing([$live->id, $frozen->id])
        ->and($unlocked_ids)->toEqualCanonicalizing([$free->id, $expired->id]);
});

it('exposes is_locked in the payload without a stored column behind it', function (): void {
    $user = User::factory()->create();

    $model = LockableTestModel::query()->create(['name' => 'doc']);
    $model->lockBy($user);

    $array = LockableTestModel::query()->findOrFail($model->id)->toArray();

    expect($array)->toHaveKey('is_locked')
        ->and($array['is_locked'])->toBeTrue()
        ->and(Schema::hasColumn('lockable_test_models', 'is_locked'))->toBeFalse();
});
