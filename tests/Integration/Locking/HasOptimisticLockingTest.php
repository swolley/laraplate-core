<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Locking\Exceptions\MissingLockVersionException;
use Modules\Core\Locking\Exceptions\StaleModelLockingException;
use Modules\Core\Models\Permission;
use Modules\Core\Tests\Stubs\Locking\OptimisticLockModel;
use Modules\Core\Tests\Stubs\Locking\VersionedLockModel;

beforeEach(function (): void {
    Schema::dropIfExists('optimistic_lock_models');
    Schema::create('optimistic_lock_models', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('note')->nullable();
        // Mirrors the only production column today: cms_contents.lock_version is nullable.
        $table->unsignedInteger('lock_version')->nullable();
    });
});

it('starts the lock version at one when the model is created', function (): void {
    $model = OptimisticLockModel::query()->create(['name' => 'first']);

    expect($model->fresh()->lock_version)->toBe(1);
});

it('increments the lock version by exactly one on each update', function (): void {
    $model = OptimisticLockModel::query()->create(['name' => 'first']);

    $model->update(['name' => 'second']);
    expect($model->fresh()->lock_version)->toBe(2);

    $model->update(['name' => 'third']);
    expect($model->fresh()->lock_version)->toBe(3);
});

it('rejects a writer holding a stale in-memory instance', function (): void {
    $created = OptimisticLockModel::query()->create(['name' => 'first']);

    // Two editors load the same row at the same time.
    $anna = OptimisticLockModel::query()->findOrFail($created->getKey());
    $marco = OptimisticLockModel::query()->findOrFail($created->getKey());

    $marco->name = 'saved by marco';
    $marco->save();

    $anna->name = 'saved by anna';

    expect(fn (): bool => $anna->save())->toThrow(StaleModelLockingException::class);
});

it('does NOT protect when the instance is reloaded before saving', function (): void {
    $created = OptimisticLockModel::query()->create(['name' => 'first']);

    // Marco saves first.
    $marco = OptimisticLockModel::query()->findOrFail($created->getKey());
    $marco->name = 'saved by marco';
    $marco->save();

    // Anna's form was opened before Marco saved, but the framework re-hydrates
    // the model from the database on the request that submits her form.
    $anna = OptimisticLockModel::query()->findOrFail($created->getKey());
    $anna->name = 'saved by anna';
    $anna->save();

    // Marco's change is silently overwritten: this is the lost update the
    // mechanism is supposed to prevent.
    expect($anna->fresh()->name)->toBe('saved by anna');
});

it('advances the lock version even when the changed column carries no business meaning', function (): void {
    $model = OptimisticLockModel::query()->create(['name' => 'first']);

    $model->update(['note' => 'internal bookkeeping']);

    expect($model->fresh()->lock_version)->toBe(2);
});

it('keeps the lock version out of the versionable image', function (): void {
    // A SNAPSHOT holding the guard column would make a restore write back a
    // stale token, and every later update would then fail as a false conflict.
    $versioned = new VersionedLockModel;

    expect($versioned->getDontVersionable())
        ->toContain(VersionedLockModel::lockVersionColumn());
});

it('leaves the versionable image untouched for models without optimistic locking', function (): void {
    expect(new Permission()->getDontVersionable())
        ->not->toContain('lock_version');
});

it('refuses to update a row whose lock version is null', function (): void {
    // A row written outside the model events (raw insert, import, legacy data)
    // carries no version, so the update would run without any concurrency check.
    // A partial select is a different case: the framework already raises
    // MissingAttributeException before reaching the guard.
    OptimisticLockModel::query()->insert(['name' => 'legacy', 'lock_version' => null]);

    $model = OptimisticLockModel::query()->where('name', 'legacy')->firstOrFail();
    $model->name = 'changed';

    expect(fn (): bool => $model->save())->toThrow(MissingLockVersionException::class);
});
