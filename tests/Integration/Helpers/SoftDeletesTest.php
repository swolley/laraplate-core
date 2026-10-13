<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\Stubs\HardDeletesStubModel;
use Modules\Core\Tests\Stubs\SoftDeletesStubModel;

beforeEach(function (): void {
    Schema::create('soft_deletes_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamp('deleted_at')->nullable();
        $table->boolean('is_deleted')->default(false);
        $table->timestamps();
    });
});

it('exposes is_deleted column helpers', function (): void {
    $model = new SoftDeletesStubModel;

    expect($model->getIsDeletedColumn())->toBe('is_deleted')
        ->and($model->getQualifiedIsDeletedColumn())->toContain('is_deleted');
});

it('guards and hides soft delete columns on initialize', function (): void {
    $model = new SoftDeletesStubModel;
    $model->initializeSoftDeletes();

    expect($model->getHidden())->toContain('deleted_at')
        ->and($model->getHidden())->toContain('is_deleted')
        ->and($model->getGuarded())->toContain('is_deleted');
});

it('casts is_deleted to boolean, as it does for deleted_at', function (): void {
    DB::table('soft_deletes_stub')->insert(['name' => 'live', 'created_at' => now(), 'updated_at' => now()]);

    $model = SoftDeletesStubModel::withoutGlobalScopes()->firstOrFail();

    expect($model->getCasts())->toHaveKey('is_deleted', 'boolean')
        ->and($model->is_deleted)->toBeFalse();
});

it('uses model property to disable soft deletes persistence', function (): void {
    $model = new class extends SoftDeletesStubModel
    {
        protected bool $softDeletesEnabled = false;
    };

    expect($model->softDeletesEnabledBySettings())->toBeFalse();
});

it('detects trashed state from original is_deleted value', function (): void {
    $model = new SoftDeletesStubModel;
    $model->setRawAttributes(['is_deleted' => true], true);
    $model->setRawAttributes([]);

    expect($model->trashed())->toBeTrue();
});

it('detects trashed state from original deleted_at value', function (): void {
    $model = new SoftDeletesStubModel;
    $model->setRawAttributes(['deleted_at' => now()], true);
    $model->setRawAttributes([]);

    expect($model->trashed())->toBeTrue();
});

it('returns false when no soft delete state is present', function (): void {
    expect((new SoftDeletesStubModel)->trashed())->toBeFalse();
});

it('restores soft deleted models when soft deletes persistence is enabled', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'restorable']);
    $model->delete();

    expect($model->restore())->toBeTrue()
        ->and($model->fresh()->trashed())->toBeFalse();
});

it('detects trashed state from current is_deleted attribute', function (): void {
    $model = new SoftDeletesStubModel;
    $model->setRawAttributes(['is_deleted' => true]);

    expect($model->trashed())->toBeTrue();
});

it('detects trashed state from current deleted_at attribute', function (): void {
    $model = new SoftDeletesStubModel;
    $model->setRawAttributes(['deleted_at' => now()->toDateTimeString()]);

    expect($model->trashed())->toBeTrue();
});

it('revives soft deleted state in memory for the next save', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'revive']);
    $model->delete();
    expect($model->trashed())->toBeTrue();

    $model->reviveInMemory();

    expect($model->trashed())->toBeFalse();
    $model->save();
    expect($model->fresh()->trashed())->toBeFalse();
});

it('uses per-model settings when softDeletesEnabled property is absent', function (): void {
    $model = new SoftDeletesStubModel;

    expect($model->softDeletesEnabledBySettings())->toBeTrue();
});

it('throws when updating a trashed model', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'a']);
    $model->delete();

    try {
        $model->update(['name' => 'b']);
        expect(false)->toBeTrue('Expected AuthorizationException');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(AuthorizationException::class);
        expect($e->getMessage())->toContain('Cannot update');
    }
});

it('deletes like a force delete after authorization when soft deletes are switched off', function (): void {
    $model = HardDeletesStubModel::create(['name' => 'gone']);
    $seen = [];
    Event::listen('eloquent.deleting: ' . HardDeletesStubModel::class, static function (HardDeletesStubModel $deleting) use (&$seen): void {
        $seen['deleting'] = $deleting->isForceDeleting();
    });
    Event::listen('eloquent.deleted: ' . HardDeletesStubModel::class, static function (HardDeletesStubModel $deleted) use (&$seen): void {
        $seen['deleted'] = $deleted->isForceDeleting();
    });
    Event::listen('eloquent.forceDeleted: ' . HardDeletesStubModel::class, static function () use (&$seen): void {
        $seen['forceDeleted'] = true;
    });

    expect($model->delete())->toBeTrue()
        ->and($seen)->toBe(['deleting' => false, 'deleted' => true, 'forceDeleted' => true])
        ->and($model->isForceDeleting())->toBeFalse()
        ->and(DB::table('soft_deletes_stub')->where('id', $model->getKey())->exists())->toBeFalse();
});

it('fires forceDeleted once when a model with soft deletes switched off is force deleted', function (): void {
    $model = HardDeletesStubModel::create(['name' => 'gone']);
    $fired = 0;
    Event::listen('eloquent.forceDeleted: ' . HardDeletesStubModel::class, static function () use (&$fired): void {
        $fired++;
    });

    $model->forceDelete();

    expect($fired)->toBe(1)
        ->and(DB::table('soft_deletes_stub')->where('id', $model->getKey())->exists())->toBeFalse();
});

it('fires no forceDeleted on a soft delete', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'kept']);
    $fired = false;
    Event::listen('eloquent.forceDeleted: ' . SoftDeletesStubModel::class, static function () use (&$fired): void {
        $fired = true;
    });

    $model->delete();

    expect($fired)->toBeFalse()
        ->and(SoftDeletesStubModel::withoutGlobalScopes()->findOrFail($model->getKey())->trashed())->toBeTrue();
});
