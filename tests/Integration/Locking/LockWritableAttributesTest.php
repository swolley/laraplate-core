<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Tests\Stubs\Locking\PartiallyWritableLockModel;
use Modules\Core\Tests\Stubs\Locking\StrictlyLockedTestModel;

beforeEach(function (): void {
    config()->set('core.locking.prevent_modifications_on_locked_objects', true);

    Schema::dropIfExists('lockable_test_models');
    Schema::create('lockable_test_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->unsignedInteger('counter')->default(0);
        $table->timestamp('locked_at')->nullable();
        $table->unsignedBigInteger('locked_user_id')->nullable();
        $table->timestamp('locked_until')->nullable();
    });
});

it('lets a frozen record accept a write confined to the attributes it declares', function (): void {
    $model = PartiallyWritableLockModel::query()->create(['name' => 'doc', 'counter' => 0]);
    $model->lock();

    $model->update(['counter' => 3]);

    expect($model->fresh()->counter)->toBe(3);
});

it('refuses a write that touches a declared attribute and an ordinary one together', function (): void {
    $model = PartiallyWritableLockModel::query()->create(['name' => 'doc', 'counter' => 0]);
    $model->lock();

    expect(fn () => $model->update(['counter' => 3, 'name' => 'renamed']))
        ->toThrow(LockedModelException::class);

    $fresh = $model->fresh();

    expect($fresh->counter)->toBe(0)
        ->and($fresh->name)->toBe('doc');
});

it('keeps refusing every write on a model that declares nothing', function (): void {
    $model = StrictlyLockedTestModel::query()->create(['name' => 'doc', 'counter' => 0]);
    $model->lock();

    expect(fn () => $model->update(['counter' => 3]))
        ->toThrow(LockedModelException::class)
        ->and($model->fresh()->counter)->toBe(0);
});

it('still refuses to delete a frozen record whatever it declares', function (): void {
    $model = PartiallyWritableLockModel::query()->create(['name' => 'doc', 'counter' => 0]);
    $model->lock();

    expect(fn () => $model->delete())->toThrow(LockedModelException::class)
        ->and(PartiallyWritableLockModel::query()->whereKey($model->getKey())->exists())->toBeTrue();
});
